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
     * Create a new department.
     * Only super_admin users can create departments.
     */
    public function createDepartment(CreateDepartmentRequest $request): JsonResponse
    {
        $user = $request->user();

        // Verify user type is super_admin
        if ($user->type !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Admin can create departments.',
                'data' => [
                    'user_type' => $user->type,
                    'required_type' => 'super_admin',
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
     * Only super_admin users can update departments.
     */
    public function updateDepartment(UpdateDepartmentRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Verify user type is super_admin
        if ($user->type !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Admin can update departments.',
                'data' => [
                    'user_type' => $user->type,
                    'required_type' => 'super_admin',
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

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => $request->guard_name ?? 'api',
        ]);

        // Assign permissions to role if provided
        if ($request->has('permissions') && !empty($request->permissions)) {
            // Ensure permissions exist with the same guard as the role
            $permissionNames = $request->permissions;
            $permissions = Permission::whereIn('name', $permissionNames)
                ->where('guard_name', $role->guard_name)
                ->get();
            
            if ($permissions->count() !== count($permissionNames)) {
                $foundNames = $permissions->pluck('name')->toArray();
                $missingNames = array_diff($permissionNames, $foundNames);
                return response()->json([
                    'success' => false,
                    'message' => 'Some permissions not found for guard "' . $role->guard_name . '": ' . implode(', ', $missingNames),
                ], 422);
            }
            
            $role->syncPermissions($permissions);
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
            if (!empty($request->permissions)) {
                // Ensure permissions exist with the same guard as the role
                $permissionNames = $request->permissions;
                $permissions = Permission::whereIn('name', $permissionNames)
                    ->where('guard_name', $role->guard_name)
                    ->get();
                
                if ($permissions->count() !== count($permissionNames)) {
                    $foundNames = $permissions->pluck('name')->toArray();
                    $missingNames = array_diff($permissionNames, $foundNames);
                    return response()->json([
                        'success' => false,
                        'message' => 'Some permissions not found for guard "' . $role->guard_name . '": ' . implode(', ', $missingNames),
                    ], 422);
                }
                
                $role->syncPermissions($permissions);
            } else {
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
     * Only super_admin users can update users.
     */
    public function updateUser(UpdateUserRequest $request, int $id): JsonResponse
    {
        $currentUser = $request->user();

        if ($currentUser->type !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Super Admin can update users.',
            ], 403);
        }

        $user = User::findOrFail($id);

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
