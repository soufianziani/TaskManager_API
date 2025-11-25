<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refuse extends Model
{
    use HasFactory;

    protected $table = 'refuse';

    protected $fillable = [
        'description',
        'task',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who created the refusal.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the task associated with this refusal.
     */
    public function taskModel()
    {
        return $this->belongsTo(Task::class, 'task');
    }
}

