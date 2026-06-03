<?php

namespace Database\Seeders;

use App\Models\Tarif;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class TarifSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        $cities = [
            'Jakarta', 'Bandung', 'Surabaya', 'Semarang', 'Yogyakarta',
            'Medan', 'Palembang', 'Makassar', 'Denpasar', 'Balikpapan',
            'Samarinda', 'Manado', 'Ambon', 'Kupang', 'Mataram',
            'Pontianak', 'Banjarmasin', 'Pekanbaru', 'Batam', 'Padang',
            'Lampung', 'Jambi', 'Bengkulu', 'Banda Aceh', 'Tangerang',
            'Bekasi', 'Depok', 'Bogor', 'Cirebon', 'Solo', 'Malang',
            'Kediri', 'Jember', 'Banyuwangi', 'Cilegon', 'Serang'
        ];

        for ($i = 0; $i < 50; $i++) {
            $loadingIn = $faker->randomElement($cities);
            do {
                $loadingOut = $faker->randomElement($cities);
            } while ($loadingOut === $loadingIn);

            Tarif::create([
                'loading_in' => $loadingIn,
                'loading_out' => $loadingOut,
                'distance' => $faker->numberBetween(10, 800),
                'uj_towing' => $faker->numberBetween(500000, 3000000),
                'uj_cdd' => $faker->numberBetween(800000, 4000000),
                'uj_fuso' => $faker->numberBetween(1500000, 8000000),
                'inv_cdd' => $faker->numberBetween(1000000, 5000000),
                'inv_fuso' => $faker->numberBetween(2000000, 10000000),
                'is_active' => 1,
            ]);
        }
    }
}
