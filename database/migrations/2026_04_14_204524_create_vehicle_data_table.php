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
        Schema::create('vehicle_datas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('dealer_id')->constrained('persons')->onDelete('cascade');
            $table->foreignId('region_id')->constrained('regions')->onDelete('cascade');
            $table->string('invoice_number')->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('invoice_receive_date')->nullable();
            $table->enum('vehicle_type', ["r2", "r3", "r4"])->nullable();
            
            // customer data
            $table->string('ktp_number')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('occupation')->nullable();
            $table->string('stnk_name')->nullable();
            $table->string('stnk_address')->nullable();
            $table->string('village')->nullable();
            $table->string('district')->nullable();
            $table->string('sub_village')->nullable();
            $table->string('sub_district')->nullable();
            $table->string('regency')->nullable();
            $table->string('postal_code')->nullable();

            // vehicle data
            $table->string('motorcycle_brand')->nullable();
            $table->string('motorcycle_type')->nullable();
            $table->string('motorcycle_category')->nullable();
            $table->string('motorcycle_model')->nullable();
            $table->integer('manufacture_year')->nullable();
            $table->integer('engine_capacity')->nullable();
            $table->string('color')->nullable();
            $table->bigInteger('price')->nullable();
            $table->string('chassis_number')->nullable(false)->unique();
            $table->string('machine_number')->nullable(false)->unique();
            $table->string('form_ab')->nullable();
            $table->string('pib')->nullable();
            $table->string('tpt_number')->nullable();
            $table->string('sut_number')->nullable();
            $table->string('srut_number')->nullable();
            $table->string('fuel_type')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_datas');
    }
};
