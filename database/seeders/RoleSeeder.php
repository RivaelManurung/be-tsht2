<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Buat Roles
        $superadmin = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'api']);
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        $operator = Role::firstOrCreate(['name' => 'operator', 'guard_name' => 'api']);

        // Superadmin: dapat semua permission KECUALI create_transaction
        $excludedPermissions = ['create_transaction'];
        $permissions = Permission::whereNotIn('name', $excludedPermissions)->pluck('name')->toArray();
        $superadmin->syncPermissions($permissions);


        // permission untuk admin
        $admin->givePermissionTo([
            'create_barang',
            'update_barang',
            'view_barang',
            'delete_barang',
        ]);

        // permission untuk operator
        $operator->givePermissionTo([
            'view_barang',
            'create_transaction',
            'view_transaction'
        ]);
    }
}
