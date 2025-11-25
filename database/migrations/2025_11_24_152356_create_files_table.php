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
        if (!Schema::hasTable('files')) {
            Schema::create('files', function (Blueprint $table) {
                $table->id();
                $table->string('type'); // photo, video, pdf, url, etc.
                $table->string('file_for'); // justif or task
                $table->text('url'); // file path in backend
                $table->timestamps();
            });
        } else {
            // Add missing columns if they don't exist
            Schema::table('files', function (Blueprint $table) {
                if (!Schema::hasColumn('files', 'type')) {
                    $table->string('type')->after('id');
                }
                if (!Schema::hasColumn('files', 'file_for')) {
                    $table->string('file_for')->after('type');
                }
                if (!Schema::hasColumn('files', 'url')) {
                    $table->text('url')->after('file_for');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
