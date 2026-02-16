<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('users')->delete();

        $user = User::create([
            'username' => 'admin',
            'firstname' => 'Admin',
            'lastname' => 'true',
            'name' => 'Admin',
            'email' => 'admin@deraly.id',
            'password' => bcrypt('rootme'),
            'secure_password' => encrypt('rootme'),
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);

        $adminRole->syncPermissions(Permission::all());

        $user->assignRole($adminRole);
    }
}
