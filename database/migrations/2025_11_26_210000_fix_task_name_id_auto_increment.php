<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensure task_name.id is an auto-incrementing primary key.
     */
    public function up(): void
    {
        if (Schema::hasTable('task_name') && Schema::hasColumn('task_name', 'id')) {
            // Make sure the id column is BIGINT UNSIGNED AUTO_INCREMENT
            // Don't specify PRIMARY KEY as it already exists
            DB::statement('ALTER TABLE `task_name` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        }
    }

    /**
     * Reverse the migrations.
     *
     * We won't try to revert the auto_increment change as it is safe and required.
     */
    public function down(): void
    {
        // No-op
    }
};


