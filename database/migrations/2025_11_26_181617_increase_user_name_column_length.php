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
        Schema::table('users', function (Blueprint $table) {
            // Drop the unique index if it exists (using try-catch for safety)
            try {
                $table->dropUnique('users_user_name_unique');
            } catch (\Exception $e) {
                // Index might not exist or have different name, continue
            }
        });
        
        // Change the column length (without unique, we'll add it back)
        Schema::table('users', function (Blueprint $table) {
            $table->string('user_name', 50)->nullable()->change();
        });
        
        // Re-add the unique index
        Schema::table('users', function (Blueprint $table) {
            $table->unique('user_name', 'users_user_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop the unique index
            try {
                $table->dropUnique('users_user_name_unique');
            } catch (\Exception $e) {
                // Index might not exist, continue
            }
        });
        
        // Change the column length back
        Schema::table('users', function (Blueprint $table) {
            $table->string('user_name', 4)->nullable()->change();
        });
        
        // Re-add the unique index
        Schema::table('users', function (Blueprint $table) {
            $table->unique('user_name', 'users_user_name_unique');
        });
    }
};
