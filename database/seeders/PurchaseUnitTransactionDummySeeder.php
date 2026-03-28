<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PurchaseUnitTransactionDummySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // call unit transaction purchase complete seeder
        $this->call(PurchaseUnitTransactionCompleteSeeder::class);
        
        // call unit transaction purchase draft seeder
        $this->call(PurchaseUnitTransactionDraftSeeder::class);
    }
}
