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
            // Drop old columns
            if (Schema::hasColumn('tasks', 'tasks_type')) {
                $table->dropColumn('tasks_type');
            }
            if (Schema::hasColumn('tasks', 'task_step')) {
                $table->dropColumn('task_step');
            }
            if (Schema::hasColumn('tasks', 'type_id')) {
                $table->dropForeign(['type_id']);
                $table->dropColumn('type_id');
            }
            if (Schema::hasColumn('tasks', 'department_id')) {
                $table->dropForeign(['department_id']);
                $table->dropColumn('department_id');
            }
            if (Schema::hasColumn('tasks', 'category_id')) {
                $table->dropForeign(['category_id']);
                $table->dropColumn('category_id');
            }
            if (Schema::hasColumn('tasks', 'justif_type')) {
                $table->dropColumn('justif_type');
            }
            if (Schema::hasColumn('tasks', 'duration')) {
                $table->dropColumn('duration');
            }
            if (Schema::hasColumn('tasks', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }
        });

        Schema::table('tasks', function (Blueprint $table) {
            // Add new columns
            if (!Schema::hasColumn('tasks', 'type')) {
                $table->string('type', 100)->after('description');
            }
            if (!Schema::hasColumn('tasks', 'status')) {
                $table->boolean('status')->default(1)->after('type');
            }
            if (!Schema::hasColumn('tasks', 'url')) {
                $table->string('url', 255)->nullable()->after('status');
            }
            if (!Schema::hasColumn('tasks', 'redirect')) {
                $table->boolean('redirect')->default(0)->after('url');
            }
            if (!Schema::hasColumn('tasks', 'department')) {
                $table->mediumText('department')->nullable()->after('redirect');
            }
            if (!Schema::hasColumn('tasks', 'period_days')) {
                $table->mediumText('period_days')->nullable()->after('period_end');
            }
            if (!Schema::hasColumn('tasks', 'period_urgent')) {
                $table->longText('period_urgent')->nullable()->after('period_days');
            }
            if (!Schema::hasColumn('tasks', 'type_justif')) {
                $table->mediumText('type_justif')->nullable()->after('period_urgent');
            }
            if (!Schema::hasColumn('tasks', 'users')) {
                $table->longText('users')->nullable()->after('type_justif');
            }
            if (!Schema::hasColumn('tasks', 'step')) {
                $table->string('step', 255)->nullable()->after('users');
            }
            if (!Schema::hasColumn('tasks', 'files_id')) {
                $table->string('files_id', 255)->nullable()->after('step');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Remove new columns
            if (Schema::hasColumn('tasks', 'files_id')) {
                $table->dropColumn('files_id');
            }
            if (Schema::hasColumn('tasks', 'step')) {
                $table->dropColumn('step');
            }
            if (Schema::hasColumn('tasks', 'users')) {
                $table->dropColumn('users');
            }
            if (Schema::hasColumn('tasks', 'type_justif')) {
                $table->dropColumn('type_justif');
            }
            if (Schema::hasColumn('tasks', 'period_urgent')) {
                $table->dropColumn('period_urgent');
            }
            if (Schema::hasColumn('tasks', 'period_days')) {
                $table->dropColumn('period_days');
            }
            if (Schema::hasColumn('tasks', 'department')) {
                $table->dropColumn('department');
            }
            if (Schema::hasColumn('tasks', 'redirect')) {
                $table->dropColumn('redirect');
            }
            if (Schema::hasColumn('tasks', 'url')) {
                $table->dropColumn('url');
            }
            if (Schema::hasColumn('tasks', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('tasks', 'type')) {
                $table->dropColumn('type');
            }
        });

        Schema::table('tasks', function (Blueprint $table) {
            // Restore old columns (simplified - you may need to adjust)
            if (!Schema::hasColumn('tasks', 'tasks_type')) {
                $table->string('tasks_type')->after('description');
            }
            if (!Schema::hasColumn('tasks', 'task_step')) {
                $table->string('task_step')->default('pending')->after('tasks_type');
            }
            if (!Schema::hasColumn('tasks', 'type_id')) {
                $table->foreignId('type_id')->constrained('types')->onDelete('cascade')->after('task_step');
            }
            if (!Schema::hasColumn('tasks', 'department_id')) {
                $table->foreignId('department_id')->constrained('departments')->onDelete('cascade')->after('type_id');
            }
            if (!Schema::hasColumn('tasks', 'category_id')) {
                $table->foreignId('category_id')->constrained('categories')->onDelete('cascade')->after('department_id');
            }
            if (!Schema::hasColumn('tasks', 'justif_type')) {
                $table->string('justif_type')->nullable()->after('category_id');
            }
            if (!Schema::hasColumn('tasks', 'duration')) {
                $table->integer('duration')->nullable()->after('justif_type');
            }
            if (!Schema::hasColumn('tasks', 'created_by')) {
                $table->foreignId('created_by')->constrained('users')->onDelete('cascade')->after('period_type');
            }
        });
    }
};
