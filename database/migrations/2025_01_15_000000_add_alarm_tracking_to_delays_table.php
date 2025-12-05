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
        // Check if delays table exists before trying to alter it
        if (!Schema::hasTable('delays')) {
            // Table doesn't exist yet, skip this migration
            // It will be handled when the delays table is created with these columns
            return;
        }

        Schema::table('delays', function (Blueprint $table) {
            // Determine the position for new columns
            $afterColumn = 'task_id'; // Default position
            if (Schema::hasColumn('delays', 'rest_max')) {
                $afterColumn = 'rest_max';
            } elseif (Schema::hasColumn('delays', 'rest_time')) {
                $afterColumn = 'rest_time';
            }

            if (!Schema::hasColumn('delays', 'next_alarm_at')) {
                if ($afterColumn === 'rest_max') {
                    $table->timestamp('next_alarm_at')->nullable()->after('rest_max');
                } else {
                    $table->timestamp('next_alarm_at')->nullable();
                }
            }
            
            if (!Schema::hasColumn('delays', 'alarm_count')) {
                if (Schema::hasColumn('delays', 'next_alarm_at')) {
                    $table->integer('alarm_count')->default(0)->after('next_alarm_at');
                } else {
                    $table->integer('alarm_count')->default(0);
                }
            }
            
            if (!Schema::hasColumn('delays', 'last_alarm_at')) {
                if (Schema::hasColumn('delays', 'alarm_count')) {
                    $table->timestamp('last_alarm_at')->nullable()->after('alarm_count');
                } else {
                    $table->timestamp('last_alarm_at')->nullable();
                }
            }
        });

        // Add index separately to avoid issues if columns already exist
        if (Schema::hasTable('delays') && Schema::hasColumn('delays', 'next_alarm_at')) {
            try {
                Schema::table('delays', function (Blueprint $table) {
                    $table->index('next_alarm_at');
                });
            } catch (\Exception $e) {
                // Index might already exist, ignore error
            }
        }
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


