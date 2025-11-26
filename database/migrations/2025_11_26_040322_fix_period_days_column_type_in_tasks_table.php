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
        if (Schema::hasColumn('tasks', 'period_days')) {
            // Use raw SQL to change column type (more reliable for type changes)
            DB::statement('ALTER TABLE tasks MODIFY COLUMN period_days TEXT NULL');
        } else {
            // If column doesn't exist, create it
            Schema::table('tasks', function (Blueprint $table) {
                $table->text('period_days')->nullable()->after('period_end');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Revert back to integer (though this might cause data loss)
            // Note: This rollback may not work if there's existing JSON data
            $table->integer('period_days')->nullable()->change();
        });
    }
};
