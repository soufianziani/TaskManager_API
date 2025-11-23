<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'permission',
        'is_active',
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
}
