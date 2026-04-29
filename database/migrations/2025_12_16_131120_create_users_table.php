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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('uid', 4)->unique();
            $table->string('company_name', 150)->nullable();
            $table->string('email')->unique();
            $table->string('mobile_number', 15)->unique();
            $table->string('password');
            $table->enum('active', ['A', 'B'])
                ->default('A')
                ->comment('A = Active, B = Blocked');
            $table->string('login_otp')->nullable();
            $table->timestamp('login_otp_expires_at')->nullable();
            $table->string('reset_otp')->nullable();
            $table->timestamp('reset_otp_expires_at')->nullable();
            $table->decimal('payout_balance', 15, 2)->default(0.00);
            $table->decimal('payin_balance', 15, 2)->default(0.00);
            $table->decimal('reserve_balance', 15, 2)->default(0.00);
            $table->decimal('freeze_balance', 15, 2)->default(0.00);
            $table->decimal('virtual_balance', 15, 2)->default(0.00);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
