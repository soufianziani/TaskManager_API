<?php

namespace App\Helpers;

use Spatie\Permission\Models\Permission;

class PermissionHelper
{
    /**
     * Get default permissions array.
     */
    public static function getDefaultPermissions(): array
    {
        return config('default_permissions.default_permissions', []);
    }

    /**
     * Ensure default permissions exist in the database.
     * Creates them if they don't exist with the 'api' guard.
     */
    public static function ensureDefaultPermissionsExist(?string $guardName = 'api'): void
    {
        $defaultPermissions = self::getDefaultPermissions();
        
        foreach ($defaultPermissions as $permissionName) {
            Permission::firstOrCreate(
                ['name' => $permissionName, 'guard_name' => $guardName],
                ['guard_name' => $guardName]
            );
        }
    }

    /**
     * Get default permissions collection for a given guard.
     */
    public static function getDefaultPermissionsCollection(?string $guardName = 'api'): \Illuminate\Support\Collection
    {
        $defaultPermissions = self::getDefaultPermissions();
        return Permission::whereIn('name', $defaultPermissions)
            ->where('guard_name', $guardName)
            ->get();
    }
}

