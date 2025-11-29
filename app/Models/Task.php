<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Category;
use App\Models\TaskName;
use App\Models\Department;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'type',
        'status',
        'url',
        'redirect',
        'department',
        'category_id',
        'task_name',
        'period_type',
        'period_start',
        'period_end',
        'time_cloture',
        'time_out',
        'timeout_notified_at',
        'period_days',
        'period_urgent',
        'type_justif',
        'users',
        'step',
        'file',
        'justif_file',
        'controller',
        'alarm',
        'rest_time',
        'rest_max',
        'created_by',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'timeout_notified_at' => 'datetime',
        'status' => 'boolean',
        'redirect' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Append the raw justif_file value to the array representation.
     * This ensures the JSON array string is available even when relationship is loaded.
     */
    protected $appends = [];

    /**
     * Get the raw justif_file value (for JSON array strings).
     */
    public function getJustifFileRawAttribute()
    {
        return $this->attributes['justif_file'] ?? null;
    }

    /**
     * Get the file associated with the task.
     */
    public function taskFile()
    {
        return $this->belongsTo(File::class, 'file');
    }

    /**
     * Get the justification file associated with the task.
     * Note: This only works for single file IDs. For JSON array strings, use the raw justif_file field.
     */
    public function justifFile()
    {
        return $this->belongsTo(File::class, 'justif_file');
    }

    /**
     * Get the raw justif_file value from attributes (before relationship override).
     */
    public function getRawJustifFileAttribute()
    {
        return $this->attributes['justif_file'] ?? null;
    }

    /**
     * Get all refuses for this task.
     * Note: The 'task' field in refuse table is varchar(255), so we need to cast the task ID to string for comparison.
     */
    public function refuses()
    {
        return $this->hasMany(Refuse::class, 'task', 'id')
            ->where('task', (string)$this->id);
    }

    /**
     * Get the category associated with the task.
     * Note: This relationship may not exist if category_id was removed from tasks table.
     */
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Get the task name associated with the task.
     * Note: This relationship uses task_name (string) to match with TaskName model's name field.
     */
    public function taskNameRelation()
    {
        return $this->belongsTo(TaskName::class, 'task_name', 'name');
    }

    /**
     * Get the department associated with the task.
     * Note: This relationship may not exist if department_id was removed from tasks table.
     */
    public function departmentRelation()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Parse time_out string to get days, hours, and minutes
     * Supports formats like "2 days, 3 hours, 5 mins" or JSON format
     */
    public function parseTimeOut(?string $timeOutStr): ?array
    {
        if (empty($timeOutStr)) {
            return null;
        }

        // Try to parse as JSON first
        $timeOutData = json_decode($timeOutStr, true);
        if (is_array($timeOutData)) {
            // Return the first value if it's a JSON object (for per-day timeouts)
            // For now, we'll use the first entry or return null
            // This can be enhanced later for per-day timeout handling
            return null;
        }

        // Parse format like "2 days, 3 hours, 5 mins"
        $days = 0;
        $hours = 0;
        $minutes = 0;

        if (preg_match('/(\d+)\s+days?/i', $timeOutStr, $matches)) {
            $days = (int)$matches[1];
        }
        if (preg_match('/(\d+)\s+hours?/i', $timeOutStr, $matches)) {
            $hours = (int)$matches[1];
        }
        if (preg_match('/(\d+)\s+mins?/i', $timeOutStr, $matches)) {
            $minutes = (int)$matches[1];
        }

        if ($days == 0 && $hours == 0 && $minutes == 0) {
            return null;
        }

        return [
            'days' => $days,
            'hours' => $hours,
            'minutes' => $minutes,
        ];
    }

    /**
     * Calculate timeout datetime from time_cloture and time_out
     * Returns null if calculation is not possible
     */
    public function calculateTimeoutDateTime(): ?\Carbon\Carbon
    {
        if (empty($this->time_cloture) || empty($this->time_out)) {
            return null;
        }

        // Parse time_cloture (can be JSON or single datetime string)
        $timeClotureStr = $this->time_cloture;
        $timeClotureData = json_decode($timeClotureStr, true);
        
        $timeCloture = null;
        if (is_array($timeClotureData)) {
            // For JSON format, use the first value (or could be enhanced for per-day)
            $firstValue = reset($timeClotureData);
            if (is_string($firstValue)) {
                try {
                    $timeCloture = \Carbon\Carbon::parse($firstValue);
                } catch (\Exception $e) {
                    return null;
                }
            }
        } else {
            // Single datetime string
            try {
                $timeCloture = \Carbon\Carbon::parse($timeClotureStr);
            } catch (\Exception $e) {
                return null;
            }
        }

        if (!$timeCloture) {
            return null;
        }

        // Parse time_out
        $timeOutData = $this->parseTimeOut($this->time_out);
        if (!$timeOutData) {
            return null;
        }

        // Calculate timeout datetime (time_cloture - time_out)
        $timeout = $timeCloture->copy()
            ->subDays($timeOutData['days'])
            ->subHours($timeOutData['hours'])
            ->subMinutes($timeOutData['minutes']);

        return $timeout;
    }
}
