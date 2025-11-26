<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskName extends Model
{
    use HasFactory;

    protected $table = 'task_name';

    protected $fillable = [
        'category_id',
        'name',
        'icon',
        'color',
        'description',
        'permission',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the category that owns the task name.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get all tasks for this task name.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'task_name');
    }
}

