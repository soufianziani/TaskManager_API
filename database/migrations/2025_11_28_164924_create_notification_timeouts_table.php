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
        if (!Schema::hasTable('notification_timeouts')) {
            Schema::create('notification_timeouts', function (Blueprint $table) {
                $table->id();
                $table->string('task_id', 255);
                $table->string('users_id', 255);
                $table->text('description')->nullable();
                $table->timestamps();
                
                // Indexes for faster queries
                $table->index(['task_id', 'users_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_timeouts');
    }
};
