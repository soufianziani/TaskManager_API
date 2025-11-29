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
            if (!Schema::hasColumn('tasks', 'rest_time')) {
                $table->time('rest_time')->nullable()->after('time_out');
            }
            if (!Schema::hasColumn('tasks', 'rest_max')) {
                $table->integer('rest_max')->default(0)->after('rest_time');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['rest_time', 'rest_max']);
        });
    }
};
