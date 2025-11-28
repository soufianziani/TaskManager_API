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
        Schema::table('delays', function (Blueprint $table) {
            // Drop old columns
            $table->dropColumn(['count', 'delay_until']);
            
            // Add new columns
            $table->time('rest_time')->nullable()->after('task_id');
            $table->integer('rest_max')->default(0)->after('rest_time');
        });
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
