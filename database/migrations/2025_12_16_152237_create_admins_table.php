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
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('email')->unique();
            $table->string('mobile_number', 15)->nullable()->unique();
            $table->string('password');
            $table->enum('admin_type', ['admin', 'accountant', 'employee'])
                ->default('employee')
                ->comment('admin = super admin');
            $table->enum('status', ['A', 'B'])
                ->default('A')
                ->comment('A = Active, B = Blocked');
            $table->string('login_otp')->nullable();
            $table->timestamp('login_otp_expires_at')->nullable();
            $table->string('reset_otp')->nullable();
            $table->timestamp('reset_otp_expires_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
