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
                if (!Schema::hasColumn('notification_timeouts', 'read')) {
                    $table->integer('read')->default(0)->after('repeat_count');
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
                if (Schema::hasColumn('notification_timeouts', 'read')) {
                    $table->dropColumn('read');
                }
            });
        }
    }
};

