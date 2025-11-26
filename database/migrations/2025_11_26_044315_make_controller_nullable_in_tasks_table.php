<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'controller')) {
                // Use raw SQL to modify the column to be nullable
                DB::statement('ALTER TABLE tasks MODIFY COLUMN controller VARCHAR(255) NULL');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'controller')) {
                // Revert to NOT NULL (if needed)
                DB::statement('ALTER TABLE tasks MODIFY COLUMN controller VARCHAR(255) NOT NULL');
            }
        });
    }
};
