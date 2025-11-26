<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateSuperAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create-super-admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a super admin user with all permissions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Creating Super Admin User...');
        $this->newLine();

        // Get user details
        $name = $this->ask('Enter name');
        $email = $this->ask('Enter email');
        $password = $this->secret('Enter password');
        $passwordConfirmation = $this->secret('Confirm password');

        // Validate input
        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            $this->error('Validation failed:');
            foreach ($validator->errors()->all() as $error) {
                $this->error('  - ' . $error);
            }
            return 1;
        }

        // Check if email already exists
        if (User::where('email', $email)->exists()) {
            $this->error('User with this email already exists!');
            return 1;
        }

        // Create or get super admin role (using api guard for API project)
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'super_admin', 'guard_name' => 'api'],
            ['guard_name' => 'api']
        );

        $this->info('Super Admin role created/retrieved.');

        // Create user with super_admin type
        $user = User::create([
            'first_name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'type' => 'super_admin',
            'is_active' => true,
        ]);

        $this->info('User created successfully.');

        // Assign super admin role
        $user->assignRole($superAdminRole);

        $this->info('Super Admin role assigned to user.');

        // Ensure default permissions exist
        $this->info('Ensuring default permissions exist...');
        $defaultPermissions = config('default_permissions.default_permissions', []);
        foreach ($defaultPermissions as $permissionName) {
            Permission::firstOrCreate(
                ['name' => $permissionName, 'guard_name' => 'api'],
                ['guard_name' => 'api']
            );
        }
        $this->info('Default permissions ensured.');

        // Give all permissions to super admin role (for api guard)
        $permissions = Permission::where('guard_name', 'api')->get();
        if ($permissions->count() > 0) {
            $superAdminRole->syncPermissions($permissions);
            $this->info('All existing permissions (' . $permissions->count() . ') granted to Super Admin role.');
        } else {
            $this->warn('No permissions found. You can create permissions later.');
        }

        $this->newLine();
        $this->info('✅ Super Admin created successfully!');
        $this->table(
            ['Field', 'Value'],
            [
                ['ID', $user->id],
                ['Name', $user->first_name],
                ['Email', $user->email],
                ['Type', $user->type],
                ['Role', 'super_admin'],
            ]
        );

        return 0;
    }
}
