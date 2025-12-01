<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationTimeout extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'users_id',
        'description',
        'next',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'next' => 'datetime',
    ];

    /**
     * Get the task associated with the notification
     */
    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'id');
    }

    /**
     * Get the user associated with the notification
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'users_id', 'id');
    }
}
