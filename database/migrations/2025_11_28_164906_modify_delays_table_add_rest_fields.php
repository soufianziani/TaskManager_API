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
        if (Schema::hasTable('delays')) {
            Schema::table('delays', function (Blueprint $table) {
                // Drop old columns if they exist
                if (Schema::hasColumn('delays', 'count')) {
                    $table->dropColumn('count');
                }
                if (Schema::hasColumn('delays', 'delay_until')) {
                    $table->dropColumn('delay_until');
                }
                
                // Add new columns if they don't exist
                if (!Schema::hasColumn('delays', 'rest_time')) {
                    $table->time('rest_time')->nullable()->after('task_id');
                }
                if (!Schema::hasColumn('delays', 'rest_max')) {
                    $table->integer('rest_max')->default(0)->after('rest_time');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delays', function (Blueprint $table) {
            // Drop new columns
            $table->dropColumn(['rest_time', 'rest_max']);
            
            // Restore old columns
            $table->integer('count')->default(1)->after('task_id');
            $table->timestamp('delay_until')->nullable()->after('count');
        });
    }
};
