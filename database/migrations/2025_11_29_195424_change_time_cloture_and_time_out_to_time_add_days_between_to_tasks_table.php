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
        // First, clean up existing invalid data
        \DB::table('tasks')->whereNotNull('time_cloture')->update([
            'time_cloture' => null
        ]);
        \DB::table('tasks')->whereNotNull('time_out')->update([
            'time_out' => null
        ]);
        
        Schema::table('tasks', function (Blueprint $table) {
            // Change time_cloture from mediumtext to time
            $table->time('time_cloture')->nullable()->change();
            
            // Change time_out from mediumtext to time
            $table->time('time_out')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Revert time_cloture back to mediumtext
            $table->mediumText('time_cloture')->nullable()->change();
            
            // Revert time_out back to mediumtext
            $table->mediumText('time_out')->nullable()->change();
        });
    }
};
