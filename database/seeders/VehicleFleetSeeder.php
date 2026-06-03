<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\VehicleFleet;
use Faker\Factory as Faker;
use Illuminate\Support\Str;

class VehicleFleetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('id_ID');
        $types = ['towing', 'cdd', 'fuso'];

        for ($i = 0; $i < 50; $i++) {
            $prefix = $faker->randomElement(['B', 'D', 'F', 'T', 'H', 'AB', 'L', 'N', 'S']);
            $number = $faker->numberBetween(1000, 9999);
            $suffix = strtoupper($faker->lexify('???'));
            $plate = "{$prefix} {$number} {$suffix}";

            VehicleFleet::create([
                'registration_number' => $plate,
                'type' => $faker->randomElement($types),
                'machine_number' => 'MCH' . strtoupper($faker->bothify('?##?###?#')),
                'chassis_number' => 'CHS' . strtoupper($faker->bothify('?##?###?#')),
                'stnk_age' => $faker->dateTimeBetween('-1 year', '+5 years')->format('Y-m-d'),
                'kir_age' => $faker->dateTimeBetween('-6 months', '+1 year')->format('Y-m-d'),
                'stnk_number' => $faker->numerify('###########'),
                'kir_book' => 'KIR-' . $faker->numerify('########'),
            ]);
        }
    }
}
