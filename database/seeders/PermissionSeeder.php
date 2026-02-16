<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'master-data:create',
            'master-data:view',
            'master-data:edit',
            'master-data:edit',
            'master-data:delete',

            'transaction:create',
            'transaction:view',
            'transaction:edit',
            'transaction:delete',

            'warehouse:create',
            'warehouse:view',
            'warehouse:edit',
            'warehouse:delete',

            'finance:create',
            'finance:view',
            'finance:edit',
            'finance:delete',

            'report:view',
            'report:delete',
            'report:export',

            'user:create',
            'user:view',
            'user:edit',
            'user:delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);

        $admin->syncPermissions(Permission::all());

    }
}
