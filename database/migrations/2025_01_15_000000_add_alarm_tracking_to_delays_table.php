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
            if (!Schema::hasColumn('delays', 'next_alarm_at')) {
                $table->timestamp('next_alarm_at')->nullable()->after('rest_max');
            }
            if (!Schema::hasColumn('delays', 'alarm_count')) {
                $table->integer('alarm_count')->default(0)->after('next_alarm_at');
            }
            if (!Schema::hasColumn('delays', 'last_alarm_at')) {
                $table->timestamp('last_alarm_at')->nullable()->after('alarm_count');
            }
            
            // Add index for faster queries
            $table->index('next_alarm_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delays', function (Blueprint $table) {
            $table->dropColumn(['next_alarm_at', 'alarm_count', 'last_alarm_at']);
        });
    }
};


