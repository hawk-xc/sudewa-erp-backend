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
        Schema::create('vehicle_document_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vehicle_document_id')->constrained('vehicle_documents')->onDelete('cascade');
            $table->foreignId('vendor_id')->constrained('persons')->onDelete('cascade');
            // Relation to VehicleData is inferred from model relationship though not in fillable
            $table->foreignId('vehicle_data_id')->nullable()->constrained('vehicle_datas')->onDelete('cascade');

            // BPKB
            $table->date('bpkb_registration_date')->nullable();
            $table->date('bpkb_received_date')->nullable();
            $table->boolean('bpkb_physical_status')->default(false);
            $table->string('bpkb_number')->nullable();

            // STNK
            $table->date('stnk_registration_date')->nullable();
            $table->date('stnk_received_date')->nullable();
            $table->boolean('stnk_physical_status')->default(false);

            // SKPD
            $table->date('skpd_payment_date')->nullable();
            $table->date('skpd_received_date')->nullable();
            $table->boolean('skpd_physical_status')->default(false);

            // TNKB
            $table->date('tnkb_received_date')->nullable();
            $table->string('tnkb_number')->nullable();
            $table->boolean('tnkb_physical_status')->default(false);

            // Administration Costs
            $table->decimal('stck_fee', 15, 2)->default(0);
            $table->decimal('bbn_registration_fee', 15, 2)->default(0);
            $table->decimal('notice_fee', 15, 2)->default(0);
            $table->decimal('pmi_fee', 15, 2)->default(0);
            $table->decimal('physical_check_fee', 15, 2)->default(0);
            $table->decimal('nik_validation_fee', 15, 2)->default(0);
            $table->decimal('garwil_fee', 15, 2)->default(0);
            $table->decimal('built_up_fee', 15, 2)->default(0);
            $table->decimal('acceleration_fee', 15, 2)->default(0);
            $table->decimal('plate_recommendation_fee', 15, 2)->default(0);
            $table->decimal('service_fee', 15, 2)->default(0);
            $table->decimal('skpd_fee', 15, 2)->default(0);

            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_document_items');
    }
};
