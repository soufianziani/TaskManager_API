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
        // Table `types` was renamed to `task_name` in the database.
        Schema::table('task_name', function (Blueprint $table) {
            // Add category_id if it doesn't exist yet
            if (!Schema::hasColumn('task_name', 'category_id')) {
                $table->unsignedBigInteger('category_id')
                    ->nullable()
                    ->after('id');

                $table->foreign('category_id')
                    ->references('id')
                    ->on('categories')
                    ->onDelete('cascade');
            }

            // Drop old department_id foreign key/column if still present
            if (Schema::hasColumn('task_name', 'department_id')) {
                try {
                    $table->dropForeign(['department_id']);
                } catch (\Throwable $e) {
                    // Ignore if foreign key name differs or already dropped
                }
                $table->dropColumn('department_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_name', function (Blueprint $table) {
            // Recreate department_id (without restoring data)
            if (!Schema::hasColumn('task_name', 'department_id')) {
                $table->unsignedBigInteger('department_id')
                    ->nullable()
                    ->after('id');

                $table->foreign('department_id')
                    ->references('id')
                    ->on('departments')
                    ->onDelete('cascade');
            }

            // Drop category_id if present
            if (Schema::hasColumn('task_name', 'category_id')) {
                try {
                    $table->dropForeign(['category_id']);
                } catch (\Throwable $e) {
                    // Ignore if FK already removed
                }
                $table->dropColumn('category_id');
            }
        });
    }
};



