<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignUserRolesPermissionsRequest;
use App\Http\Requests\CreateCategoryRequest;
use App\Http\Requests\CreateDepartmentRequest;
use App\Http\Requests\CreatePermissionRequest;
use App\Http\Requests\CreateRoleRequest;
use App\Http\Requests\CreateTaskNameRequest;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Http\Requests\UpdateTaskNameRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Requests\UpdatePermissionRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Helpers\PermissionHelper;
use App\Models\Category;
use App\Models\Department;
use App\Models\TaskName;
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
            // Permission is now optional and not auto-created
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

        // Check if department has related tasks
        if ($department->tasks()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete department. It has associated tasks. Please deactivate it instead.',
            ], 422);
        }

        // Also prevent delete if it still has categories or types attached
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
            // Permission is now optional and not auto-created
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
     * Delete an existing category.
     * Only super_admin users can delete categories.
     * Prevent deletion if the category has tasks.
     */
    public function deleteCategory(int $id): JsonResponse
    {
        $user = request()->user();

        // Verify user type is super_admin
        if ($user->type !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Admin can delete categories.',
                'data' => [
                    'user_type' => $user->type,
                    'required_type' => 'super_admin',
                ],
            ], 403);
        }

        $category = Category::findOrFail($id);

        // Prevent deleting category if it has tasks
        if ($category->tasks()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category. It has associated tasks. Please deactivate it instead.',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully',
        ], 200);
    }

    /**
     * Create a new type assigned to a department.
     * Only super_admin users can create types.
     */
    public function createTaskName(CreateTaskNameRequest $request): JsonResponse
    {
        $user = $request->user();

        // Verify user type is super_admin
        if ($user->type !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Admin can create task names.',
                'data' => [
                    'user_type' => $user->type,
                    'required_type' => 'super_admin',
                ],
            ], 403);
        }

        $taskName = TaskName::create([
            'category_id' => $request->category_id,
            'name' => $request->name,
            'icon' => $request->icon,
            'color' => $request->color,
            'description' => $request->description,
            // Permission is now optional and not auto-created
            'permission' => $request->permission,
            'is_active' => $request->is_active ?? true,
        ]);

        $taskName->load('category');

        return response()->json([
            'success' => true,
            'message' => 'Task name created successfully',
            'data' => $taskName,
        ], 201);
    }

    /**
     * Update an existing type.
     * Only super_admin users can update types.
     */
    public function updateTaskName(UpdateTaskNameRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        if ($user->type !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Admin can update task names.',
                'data' => [
                    'user_type' => $user->type,
                    'required_type' => 'super_admin',
                ],
            ], 403);
        }

        $taskName = TaskName::findOrFail($id);

        if ($request->filled('category_id')) {
            $taskName->category_id = $request->category_id;
        }
        if ($request->filled('name')) {
            $taskName->name = $request->name;
        }
        if ($request->filled('icon')) {
            $taskName->icon = $request->icon;
        }
        if ($request->filled('color')) {
            $taskName->color = $request->color;
        }
        if ($request->has('description')) {
            $taskName->description = $request->description;
        }
        if ($request->has('permission')) {
            $taskName->permission = $request->permission;
        }
        if ($request->has('is_active')) {
            $taskName->is_active = $request->is_active;
        }

        $taskName->save();
        $taskName->load('category');

        return response()->json([
            'success' => true,
            'message' => 'Task name updated successfully',
            'data' => $taskName,
        ], 200);
    }

    /**
     * Delete an existing type.
     * Only super_admin users can delete types.
     * Prevent deletion if the type has tasks.
     */
    public function deleteTaskName(int $id): JsonResponse
    {
        $user = request()->user();

        if ($user->type !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Admin can delete task names.',
                'data' => [
                    'user_type' => $user->type,
                    'required_type' => 'super_admin',
                ],
            ], 403);
        }

        $taskName = TaskName::findOrFail($id);

        // Prevent deleting task name if it has tasks
        if ($taskName->tasks()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete task name. It has associated tasks. Please deactivate it instead.',
            ], 422);
        }

        $taskName->delete();

        return response()->json([
            'success' => true,
            'message' => 'Type deleted successfully',
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
     * Permissions are assigned ONLY if explicitly provided in the request.
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

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => $guardName,
        ]);

        // Assign permissions ONLY if explicitly provided
        if ($request->has('permissions')) {
            $permissionNames = $request->permissions;

            if (is_array($permissionNames) && count($permissionNames) > 0) {
                $permissions = Permission::whereIn('name', $permissionNames)
                    ->where('guard_name', $guardName)
                    ->get();

                // Create missing permissions if they don't exist
                $foundNames = $permissions->pluck('name')->toArray();
                $missingNames = array_diff($permissionNames, $foundNames);

                foreach ($missingNames as $missingName) {
                    $newPermission = Permission::create([
                        'name' => $missingName,
                        'guard_name' => $guardName,
                    ]);
                    $permissions->push($newPermission);
                }

                $role->syncPermissions($permissions);
            } else {
                // Explicit empty array → no permissions
                $role->syncPermissions([]);
            }
        }

        $role->load('permissions');

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
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
     * Create a new user.
     * Only super_admin can create users.
     */
    public function createUser(CreateUserRequest $request): JsonResponse
    {
        $currentUser = $request->user();

        // Only super_admin can create users
        if ($currentUser->type !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Admin can create users.',
            ], 403);
        }

        // Generate unique 4-digit username starting from 1000
        // Find the highest existing numeric username >= 1000 and <= 9999
        $maxUsername = User::whereNotNull('user_name')
            ->whereRaw('user_name REGEXP "^[0-9]{4}$"') // Only 4-digit numbers
            ->whereRaw('CAST(user_name AS UNSIGNED) >= 1000')
            ->whereRaw('CAST(user_name AS UNSIGNED) <= 9999')
            ->orderByRaw('CAST(user_name AS UNSIGNED) DESC')
            ->value('user_name');

        $nextUsername = 1000; // Default starting point
        
        if ($maxUsername !== null && is_numeric($maxUsername)) {
            $nextUsername = (int)$maxUsername + 1;
        }

        // If we've exceeded 9999, find the first available number starting from 1000
        if ($nextUsername > 9999) {
            $nextUsername = 1000;
        }

        // Find the next available username (in case there are gaps)
        $userName = (string)$nextUsername;
        while (User::where('user_name', $userName)->exists() && $nextUsername <= 9999) {
            $nextUsername++;
            $userName = (string)$nextUsername;
        }
        
        // If all numbers are taken, return error
        if ($nextUsername > 9999) {
            return response()->json([
                'success' => false,
                'message' => 'All 4-digit usernames (1000-9999) are in use. Please contact system administrator.',
            ], 422);
        }

        // Username is already 4 digits (1000-9999), no padding needed
        $userName = (string)$nextUsername;

        // Create user
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name ?? '',
            'user_name' => $userName,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'whatsapp_number' => $request->whatsapp_number,
            'type' => $request->type,
            'is_active' => $request->is_active ?? true,
            'password' => Hash::make(Str::random(12)), // Generate random password
        ]);

        // Assign roles if provided
        if ($request->has('roles') && !empty($request->roles)) {
            $roles = Role::whereIn('id', $request->roles)
                ->where('guard_name', 'api')
                ->get();
            
            if ($roles->count() > 0) {
                $user->syncRoles($roles);
            }
        }

        $user->load(['roles', 'permissions']);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => $user,
        ], 201);
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
