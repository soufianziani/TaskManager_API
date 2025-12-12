<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class TestUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure default permissions exist
        $defaultPermissions = config('default_permissions.default_permissions', []);
        foreach ($defaultPermissions as $permissionName) {
            Permission::firstOrCreate(
                ['name' => $permissionName, 'guard_name' => 'api'],
                ['guard_name' => 'api']
            );
        }

        // Create or get roles
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'api'],
            ['guard_name' => 'api']
        );

        $userRole = Role::firstOrCreate(
            ['name' => 'user', 'guard_name' => 'api'],
            ['guard_name' => 'api']
        );

        $superAdminRole = Role::firstOrCreate(
            ['name' => 'super_admin', 'guard_name' => 'api'],
            ['guard_name' => 'api']
        );

        // Assign all permissions to admin role
        $permissions = Permission::where('guard_name', 'api')->get();
        if ($permissions->count() > 0) {
            $adminRole->syncPermissions($permissions);
        }

        // Create Test Admin User
        $testAdmin = User::firstOrCreate(
            ['user_name' => 'Test_admin'],
            [
                'first_name' => 'Test',
                'last_name' => 'Admin',
                'email' => 'test_admin@example.com',
                'password' => Hash::make('123456789'),
                'type' => 'admin',
                'is_active' => true,
                'phone_number' => '+212612345678',
            ]
        );

        // Assign admin role to Test Admin
        if (!$testAdmin->hasRole($adminRole)) {
            $testAdmin->assignRole($adminRole);
        }

        $this->command->info('✅ Test Admin user created/updated: Test_admin (password: 123456789)');

        // Create Test User
        $testUser = User::firstOrCreate(
            ['user_name' => 'Test_user'],
            [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test_user@example.com',
                'password' => Hash::make('123456789'),
                'type' => 'user',
                'is_active' => true,
                'phone_number' => '+212612345679',
            ]
        );

        // Assign user role to Test User
        if (!$testUser->hasRole($userRole)) {
            $testUser->assignRole($userRole);
        }

        $this->command->info('✅ Test User created/updated: Test_user (password: 123456789)');

        // Create Test Super Admin (optional)
        $testSuperAdmin = User::firstOrCreate(
            ['user_name' => 'Test_super_admin'],
            [
                'first_name' => 'Test',
                'last_name' => 'Super Admin',
                'email' => 'test_super_admin@example.com',
                'password' => Hash::make('123456789'),
                'type' => 'super_admin',
                'is_active' => true,
                'phone_number' => '+212612345680',
            ]
        );

        // Assign super admin role to Test Super Admin
        if (!$testSuperAdmin->hasRole($superAdminRole)) {
            $testSuperAdmin->assignRole($superAdminRole);
        }

        // Give all permissions to super admin role
        if ($permissions->count() > 0) {
            $superAdminRole->syncPermissions($permissions);
        }

        $this->command->info('✅ Test Super Admin user created/updated: Test_super_admin (password: 123456789)');

        $this->command->info('');
        $this->command->info('📋 Summary:');
        $this->command->table(
            ['Username', 'Email', 'Type', 'Password'],
            [
                ['Test_admin', 'test_admin@example.com', 'admin', '123456789'],
                ['Test_user', 'test_user@example.com', 'user', '123456789'],
                ['Test_super_admin', 'test_super_admin@example.com', 'super_admin', '123456789'],
            ]
        );
    }
}

