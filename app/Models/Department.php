<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'permission',
        'is_active',
        'icon',
        'color',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get all types for this department.
     */
    public function types(): HasMany
    {
        return $this->hasMany(Type::class);
    }

    /**
     * Get all categories for this department.
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * Get all tasks for this department.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'department_id');
    }
}
