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
     * Calculate timeout datetime from time_cloture and time_out
     * Returns null if calculation is not possible
     * 
     * Logic:
     * - time_out is the task start time (e.g., "09:00:00")
     * - time_cloture is the task end time (e.g., "14:30:00")
     * - Uses period_start as the base date, or current date if period_start is not set
     */
    public function calculateTimeoutDateTime(): ?\Carbon\Carbon
    {
        if (empty($this->time_cloture) || empty($this->time_out)) {
            return null;
        }

        // Get base date from period_start or use current date
        $baseDate = null;
        if (!empty($this->period_start)) {
            try {
                $baseDate = \Carbon\Carbon::parse($this->period_start)->startOfDay();
            } catch (\Exception $e) {
                $baseDate = \Carbon\Carbon::now()->startOfDay();
            }
        } else {
            $baseDate = \Carbon\Carbon::now()->startOfDay();
        }

        // Parse time_out (task start time) - format: "HH:mm:ss" or "HH:mm"
        $timeOutStr = $this->time_out;
        if (is_object($timeOutStr) && method_exists($timeOutStr, 'format')) {
            // Already a Carbon instance, get time string
            $timeOutStr = $timeOutStr->format('H:i:s');
        }
        
        try {
            // Parse time string (e.g., "09:00:00" or "09:00")
            $timeOutParts = explode(':', $timeOutStr);
            if (count($timeOutParts) < 2) {
                return null;
            }
            
            $startHour = (int)$timeOutParts[0];
            $startMinute = (int)$timeOutParts[1];
            $startSecond = isset($timeOutParts[2]) ? (int)$timeOutParts[2] : 0;
            
            // Create start datetime
            $startDateTime = $baseDate->copy()
                ->setTime($startHour, $startMinute, $startSecond);
            
            // The start datetime is the timeout datetime (when to send notification)
            return $startDateTime;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Calculate task end datetime from time_cloture
     * Returns null if calculation is not possible
     */
    public function calculateEndDateTime(): ?\Carbon\Carbon
    {
        if (empty($this->time_cloture)) {
            return null;
        }

        // Get base date from period_start or use current date
        $baseDate = null;
        if (!empty($this->period_start)) {
            try {
                $baseDate = \Carbon\Carbon::parse($this->period_start)->startOfDay();
            } catch (\Exception $e) {
                $baseDate = \Carbon\Carbon::now()->startOfDay();
            }
        } else {
            $baseDate = \Carbon\Carbon::now()->startOfDay();
        }

        // Parse time_cloture (task end time) - format: "HH:mm:ss" or "HH:mm"
        $timeClotureStr = $this->time_cloture;
        if (is_object($timeClotureStr) && method_exists($timeClotureStr, 'format')) {
            // Already a Carbon instance, get time string
            $timeClotureStr = $timeClotureStr->format('H:i:s');
        }
        
        try {
            // Parse time string (e.g., "14:30:00" or "14:30")
            $timeClotureParts = explode(':', $timeClotureStr);
            if (count($timeClotureParts) < 2) {
                return null;
            }
            
            $endHour = (int)$timeClotureParts[0];
            $endMinute = (int)$timeClotureParts[1];
            $endSecond = isset($timeClotureParts[2]) ? (int)$timeClotureParts[2] : 0;
            
            // Create end datetime (same day as base date)
            $endDateTime = $baseDate->copy()
                ->setTime($endHour, $endMinute, $endSecond);
            
            return $endDateTime;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Calculate alarm start datetime from task end time and alarm offset
     * Alarm offset is stored in alarm field as JSON: {"days": 1, "hours": 2, "minutes": 30}
     * Returns null if calculation is not possible
     */
    public function calculateAlarmStartTime(): ?\Carbon\Carbon
    {
        if (empty($this->alarm) || empty($this->time_cloture)) {
            return null;
        }

        // Get task end datetime
        $endDateTime = $this->calculateEndDateTime();
        if (!$endDateTime) {
            return null;
        }

        try {
            // Parse alarm JSON
            $alarmData = json_decode($this->alarm, true);
            if (!is_array($alarmData) || empty($alarmData)) {
                return null;
            }

            // Check for alarm offset format: {"days": 1, "hours": 2, "minutes": 30}
            // Note: hours can be -1 (from global server), treat as 0
            if (isset($alarmData['days']) || isset($alarmData['hours']) || isset($alarmData['minutes'])) {
                $days = (int)($alarmData['days'] ?? 0);
                $hours = (int)($alarmData['hours'] ?? 0);
                // Convert -1 to 0 for calculation (backend stores -1, but we treat it as 0)
                if ($hours == -1) {
                    $hours = 0;
                }
                $minutes = (int)($alarmData['minutes'] ?? 0);
                $seconds = (int)($alarmData['seconds'] ?? 0);

                // Calculate alarm start time by subtracting offset from end time
                $alarmStartTime = $endDateTime->copy()
                    ->subDays($days)
                    ->subHours($hours)
                    ->subMinutes($minutes)
                    ->subSeconds($seconds);

                return $alarmStartTime;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
