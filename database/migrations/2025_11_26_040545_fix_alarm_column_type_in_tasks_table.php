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
        // Check if column exists and change its type
        if (Schema::hasColumn('tasks', 'alarm')) {
            // Use raw SQL to change column type from datetime to TEXT (more reliable for type changes)
            DB::statement('ALTER TABLE tasks MODIFY COLUMN alarm TEXT NULL');
        } else {
            // If column doesn't exist, create it
            Schema::table('tasks', function (Blueprint $table) {
                $table->text('alarm')->nullable()->after('period_days');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: Reverting to datetime might cause data loss if JSON data exists
        if (Schema::hasColumn('tasks', 'alarm')) {
            DB::statement('ALTER TABLE tasks MODIFY COLUMN alarm DATETIME NULL');
        }
    }
};
