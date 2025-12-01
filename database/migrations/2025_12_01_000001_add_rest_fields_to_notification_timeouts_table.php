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
        if (Schema::hasTable('notification_timeouts')) {
            Schema::table('notification_timeouts', function (Blueprint $table) {
                if (!Schema::hasColumn('notification_timeouts', 'rest_max')) {
                    $table->integer('rest_max')->default(0)->after('next');
                }
                if (!Schema::hasColumn('notification_timeouts', 'repeat_count')) {
                    $table->integer('repeat_count')->default(0)->after('rest_max');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('notification_timeouts')) {
            Schema::table('notification_timeouts', function (Blueprint $table) {
                if (Schema::hasColumn('notification_timeouts', 'repeat_count')) {
                    $table->dropColumn('repeat_count');
                }
                if (Schema::hasColumn('notification_timeouts', 'rest_max')) {
                    $table->dropColumn('rest_max');
                }
            });
        }
    }
};


