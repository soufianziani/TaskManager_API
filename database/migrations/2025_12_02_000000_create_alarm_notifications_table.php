<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('alarm_notifications')) {
            Schema::create('alarm_notifications', function (Blueprint $table) {
                $table->id();
                $table->string('task_id', 255);
                $table->string('users_id', 255);
                $table->text('description')->nullable();
                $table->datetime('next')->nullable(); // Next alarm notification time
                $table->integer('rest_max')->default(0); // Maximum number of alarm notifications
                $table->integer('notification_count')->default(0); // Current notification count
                $table->integer('read')->default(0); // Read status (0 = unread, 1 = read)
                $table->timestamps();
                
                // Indexes for faster queries
                $table->index(['task_id', 'users_id']);
                $table->index('next');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alarm_notifications');
    }
};

