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
        Schema::table('types', function (Blueprint $table) {
            if (!Schema::hasColumn('types', 'icon')) {
                $table->string('icon')->nullable()->after('name');
            }

            if (!Schema::hasColumn('types', 'color')) {
                $table->string('color', 50)->nullable()->after('icon');
            }

            if (!Schema::hasColumn('types', 'permission')) {
                $table->string('permission')->nullable()->after('description');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('types', function (Blueprint $table) {
            if (Schema::hasColumn('types', 'permission')) {
                $table->dropColumn('permission');
            }

            if (Schema::hasColumn('types', 'color')) {
                $table->dropColumn('color');
            }

            if (Schema::hasColumn('types', 'icon')) {
                $table->dropColumn('icon');
            }
        });
    }
};
