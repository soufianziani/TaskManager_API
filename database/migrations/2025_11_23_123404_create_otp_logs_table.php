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
        Schema::create('otp_logs', function (Blueprint $table) {
            $table->id();
            $table->string('verification_code', 4); // 4-digit OTP
            $table->string('phone_number');
            $table->enum('status', ['pending', 'sent', 'failed', 'confirmed', 'expired'])->default('pending');
            $table->string('provider'); // 'whatsapp' or 'sms'
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('purpose')->nullable(); // 'login', 'register', 'password_reset', 'phone_update'
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            
            $table->index('phone_number');
            $table->index('verification_code');
            $table->index(['phone_number', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_logs');
    }
};
