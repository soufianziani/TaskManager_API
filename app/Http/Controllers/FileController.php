<?php

namespace App\Http\Controllers;

use App\Models\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    /**
     * Upload a file and save it to the files table.
     */
    public function upload(Request $request): JsonResponse
    {
        $type = $request->input('type');
        
        // Build validation rules based on file type
        $fileRules = ['required', 'file'];
        
        // Add file size and MIME type validation based on type
        switch ($type) {
            case 'video':
                // Video files: max 500MB, allow common video MIME types
                $fileRules[] = 'max:512000'; // 500MB in KB
                $fileRules[] = 'mimes:mp4,mov,avi,mkv,webm,flv,wmv,m4v,3gp,quicktime,mpg,mpeg,qt';
                $fileRules[] = 'mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,video/webm,video/x-flv,video/x-ms-wmv,video/3gpp,video/mpeg,video/x-m4v';
                break;
            case 'foto':
                // Image files: max 50MB, allow common image MIME types
                $fileRules[] = 'max:51200'; // 50MB in KB
                $fileRules[] = 'mimes:jpg,jpeg,png,gif,bmp,webp';
                $fileRules[] = 'mimetypes:image/jpeg,image/png,image/gif,image/bmp,image/webp';
                break;
            case 'pdf':
                // PDF files: max 100MB
                $fileRules[] = 'max:102400'; // 100MB in KB
                $fileRules[] = 'mimes:pdf';
                $fileRules[] = 'mimetypes:application/pdf';
                break;
            case 'foto or video':
                // Can be either image or video: max 500MB
                $fileRules[] = 'max:512000'; // 500MB in KB
                $fileRules[] = 'mimes:jpg,jpeg,png,gif,bmp,webp,mp4,mov,avi,mkv,webm,flv,wmv,m4v,3gp,quicktime,mpg,mpeg,qt';
                $fileRules[] = 'mimetypes:image/jpeg,image/png,image/gif,image/bmp,image/webp,video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,video/webm,video/x-flv,video/x-ms-wmv,video/3gpp,video/mpeg,video/x-m4v';
                break;
            default:
                // Default: max 100MB
                $fileRules[] = 'max:102400'; // 100MB in KB
        }
        
        $request->validate([
            'file' => $fileRules,
            'type' => 'required|string|in:foto,video,pdf,url,foto or video',
            'file_for' => 'required|string|in:justif,task',
        ]);

        try {
            $uploadedFile = $request->file('file');
            $fileFor = $request->file_for;

            // Generate unique filename
            $filename = time() . '_' . uniqid() . '.' . $uploadedFile->getClientOriginalExtension();
            
            // Store file in storage/app/public/files
            $path = $uploadedFile->storeAs('files', $filename, 'public');

            // Save file record to database
            // Use the full path: storage/files/filename.ext
            $file = File::create([
                'type' => $type,
                'file_for' => $fileFor,
                'url' => 'storage/' . $path, // Returns storage/files/filename.ext
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => [
                    'id' => $file->id,
                    'type' => $file->type,
                    'file_for' => $file->file_for,
                    'url' => $file->url,
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->errors();
            $errorMessages = [];
            
            // Extract all error messages into a readable format
            foreach ($errors as $field => $messages) {
                if (is_array($messages)) {
                    foreach ($messages as $msg) {
                        // Make error messages more descriptive
                        if (strpos($msg, 'max:') !== false) {
                            // Extract the max size from the error message
                            preg_match('/max:(\d+)/', $msg, $matches);
                            if (!empty($matches[1])) {
                                $maxKB = (int)$matches[1];
                                $maxMB = round($maxKB / 1024, 1);
                                $errorMessages[] = "File size exceeds maximum allowed size of {$maxMB}MB.";
                            } else {
                                $errorMessages[] = $msg;
                            }
                        } elseif (strpos($msg, 'mimes') !== false || strpos($msg, 'mimetypes') !== false) {
                            $errorMessages[] = "File type (MIME type) is not supported. Please ensure the file is a valid video format (MP4, MOV, AVI, etc.).";
                        } else {
                            $errorMessages[] = $msg;
                        }
                    }
                } else {
                    $errorMessages[] = $messages;
                }
            }
            
            $message = !empty($errorMessages) 
                ? implode(' ', $errorMessages)
                : 'Validation failed. Please check your file type and size.';
            
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => $errors,
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload a file from URL (for URL type files).
     */
    public function uploadFromUrl(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'required|url',
            'type' => 'required|string|in:url',
            'file_for' => 'required|string|in:justif,task',
        ]);

        try {
            $file = File::create([
                'type' => $request->type,
                'file_for' => $request->file_for,
                'url' => $request->url,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File URL saved successfully',
                'data' => [
                    'id' => $file->id,
                    'type' => $file->type,
                    'file_for' => $file->file_for,
                    'url' => $file->url,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving file URL: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get file information by ID.
     */
    public function getFile($id): JsonResponse
    {
        try {
            $file = File::find($id);

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'File retrieved successfully',
                'data' => [
                    'id' => $file->id,
                    'type' => $file->type,
                    'file_for' => $file->file_for,
                    'url' => $file->url,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving file: ' . $e->getMessage(),
            ], 500);
        }
    }
}
