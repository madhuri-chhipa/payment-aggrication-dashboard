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
        Schema::create('user_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->unique();
            $table->enum('payout_status', ['A', 'B'])
                ->default('B')
                ->comment('A = Active, B = Blocked');
            $table->decimal('minimum_transaction', 15, 2)
                ->default(100.00);
            $table->decimal('maximum_transaction', 15, 2)
                ->default(49999.00);
            $table->enum('ftransaction', ['A', 'B', 'C'])
                ->default('B')
                ->comment('A = Allow, B = Block, C = Conditional');
            $table->enum('payin_status', ['A', 'B'])
                ->default('B')
                ->comment('A = Active, B = Blocked');
            $table->decimal('payin_minimum_transaction', 15, 2)
                ->default(100.00);
            $table->decimal('payin_maximum_transaction', 15, 2)
                ->default(49999.00);
            $table->enum('ptransaction', ['A', 'B', 'C'])
                ->default('B')
                ->comment('A = Allow, B = Block, C = Conditional');
            $table->decimal('virtual_charges', 15, 2)
                ->default(1.00);
            $table->enum('virtual_type', ['percentage', 'flat_rate'])
                ->default('percentage');
            $table->decimal('pslab_1000', 15, 2)->default(5.00);
            $table->decimal('pslab_25000', 15, 2)->default(7.00);
            $table->decimal('pslab_200000', 15, 2)->default(15.00);
            $table->decimal('pslab_percentage', 15, 2)->default(7.00);
            $table->decimal('payin_charges', 15, 2)
                ->default(2.00);
            $table->string('active_payout_api', 255)
                ->nullable();
            $table->string('active_payin_api', 255)
                ->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_services');
    }
};