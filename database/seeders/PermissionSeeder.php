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
            'master-data:list',
            'master-data:delete',

            'transaction:create',
            'transaction:list',
            'transaction:view',
            'transaction:edit',
            'transaction:delete',

            'warehouse:create',
            'warehouse:list',
            'warehouse:view',
            'warehouse:edit',
            'warehouse:delete',

            'finance:create',
            'finance:list',
            'finance:view',
            'finance:edit',
            'finance:delete',

            'report:list',
            'report:view',
            'report:delete',
            'report:export',

            'user:list',
            'user:create',
            'user:view',
            'user:edit',
            'user:delete',

            'role:create',
            'role:view',
            'role:list',
            'role:edit',
            'role:delete',
            'role:assign-permission',

            'permission:view',
            'permission:list'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);

        $admin->syncPermissions(Permission::all());

    }
}
