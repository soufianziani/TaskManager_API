<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Type extends Model
{
    use HasFactory;

    // Map to the new underlying table name `task_name`
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
     * Get the category that owns the type.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get all tasks for this type.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'type_id');
    }
}
