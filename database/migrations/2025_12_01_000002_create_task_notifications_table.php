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
        if (!Schema::hasTable('task_notifications')) {
            Schema::create('task_notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('task_id')->nullable();
                $table->string('title');
                $table->text('body');
                $table->string('type', 50)->default('task'); // e.g. task_assigned, task_status_updated
                $table->timestamps();

                $table->index(['user_id', 'task_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_notifications');
    }
};


