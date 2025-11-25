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
        Schema::create('refuse', function (Blueprint $table) {
            $table->id();
            $table->text('description');
            $table->string('task', 255); // Task ID
            $table->integer('created_by'); // User ID who created the refusal
            $table->timestamps();
            
            // Add indexes
            $table->index('task');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refuse');
    }
};

