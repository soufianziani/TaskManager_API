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
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('phone_number');
            }
            if (!Schema::hasColumn('users', 'new_phone')) {
                $table->string('new_phone')->nullable()->after('phone'); // For phone change verification
            }
            if (!Schema::hasColumn('users', 'is_number_validated')) {
                $table->boolean('is_number_validated')->default(false)->after('new_phone');
            }
            if (!Schema::hasColumn('users', 'validated_by')) {
                $table->string('validated_by')->nullable()->after('is_number_validated'); // 'whatsapp' or 'sms'
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'new_phone', 'is_number_validated', 'validated_by']);
        });
    }
};
