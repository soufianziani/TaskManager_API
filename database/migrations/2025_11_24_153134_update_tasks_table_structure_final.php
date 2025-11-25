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
            // Add justif_file column if it doesn't exist
            if (!Schema::hasColumn('tasks', 'justif_file')) {
                $table->string('justif_file', 255)->nullable()->after('file');
            }
            
            // Remove files_id column if it exists (not in new structure)
            if (Schema::hasColumn('tasks', 'files_id')) {
                $table->dropColumn('files_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'justif_file')) {
                $table->dropColumn('justif_file');
            }
            
            // Re-add files_id if needed
            if (!Schema::hasColumn('tasks', 'files_id')) {
                $table->string('files_id', 255)->nullable()->after('file');
            }
        });
    }
};
