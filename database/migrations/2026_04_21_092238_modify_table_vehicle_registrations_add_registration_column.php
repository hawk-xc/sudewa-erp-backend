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
        Schema::table('vehicle_registrations', function (Blueprint $table) {
            $table->date('customer_delivery_date')->nullable()->after('is_already_processed');
            
            // BPKB
            $table->string('bpkb_number')->nullable()->after('customer_delivery_date');
            $table->date('bpkb_registration_date')->nullable()->after('bpkb_number');
            $table->date('bpkb_received_date')->nullable()->after('bpkb_registration_date');
            $table->boolean('bpkb_physical_status')->default(false)->after('bpkb_received_date');

            // STNK
            $table->date('stnk_registration_date')->nullable()->after('bpkb_physical_status');
            $table->date('stnk_received_date')->nullable()->after('stnk_registration_date');
            $table->boolean('stnk_physical_status')->default(false)->after('stnk_received_date');

            // SKPD
            $table->date('skpd_payment_date')->nullable()->after('stnk_physical_status');
            $table->date('skpd_received_date')->nullable()->after('skpd_payment_date');
            $table->boolean('skpd_physical_status')->default(false)->after('skpd_received_date');

            // TNKB
            $table->date('tnkb_received_date')->nullable()->after('skpd_physical_status');
            $table->string('tnkb_number')->nullable()->after('tnkb_received_date');
            $table->boolean('tnkb_physical_status')->default(false)->after('tnkb_number');

            // Administration Costs
            $table->decimal('stck_fee', 15, 2)->default(0)->after('tnkb_physical_status');
            $table->decimal('bbn_registration_fee', 15, 2)->default(0)->after('stck_fee');
            $table->decimal('notice_fee', 15, 2)->default(0)->after('bbn_registration_fee');
            $table->decimal('pmi_fee', 15, 2)->default(0)->after('notice_fee');

            $table->decimal('physical_check_fee', 15, 2)->default(0)->after('pmi_fee');
            $table->decimal('nik_validation_fee', 15, 2)->default(0)->after('physical_check_fee');
            $table->decimal('garwil_fee', 15, 2)->default(0)->after('nik_validation_fee');
            $table->decimal('built_up_fee', 15, 2)->default(0)->after('garwil_fee');

            $table->decimal('acceleration_fee', 15, 2)->default(0)->after('built_up_fee');
            $table->decimal('plate_recommendation_fee', 15, 2)->default(0)->after('acceleration_fee');
            $table->decimal('service_fee', 15, 2)->default(0)->after('plate_recommendation_fee');
            $table->decimal('skpd_fee', 15, 2)->default(0)->after('service_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_registrations', function (Blueprint $table) {
            $table->dropColumn([
                'bpkb_number', 'bpkb_registration_date', 'bpkb_received_date', 'bpkb_physical_status',
                'stnk_registration_date', 'stnk_received_date', 'stnk_physical_status',
                'skpd_payment_date', 'skpd_received_date', 'skpd_physical_status',
                'tnkb_received_date', 'tnkb_number', 'tnkb_physical_status',
                'stck_fee', 'bbn_registration_fee', 'notice_fee', 'pmi_fee',
                'physical_check_fee', 'nik_validation_fee', 'garwil_fee', 'built_up_fee',
                'acceleration_fee', 'plate_recommendation_fee', 'service_fee', 'skpd_fee'
            ]);
        });
    }
};
