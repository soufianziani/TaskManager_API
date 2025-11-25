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
        Schema::table('tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks', 'tasks_type')) {
                $table->string('tasks_type')->after('description');
            }
            if (!Schema::hasColumn('tasks', 'task_step')) {
                $table->string('task_step')->default('pending')->after('tasks_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'task_step')) {
                $table->dropColumn('task_step');
            }
            if (Schema::hasColumn('tasks', 'tasks_type')) {
                $table->dropColumn('tasks_type');
            }
        });
    }
};
