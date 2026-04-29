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
        Schema::create('company_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('name', 150);
            $table->enum('company_type', [
                'private_limited',
                'one_person_company',
                'limited_liability_partnership',
                'public_limited'
            ])->default('private_limited');
            $table->text('gst_no')->nullable();
            $table->longText('gst_image')->nullable();
            $table->text('address')->nullable();
            $table->text('cin')->nullable();
            $table->longText('cin_image')->nullable();
            $table->text('pan')->nullable();
            $table->longText('pan_image')->nullable();
            $table->text('udhyam_number')->nullable();
            $table->longText('udhyam_image')->nullable();
            $table->longText('moa_image')->nullable();
            $table->longText('br_image')->nullable();
            $table->string('director_name', 100);
            $table->string('director_email')->index();
            $table->string('director_mobile', 15)->unique();
            $table->text('director_aadhar_no');
            $table->longText('director_aadhar_image');
            $table->text('director_pan_no');
            $table->longText('director_pan_image');
            $table->text('docs')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_details');
    }
};
