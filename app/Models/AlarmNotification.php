<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlarmNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'users_id',
        'description',
        'next',
        'rest_max',
        'notification_count',
        'read',
    ];

    protected $casts = [
        'next' => 'datetime',
        'rest_max' => 'integer',
        'notification_count' => 'integer',
        'read' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Task related to this alarm notification.
     */
    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'id');
    }
}

