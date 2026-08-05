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
            ['name' => 'master-data:create', 'description' => 'Membuat data master baru'],
            ['name' => 'master-data:view', 'description' => 'Melihat detail data master'],
            ['name' => 'master-data:edit', 'description' => 'Mengubah data master yang sudah ada'],
            ['name' => 'master-data:list', 'description' => 'Menampilkan daftar data master'],
            ['name' => 'master-data:delete', 'description' => 'Menghapus data master'],
            ['name' => 'master-data:read', 'description' => 'Membaca List data master'],

            ['name' => 'transaction:create', 'description' => 'Membuat transaksi baru'],
            ['name' => 'transaction:list', 'description' => 'Menampilkan daftar transaksi'],
            ['name' => 'transaction:view', 'description' => 'Melihat detail transaksi'],
            ['name' => 'transaction:edit', 'description' => 'Mengubah transaksi yang sudah ada'],
            ['name' => 'transaction:delete', 'description' => 'Menghapus transaksi'],

            ['name' => 'warehouse:create', 'description' => 'Membuat data gudang baru'],
            ['name' => 'warehouse:list', 'description' => 'Menampilkan daftar gudang'],
            ['name' => 'warehouse:view', 'description' => 'Melihat detail gudang'],
            ['name' => 'warehouse:edit', 'description' => 'Mengubah data gudang'],
            ['name' => 'warehouse:delete', 'description' => 'Menghapus data gudang'],
            ['name' => 'warehouse:read', 'description' => 'Membaca list data gudang'],
            ['name' => 'warehouse:activity', 'description' => 'Mengubah aktivitas data gudang'],

            ['name' => 'finance:create', 'description' => 'Membuat data keuangan baru'],
            ['name' => 'finance:list', 'description' => 'Menampilkan daftar keuangan'],
            ['name' => 'finance:view', 'description' => 'Melihat detail keuangan'],
            ['name' => 'finance:edit', 'description' => 'Mengubah data keuangan'],
            ['name' => 'finance:delete', 'description' => 'Menghapus data keuangan'],

            ['name' => 'report:list', 'description' => 'Menampilkan daftar laporan'],
            ['name' => 'report:view', 'description' => 'Melihat detail laporan'],
            ['name' => 'report:delete', 'description' => 'Menghapus laporan'],
            ['name' => 'report:export', 'description' => 'Mengekspor laporan ke format file'],

            ['name' => 'user:list', 'description' => 'Menampilkan daftar pengguna'],
            ['name' => 'user:create', 'description' => 'Membuat pengguna baru'],
            ['name' => 'user:view', 'description' => 'Melihat detail pengguna'],
            ['name' => 'user:edit', 'description' => 'Mengubah data pengguna'],
            ['name' => 'user:delete', 'description' => 'Menghapus pengguna'],

            ['name' => 'role:create', 'description' => 'Membuat peran baru'],
            ['name' => 'role:view', 'description' => 'Melihat detail peran'],
            ['name' => 'role:list', 'description' => 'Menampilkan daftar peran'],
            ['name' => 'role:edit', 'description' => 'Mengubah data peran'],
            ['name' => 'role:delete', 'description' => 'Menghapus peran'],
            ['name' => 'role:assign-permission', 'description' => 'Menetapkan izin ke peran'],

            ['name' => 'permission:view', 'description' => 'Melihat detail izin'],
            ['name' => 'permission:list', 'description' => 'Menampilkan daftar izin'],

            ['name' => 'settings:create', 'description' => 'Membuat pengaturan baru'],
            ['name' => 'settings:list', 'description' => 'Menampilkan daftar pengaturan'],
            ['name' => 'settings:view', 'description' => 'Melihat detail pengaturan'],
            ['name' => 'settings:edit', 'description' => 'Mengubah data pengaturan'],
            ['name' => 'settings:delete', 'description' => 'Menghapus pengaturan'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                ['description' => $permission['description']]
            );
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);

        $admin->syncPermissions(Permission::all());
    }
}
