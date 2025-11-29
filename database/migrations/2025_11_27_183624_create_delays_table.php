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
        if (!Schema::hasTable('delays')) {
            Schema::create('delays', function (Blueprint $table) {
                $table->id();
                $table->string('user_id', 255);
                $table->string('task_id', 255);
                $table->integer('count')->default(1);
                $table->timestamp('delay_until')->nullable(); // When the delay expires (6 minutes from creation)
                $table->timestamps();
                
                // Indexes for faster queries
                $table->index(['user_id', 'task_id']);
                $table->index('delay_until');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delays');
    }
};
