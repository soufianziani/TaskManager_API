<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delay extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'task_id',
        'count',
        'delay_until',
    ];

    protected $casts = [
        'delay_until' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the task associated with the delay
     */
    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'id');
    }

    /**
     * Get the user associated with the delay
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
