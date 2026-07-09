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
        Schema::create('cash_flows', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('unit_transaction_billing_id')->nullable()->constrained('unit_transaction_billings')->cascadeOnDelete();
            $table->string('code')->unique()->nullable(false);
            $table->date('date')->nullable(false);
            $table->text('note')->nullable();
            $table->decimal('debet', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->boolean('is_valid')->default(false);
            $table->timestamps();
        });
    }
 
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_flows');
    }
};
