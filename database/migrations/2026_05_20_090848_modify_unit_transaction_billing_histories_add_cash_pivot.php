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
        if (Schema::hasTable('unit_transaction_billing_histories')) {
            Schema::table('unit_transaction_billing_histories', function (Blueprint $table) {
                if (Schema::hasColumn('unit_transaction_billing_histories', 'bca_payment_amount')) {
                    $table->dropColumn('bca_payment_amount');
                }
                if (Schema::hasColumn('unit_transaction_billing_histories', 'bca_payment_usd_amount')) {
                    $table->dropColumn('bca_payment_usd_amount');
                }
                if (Schema::hasColumn('unit_transaction_billing_histories', 'cash_payment_amount')) {
                    $table->dropColumn('cash_payment_amount');
                }
            });
        }

        if (!Schema::hasTable('cash_unit_transaction_billing_history')) {
            Schema::create('cash_unit_transaction_billing_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cash_id')->constrained('cashes')->onDelete('cascade');
                
                $table->unsignedBigInteger('unit_transaction_billing_history_id');
                $table->foreign('unit_transaction_billing_history_id', 'fk_cash_utbh_id')
                      ->references('id')->on('unit_transaction_billing_histories')
                      ->onDelete('cascade');
                
                $table->decimal('amount', 20, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('cash_unit_transaction_billing_history')) {
            Schema::dropIfExists('cash_unit_transaction_billing_history');
        }

        if (Schema::hasTable('unit_transaction_billing_histories')) {
            Schema::table('unit_transaction_billing_histories', function (Blueprint $table) {
                if (!Schema::hasColumn('unit_transaction_billing_histories', 'bca_payment_amount')) {
                    $table->decimal('bca_payment_amount', 20, 2)->nullable();
                }
                if (!Schema::hasColumn('unit_transaction_billing_histories', 'bca_payment_usd_amount')) {
                    $table->decimal('bca_payment_usd_amount', 20, 2)->nullable();
                }
                if (!Schema::hasColumn('unit_transaction_billing_histories', 'cash_payment_amount')) {
                    $table->decimal('cash_payment_amount', 20, 2)->nullable();
                }
            });
        }
    }
};
