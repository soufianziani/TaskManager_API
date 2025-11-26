<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Category;
use App\Models\Type;
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
        'type_id',
        'period_type',
        'period_start',
        'period_end',
        'period_days',
        'period_urgent',
        'type_justif',
        'users',
        'step',
        'file',
        'justif_file',
        'controller',
        'alarm',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
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
     * Get the type associated with the task.
     * Note: This relationship may not exist if type_id was removed from tasks table.
     */
    public function type()
    {
        return $this->belongsTo(Type::class, 'type_id');
    }

    /**
     * Get the department associated with the task.
     * Note: This relationship may not exist if department_id was removed from tasks table.
     */
    public function departmentRelation()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}
