<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignUserRolesPermissionsRequest;
use App\Http\Requests\CreateCategoryRequest;
use App\Http\Requests\CreateDepartmentRequest;
use App\Http\Requests\CreatePermissionRequest;
use App\Http\Requests\CreateRoleRequest;
use App\Http\Requests\CreateTypeRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Http\Requests\UpdateTypeRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Requests\UpdatePermissionRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Helpers\PermissionHelper;
use App\Models\Category;
use App\Models\Department;
use App\Models\Type;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SuperAdminController extends Controller
{
    /**
     * Ensure default permissions exist in the database.
     * Creates them if they don't exist with the 'api' guard.
     */
    private function ensureDefaultPermissionsExist(): void
    {
        PermissionHelper::ensureDefaultPermissionsExist('api');
    }

    /**
     * Get default permissions for a given guard.
     */
    private function getDefaultPermissions(string $guardName = 'api'): \Illuminate\Support\Collection
    {
        return PermissionHelper::getDefaultPermissionsCollection($guardName);
    }

    /**
     * Create a new department.
     * Super admin or users with "admin" or "task config" permission can create departments.
     */
    public function createDepartment(CreateDepartmentRequest $request): JsonResponse
    {
        $user = $request->user();

        // Check if user has required permissions (super_admin bypasses automatically)
        if (!$user->hasPermissionWithSuperAdminBypass('task config') && !$user->hasPermissionWithSuperAdminBypass('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Super Admin or users with "admin" or "task config" permission required.',
                'data' => [
                    'user_type' => $user->type,
                ],
            ], 403);
        }

        $department = Department::create([
            'name' => $request->name,
            'description' => $request->description,
            'permission' => $request->permission,
            'is_active' => $request->is_active ?? true,
            'icon' => $request->icon,
            'color' => $request->color,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Department created successfully',
            'data' => $department,
        ], 201);
    }

    /**
     * Update an existing department.
     * Super admin or users with "admin" or "task config" permission can update departments.
     */
    public function updateDepartment(UpdateDepartmentRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Check if user has required permissions (super_admin bypasses automatically)
        if (!$user->hasPermissionWithSuperAdminBypass('task config') && !$user->hasPermissionWithSuperAdminBypass('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Super Admin or users with "admin" or "task config" permission required.',
                'data' => [
                    'user_type' => $user->type,
                ],
            ], 403);
        }

        $department = Department::findOrFail($id);

        // Update only provided fields
        if ($request->has('name')) {
            $department->name = $request->name;
        }
        if ($request->has('description')) {
            $department->description = $request->description;
        }
        if ($request->has('permission')) {
            $department->permission = $request->permission;
        }
        if ($request->has('is_active')) {
            $department->is_active = $request->is_active;
        }
        if ($request->has('icon')) {
            $department->icon = $request->icon;
        }
        if ($request->has('color')) {
            $department->color = $request->color;
        }

        $department->save();

        return response()->json([
            'success' => true,
            'message' => 'Department updated successfully',
            'data' => $department,
        ], 200);
    }

    /**
     * Delete an existing department.
     * Super admin or users with "admin" or "task config" permission can delete departments.
     */
    public function deleteDepartment(int $id): JsonResponse
    {
        $user = request()->user();

        // Check if user has required permissions (super_admin bypasses automatically)
        if (!$user->hasPermissionWithSuperAdminBypass('task config') && !$user->hasPermissionWithSuperAdminBypass('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Super Admin or users with "admin" or "task config" permission required.',
                'data' => [
                    'user_type' => $user->type,
                ],
            ], 403);
        }

        $department = Department::findOrFail($id);

        // Check if department has categories or types
        if ($department->categories()->count() > 0 || $department->types()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete department. It has associated categories or types. Please remove them first.',
            ], 422);
        }

        $department->delete();

        return response()->json([
            'success' => true,
            'message' => 'Department deleted successfully',
        ], 200);
    }

    /**
     * Create a new category assigned to a department.
     * Only super_admin users can create categories.
     */
    public function createCategory(CreateCategoryRequest $request): JsonResponse
    {
        $user = $request->user();

        // Verify user type is super_admin
        if ($user->type !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Admin can create categories.',
                'data' => [
                    'user_type' => $user->type,
                    'required_type' => 'super_admin',
                ],
            ], 403);
        }

        $category = Category::create([
            'department_id' => $request->department_id,
            'name' => $request->name,
            'description' => $request->description,
            'permission' => $request->permission,
            'is_active' => $request->is_active ?? true,
            'icon' => $request->icon,
            'color' => $request->color,
        ]);

        $category->load('department');

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => $category,
        ], 201);
    }

    /**
     * Update an existing category.
     * Only super_admin users can update categories.
     */
    public function updateCategory(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        if ($user->type !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Admin can update categories.',
                'data' => [
                    'user_type' => $user->type,
                    'required_type' => 'super_admin',
                ],
            ], 403);
        }

        $category = Category::findOrFail($id);

        if ($request->filled('department_id')) {
            $category->department_id = $request->department_id;
        }
        if ($request->filled('name')) {
            $category->name = $request->name;
        }
        if ($request->has('description')) {
            $category->description = $request->description;
        }
        if ($request->has('permission')) {
            $category->permission = $request->permission;
        }
        if ($request->has('is_active')) {
            $category->is_active = $request->is_active;
        }
        if ($request->filled('icon')) {
            $category->icon = $request->icon;
        }
        if ($request->filled('color')) {
            $category->color = $request->color;
        }

        $category->save();
        $category->load('department');

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => $category,
        ], 200);
    }

    /**
     * Create a new type assigned to a department.
     * Only super_admin users can create types.
     */
    public function createType(CreateTypeRequest $request): JsonResponse
    {
        $user = $request->user();

        // Verify user type is super_admin
        if ($user->type !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Admin can create types.',
                'data' => [
                    'user_type' => $user->type,
                    'required_type' => 'super_admin',
                ],
            ], 403);
        }

        $type = Type::create([
            'department_id' => $request->department_id,
            'name' => $request->name,
            'icon' => $request->icon,
            'color' => $request->color,
            'description' => $request->description,
            'permission' => $request->permission,
            'is_active' => $request->is_active ?? true,
        ]);

        $type->load('department');

        return response()->json([
            'success' => true,
            'message' => 'Type created successfully',
            'data' => $type,
        ], 201);
    }

    /**
     * Update an existing type.
     * Only super_admin users can update types.
     */
    public function updateType(UpdateTypeRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        if ($user->type !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Admin can update types.',
                'data' => [
                    'user_type' => $user->type,
                    'required_type' => 'super_admin',
                ],
            ], 403);
        }

        $type = Type::findOrFail($id);

        if ($request->filled('department_id')) {
            $type->department_id = $request->department_id;
        }
        if ($request->filled('name')) {
            $type->name = $request->name;
        }
        if ($request->filled('icon')) {
            $type->icon = $request->icon;
        }
        if ($request->filled('color')) {
            $type->color = $request->color;
        }
        if ($request->has('description')) {
            $type->description = $request->description;
        }
        if ($request->has('permission')) {
            $type->permission = $request->permission;
        }
        if ($request->has('is_active')) {
            $type->is_active = $request->is_active;
        }

        $type->save();
        $type->load('department');

        return response()->json([
            'success' => true,
            'message' => 'Type updated successfully',
            'data' => $type,
        ], 200);
    }

    /**
     * Create a new permission.
     * Only super_admin users can create permissions.
     */
    public function createPermission(CreatePermissionRequest $request): JsonResponse
    {
        $user = $request->user();

        // Verify user type is super_admin
        if ($user->type !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Admin can create permissions.',
                'data' => [
                    'user_type' => $user->type,
                    'required_type' => 'super_admin',
                ],
            ], 403);
        }

        $permission = Permission::create([
            'name' => $request->name,
            'guard_name' => $request->guard_name ?? 'api',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permission created successfully',
            'data' => $permission,
        ], 201);
    }

    /**
     * Create a new role.
     * Only super_admin users can create roles.
     * Automatically ensures default permissions exist and includes them in the role.
     */
    public function createRole(CreateRoleRequest $request): JsonResponse
    {
        $user = $request->user();

        // Verify user type is super_admin
        if ($user->type !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Admin can create roles.',
                'data' => [
                    'user_type' => $user->type,
                    'required_type' => 'super_admin',
                ],
            ], 403);
        }

        $guardName = $request->guard_name ?? 'api';
        
        // Ensure default permissions exist
        $this->ensureDefaultPermissionsExist();

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => $guardName,
        ]);

        // Collect permissions to assign
        $permissionsToAssign = collect();

        // Get default permissions
        $defaultPermissions = $this->getDefaultPermissions($guardName);
        $permissionsToAssign = $permissionsToAssign->merge($defaultPermissions);

        // Add custom permissions if provided
        if ($request->has('permissions') && !empty($request->permissions)) {
            $customPermissionNames = $request->permissions;
            $customPermissions = Permission::whereIn('name', $customPermissionNames)
                ->where('guard_name', $guardName)
                ->get();
            
            // Create missing permissions if they don't exist
            $foundNames = $customPermissions->pluck('name')->toArray();
            $missingNames = array_diff($customPermissionNames, $foundNames);
            
            foreach ($missingNames as $missingName) {
                $newPermission = Permission::create([
                    'name' => $missingName,
                    'guard_name' => $guardName,
                ]);
                $customPermissions->push($newPermission);
            }
            
            $permissionsToAssign = $permissionsToAssign->merge($customPermissions);
        }

        // Remove duplicates and sync all permissions to the role
        $uniquePermissions = $permissionsToAssign->unique('id');
        $role->syncPermissions($uniquePermissions);

        $role->load('permissions');

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully with default permissions',
            'data' => [
                'role' => $role,
                'permissions' => $role->permissions,
            ],
        ], 201);
    }

    /**
     * Assign roles and permissions to a user.
     */
    public function assignUserRolesPermissions(AssignUserRolesPermissionsRequest $request): JsonResponse
    {
        $user = User::findOrFail($request->user_id);

        // Assign roles if provided
        if ($request->has('roles') && !empty($request->roles)) {
            // Get roles with 'api' guard
            $roles = Role::whereIn('id', $request->roles)
                ->where('guard_name', 'api')
                ->get();
            
            if ($roles->count() !== count($request->roles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some roles not found for guard "api"',
                ], 422);
            }
            
            $user->syncRoles($roles);
        }

        // Assign permissions if provided
        if ($request->has('permissions') && !empty($request->permissions)) {
            // Get permissions with 'api' guard
            $permissions = Permission::whereIn('id', $request->permissions)
                ->where('guard_name', 'api')
                ->get();
            
            if ($permissions->count() !== count($request->permissions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some permissions not found for guard "api"',
                ], 422);
            }
            
            $user->syncPermissions($permissions);
        }

        $user->load(['roles', 'permissions']);

        return response()->json([
            'success' => true,
            'message' => 'Roles and permissions assigned successfully',
            'data' => [
                'user' => $user,
                'roles' => $user->roles,
                'permissions' => $user->permissions,
            ],
        ], 200);
    }

    /**
     * Update an existing role.
     * Only super_admin users can update roles.
     * Automatically ensures default permissions exist and includes them when updating permissions.
     */
    public function updateRole(UpdateRoleRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        if ($user->type !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Admin can update roles.',
            ], 403);
        }

        // Ensure default permissions exist
        $this->ensureDefaultPermissionsExist();

        $role = Role::findOrFail($id);

        if ($request->filled('name')) {
            $role->name = $request->name;
        }
        if ($request->has('guard_name')) {
            $role->guard_name = $request->guard_name ?? 'api';
        }

        $role->save();

        // Sync permissions if provided
        if ($request->has('permissions')) {
            // Check if permissions array is explicitly provided (even if empty)
            $permissionNames = $request->permissions;
            
            if (is_array($permissionNames) && count($permissionNames) > 0) {
                // Use ONLY the permissions explicitly provided (respect user's selection)
                $permissions = Permission::whereIn('name', $permissionNames)
                    ->where('guard_name', $role->guard_name)
                    ->get();
                
                // Create missing permissions if they don't exist
                $foundNames = $permissions->pluck('name')->toArray();
                $missingNames = array_diff($permissionNames, $foundNames);
                
                foreach ($missingNames as $missingName) {
                    $newPermission = Permission::create([
                        'name' => $missingName,
                        'guard_name' => $role->guard_name,
                    ]);
                    $permissions->push($newPermission);
                }
                
                // Sync ONLY the explicitly provided permissions
                $role->syncPermissions($permissions);
            } else {
                // Empty array provided - remove all permissions (respect user's choice to remove all)
                $role->syncPermissions([]);
            }
        }

        $role->load('permissions');

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'data' => [
                'role' => $role,
                'permissions' => $role->permissions,
            ],
        ], 200);
    }

    /**
     * Delete a role.
     * Only super_admin users can delete roles.
     */
    public function deleteRole(int $id): JsonResponse
    {
        $user = request()->user();

        if ($user->type !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Admin can delete roles.',
            ], 403);
        }

        $role = Role::findOrFail($id);
        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully',
        ], 200);
    }

    /**
     * Update an existing permission.
     * Only super_admin users can update permissions.
     */
    public function updatePermission(UpdatePermissionRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        if ($user->type !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Admin can update permissions.',
            ], 403);
        }

        $permission = Permission::findOrFail($id);

        if ($request->filled('name')) {
            $permission->name = $request->name;
        }
        if ($request->has('guard_name')) {
            $permission->guard_name = $request->guard_name ?? 'api';
        }

        $permission->save();

        return response()->json([
            'success' => true,
            'message' => 'Permission updated successfully',
            'data' => $permission,
        ], 200);
    }

    /**
     * Delete a permission.
     * Only super_admin users can delete permissions.
     */
    public function deletePermission(int $id): JsonResponse
    {
        $user = request()->user();

        if ($user->type !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Admin can delete permissions.',
            ], 403);
        }

        $permission = Permission::findOrFail($id);
        $permission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted successfully',
        ], 200);
    }

    /**
     * Update an existing user.
     * Super admin can update any user.
     * Users can update their own profile (limited fields).
     */
    public function updateUser(UpdateUserRequest $request, int $id): JsonResponse
    {
        $currentUser = $request->user();
        $user = User::findOrFail($id);

        // Check if user is super admin, has actors permission, or is editing their own profile
        $isOwnProfile = $currentUser->id === $user->id;
        $isCurrentUserSuperAdmin = $currentUser->type === 'super_admin';
        $hasActorsPermission = $currentUser->hasPermissionWithSuperAdminBypass('actors');
        $targetUserIsSuperAdmin = $user->type === 'super_admin';
        $targetUserHasActorsPermission = $user->hasPermissionWithSuperAdminBypass('actors');

        // Authorization check
        if (!$hasActorsPermission && !$isOwnProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You need "actors" permission to edit users, or you can only edit your own profile.',
            ], 403);
        }

        // Restriction 1: Super admin cannot update other super admins
        if ($isCurrentUserSuperAdmin && $targetUserIsSuperAdmin && !$isOwnProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Super admins cannot update other super admin users.',
            ], 403);
        }

        // Restriction 2: Users with actors permission cannot update super admins or other users with actors permission
        if ($hasActorsPermission && !$isCurrentUserSuperAdmin) {
            if ($targetUserIsSuperAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. You cannot update super admin users.',
                ], 403);
            }
            if ($targetUserHasActorsPermission && !$isOwnProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. You cannot update users who have "actors" permission.',
                ], 403);
            }
        }

        // If user is editing their own profile (not super admin), restrict certain fields
        if ($isOwnProfile && !$isCurrentUserSuperAdmin) {
            // Users can only update: first_name, last_name, email, phone_number, whatsapp_number
            // They cannot update: type, is_active, roles, permissions, reset_password
            if ($request->has('type')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot change your own user type.',
                ], 403);
            }
            if ($request->has('is_active')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot change your own active status.',
                ], 403);
            }
            if ($request->has('roles')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot change your own roles.',
                ], 403);
            }
            if ($request->has('reset_password')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot reset your own password through this endpoint. Use change password instead.',
                ], 403);
            }
        }

        // Prevent deactivating super_admin users
        if ($request->has('is_active') && $request->is_active === false && $user->type === 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot deactivate Super Admin users. Super Admin accounts must remain active.',
            ], 422);
        }

        // Update user fields
        if ($request->filled('first_name')) {
            $user->first_name = $request->first_name;
        }
        if ($request->filled('last_name')) {
            $user->last_name = $request->last_name;
        }
        if ($request->has('email')) {
            $user->email = $request->email;
        }
        if ($request->has('phone_number')) {
            $user->phone_number = $request->phone_number;
        }
        if ($request->has('whatsapp_number')) {
            $user->whatsapp_number = $request->whatsapp_number;
        }
        if ($request->has('is_active')) {
            // Only allow setting to true for super_admin, or any value for other users
            if ($user->type === 'super_admin' && $request->is_active === false) {
                // This should not happen due to check above, but double-check
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot deactivate Super Admin users.',
                ], 422);
            }
            $user->is_active = $request->is_active;
        }
        if ($request->filled('type')) {
            // Prevent changing super_admin type
            if ($user->type === 'super_admin' && $request->type !== 'super_admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot change Super Admin user type.',
                ], 422);
            }
            $user->type = $request->type;
        }

        // Handle password reset before saving
        $newPassword = null;
        if ($request->has('reset_password') && $request->reset_password === true) {
            $newPassword = Str::random(12); // Generate 12-character random password
            $user->password = Hash::make($newPassword);
        }

        $user->save();

        // Assign roles if provided
        if ($request->has('roles')) {
            if (!empty($request->roles)) {
                // Get roles with 'api' guard
                $roles = Role::whereIn('id', $request->roles)
                    ->where('guard_name', 'api')
                    ->get();
                
                if ($roles->count() !== count($request->roles)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Some roles not found for guard "api"',
                    ], 422);
                }
                
                $user->syncRoles($roles);
            } else {
                // Remove all roles if empty array
                $user->syncRoles([]);
            }
        }

        $user->load(['roles', 'permissions']);

        $responseData = [
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user,
        ];

        // If password was reset, include the new password in response
        if ($newPassword !== null) {
            $responseData['password'] = $newPassword;
            $responseData['message'] = 'User updated and password reset successfully';
        }

        return response()->json($responseData, 200);
    }
}
