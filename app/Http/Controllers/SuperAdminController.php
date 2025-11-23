<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignUserRolesPermissionsRequest;
use App\Http\Requests\CreateCategoryRequest;
use App\Http\Requests\CreateDepartmentRequest;
use App\Http\Requests\CreatePermissionRequest;
use App\Http\Requests\CreateRoleRequest;
use App\Http\Requests\CreateTypeRequest;
use App\Models\Category;
use App\Models\Department;
use App\Models\Type;
use App\Models\User;
use Illuminate\Http\JsonResponse;
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
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Department created successfully',
            'data' => $department,
        ], 201);
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
        ]);

        $category->load('department');

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => $category,
        ], 201);
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
            'color' => $request->color,
            'description' => $request->description,
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
            $role->syncPermissions($request->permissions);
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
            $user->syncRoles($request->roles);
        }

        // Assign permissions if provided
        if ($request->has('permissions') && !empty($request->permissions)) {
            $user->syncPermissions($request->permissions);
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
}
