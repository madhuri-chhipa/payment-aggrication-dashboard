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
        Schema::create('user_api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->unique();
            $table->longText('client_key')->nullable();
            $table->longText('client_secret')->nullable();
            $table->text('payin_webhooks')->nullable();
            $table->text('payout_webhooks')->nullable();
            $table->text('ip')->nullable()
                ->comment('comma separated whitelisted ips');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_api_keys');
    }
};
