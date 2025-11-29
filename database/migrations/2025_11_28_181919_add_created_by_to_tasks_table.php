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
            // Only add the column if it doesn't exist
            if (!Schema::hasColumn('tasks', 'created_by')) {
                // Check if rest_max column exists, if yes add after it, otherwise add at end
                if (Schema::hasColumn('tasks', 'rest_max')) {
                    $table->string('created_by', 255)->nullable()->after('rest_max');
                } else {
                    $table->string('created_by', 255)->nullable();
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('created_by');
        });
    }
};
