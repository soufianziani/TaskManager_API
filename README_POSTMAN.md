# Postman Collection - Task Manager API

## Setup Instructions

### 1. Import Collection
1. Open Postman
2. Click **Import** button
3. Select the file `TaskManager_API.postman_collection.json`
4. The collection will be imported with all endpoints

### 2. Configure Environment Variables

The collection uses the following variables:
- `base_url`: Your API base URL (default: `http://localhost:8000`)
- `auth_token`: Authentication token (automatically set after login)

**To set up environment variables:**
1. Click on the collection name
2. Go to the **Variables** tab
3. Set `base_url` to your API URL (e.g., `http://localhost:8000` or your production URL)
4. The `auth_token` will be automatically set when you use the Login endpoint

**Alternative - Create a Postman Environment:**
1. Click **Environments** in the left sidebar
2. Click **+** to create a new environment
3. Add variables:
   - Variable: `base_url`, Initial Value: `http://localhost:8000`
   - Variable: `auth_token`, Initial Value: (leave empty)
4. Select the environment from the dropdown at the top right

### 3. Getting Started

#### Step 1: Run Migrations
```bash
php artisan migrate
```

#### Step 2: Create Super Admin
```bash
php artisan admin:create-super-admin
```

#### Step 3: Get Authentication Token

**Option A: If you have a login endpoint**
- Use the "Login" request in the Authentication folder
- Update email and password in the request body
- The token will be automatically saved to `auth_token` variable

**Option B: Manual token (for testing)**
- You can manually set the token in Postman variables
- Or get token from Laravel Tinker:
```bash
php artisan tinker
$user = App\Models\User::first();
$token = $user->createToken('test-token')->plainTextToken;
echo $token;
```

### 4. Test Endpoints

The collection is organized into folders:

#### Authentication
- **Login**: Login to get authentication token

#### Super Admin - Departments
- **Create Department**: Create a new department

#### Super Admin - Categories
- **Create Category**: Create a new category (requires department_id)
- **Create Category - Development**: Example request
- **Create Category - Design**: Example request

#### Super Admin - Types
- **Create Type - Urgent**: Create urgent type (red color)
- **Create Type - Pending**: Create pending type (blue color)
- **Create Type - Testing**: Create testing type (orange color)
- **Create Type - Complete**: Create complete type (green color)

#### Super Admin - User Management
- **Assign User Roles and Permissions**: Assign roles and/or permissions to a user
- **Assign User - Only Roles**: Example with only roles
- **Assign User - Only Permissions**: Example with only permissions

## Example Request Sequence

1. **Create Department**
   ```json
   POST /api/super-admin/create-department
   {
       "name": "IT",
       "description": "Information Technology Department",
       "is_active": true
   }
   ```
   Note the `id` returned (e.g., `1`)

2. **Create Types**
   ```json
   POST /api/super-admin/create-type
   {
       "department_id": 1,
       "name": "Urgent",
       "color": "red",
       "is_active": true
   }
   ```

3. **Create Categories**
   ```json
   POST /api/super-admin/create-categorie
   {
       "department_id": 1,
       "name": "Test",
       "description": "Testing category",
       "is_active": true
   }
   ```

4. **Assign User Roles/Permissions**
   ```json
   POST /api/super-admin/assign-user-roles-permissions
   {
       "user_id": 2,
       "roles": [1, 2],
       "permissions": [1, 2, 3]
   }
   ```

## Response Format

All endpoints return JSON responses in this format:

**Success:**
```json
{
    "success": true,
    "message": "Operation successful",
    "data": { ... }
}
```

**Error:**
```json
{
    "success": false,
    "message": "Error message",
    "errors": { ... }
}
```

## Common Status Codes

- `200 OK`: Request successful
- `201 Created`: Resource created successfully
- `401 Unauthorized`: Not authenticated or invalid token
- `403 Forbidden`: Authenticated but insufficient permissions (not Super Admin)
- `422 Unprocessable Entity`: Validation errors
- `404 Not Found`: Resource not found

## Notes

- All Super Admin endpoints require:
  - Valid authentication token (Bearer token)
  - User must have `super_admin` role
- The `department_id` must exist before creating categories or types
- Category names must be unique per department
- Type names must be unique per department
- Department names must be unique globally

## Troubleshooting

**401 Unauthorized:**
- Check if token is set correctly
- Token may have expired
- Re-authenticate using Login endpoint

**403 Forbidden:**
- User doesn't have `super_admin` role
- Create super admin: `php artisan admin:create-super-admin`
- Assign role to user manually if needed

**422 Validation Error:**
- Check request body format
- Ensure required fields are provided
- Check if referenced IDs (department_id, user_id, etc.) exist

**404 Not Found:**
- Check base_url is correct
- Verify route is correct: `/api/super-admin/...`
- Check if Laravel server is running: `php artisan serve`

