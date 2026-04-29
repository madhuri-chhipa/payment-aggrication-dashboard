<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('admin_login_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('otp')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('latitude')->nullable();   // encrypted
            $table->text('longitude')->nullable();  // encrypted
            $table->timestamp('logged_at')->nullable();
            $table->foreign('admin_id')
                ->references('id')
                ->on('admins')
                ->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_login_logs');
    }
};
