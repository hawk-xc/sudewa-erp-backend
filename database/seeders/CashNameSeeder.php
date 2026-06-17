<?php

namespace Database\Seeders;

use App\Models\Cash;
use Illuminate\Database\Seeder;

class CashNameSeeder extends Seeder
{
    /**
     * Run the database seeds to update cash_name column.
     */
    public function run(): void
    {
        $cashes = Cash::all();

        foreach ($cashes as $cash) {
            $parts = explode('_', $cash->code);
            $name = $parts[0];
            $currency = isset($parts[1]) ? strtoupper($parts[1]) : '';

            if (strtolower($name) === 'cash') {
                $formattedName = 'Cash';
            } else {
                $formattedName = strtoupper($name);
            }

            $cashName = $currency ? "{$formattedName} {$currency}" : $formattedName;

            $cash->update([
                'cash_name' => $cashName,
            ]);
        }
    }
}
