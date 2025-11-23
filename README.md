# 🚀 Taskiano - Task Management API

<div align="center">

![Laravel](https://img.shields.io/badge/Laravel-10.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

A robust RESTful API for task management with OTP authentication, role-based access control, and comprehensive organizational features.

[Features](#-features) • [Installation](#-installation) • [API Documentation](#-api-documentation) • [Configuration](#-configuration)

</div>

---

## 📋 Table of Contents

- [About](#-about)
- [Features](#-features)
- [Technology Stack](#-technology-stack)
- [Requirements](#-requirements)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [API Documentation](#-api-documentation)
- [Project Structure](#-project-structure)
- [Usage Examples](#-usage-examples)
- [Testing](#-testing)
- [Contributing](#-contributing)
- [License](#-license)

---

## 🎯 About

**Taskiano** is a comprehensive task management API built with Laravel 10. It provides a secure, scalable solution for managing tasks with features like OTP-based authentication, department-based organization, role-based access control, and flexible task categorization.

### Key Capabilities

- ✅ **Multi-Channel OTP Authentication** (SMS & WhatsApp)
- ✅ **Role-Based Access Control** (Super Admin, Admin, User)
- ✅ **Department-Based Organization**
- ✅ **Task Management** with categories and types
- ✅ **Permission Management** with Spatie Laravel Permission
- ✅ **RESTful API** with Laravel Sanctum authentication
- ✅ **Webhook Support** for WhatsApp callbacks

---

## ✨ Features

### 🔐 Authentication & Security
- **OTP-based Authentication**: Login and registration via SMS or WhatsApp
- **Traditional Authentication**: Email/password login
- **Token-based Security**: Laravel Sanctum for API authentication
- **Role-based Permissions**: Fine-grained access control

### 📊 Task Management
- Create and manage tasks
- Organize tasks by departments, categories, and types
- Custom task types with color coding (Urgent, Pending, Testing, Complete)
- Task assignment and tracking

### 🏢 Organizational Structure
- **Departments**: Organize teams and departments
- **Categories**: Categorize tasks within departments
- **Types**: Define task types with visual indicators
- Permission-based access to organizational units

### 👥 User Management
- Super Admin role for system administration
- Role and permission assignment
- User profile management
- Device and FCM token support for push notifications

---

## 🛠 Technology Stack

| Component | Technology |
|-----------|-----------|
| **Framework** | Laravel 10.x |
| **PHP Version** | PHP 8.1+ |
| **Authentication** | Laravel Sanctum |
| **Permissions** | Spatie Laravel Permission |
| **Database** | MySQL |
| **SMS Provider** | Infobip |
| **WhatsApp Provider** | Wadina Agency |
| **API Testing** | Postman |

### Key Packages
- `laravel/sanctum` - API token authentication
- `spatie/laravel-permission` - Role and permission management
- `guzzlehttp/guzzle` - HTTP client for external APIs

---

## 📦 Requirements

- **PHP** >= 8.1
- **Composer** >= 2.0
- **MySQL** >= 5.7 or **MariaDB** >= 10.3
- **Node.js** & **NPM** (for asset compilation)
- **PHP Extensions**: BCMath, Ctype, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML

---

## 🚀 Installation

### Step 1: Clone the Repository

```bash
git clone https://github.com/yourusername/taskiano.git
cd taskiano
```

### Step 2: Install Dependencies

```bash
composer install
npm install
```

### Step 3: Environment Configuration

Copy the environment file and configure it:

```bash
cp .env.example .env
php artisan key:generate
```

Update `.env` with your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=taskiano
DB_USERNAME=root
DB_PASSWORD=
```

### Step 4: Database Setup

Create the database and run migrations:

```bash
php artisan migrate
php artisan db:seed  # Optional: seed initial data
```

### Step 5: Create Super Admin

```bash
php artisan admin:create-super-admin
```

This will prompt you to enter:
- Email
- Password
- First Name
- Last Name

### Step 6: Start Development Server

```bash
php artisan serve
```

The API will be available at `http://localhost:8000`

---

## ⚙️ Configuration

### API Services Configuration

Add the following to your `.env` file for OTP functionality:

```env
# WhatsApp API
WHATSAPP_API_KEY=your_whatsapp_api_key
WHATSAPP_BASE_URL=https://connect.wadina.agency/webhooks
WHATSAPP_WEBHOOK_ID=
WHATSAPP_TEST_MODE=false

# Infobip SMS API
INFOBIP_API_KEY=your_infobip_api_key
INFOBIP_BASE_URL=https://xl4ln4.api.infobip.com/sms/2/text/advanced
INFOBIP_SENDER_ID=TaskManager
```

For detailed configuration instructions, see [ENV_CONFIGURATION.md](ENV_CONFIGURATION.md).

### Database Configuration

If using XAMPP with custom MySQL port, see [DATABASE_SETUP.md](DATABASE_SETUP.md).

---

## 📚 API Documentation

### Base URL
```
http://localhost:8000/api
```

### Authentication

All protected endpoints require a Bearer token in the Authorization header:

```
Authorization: Bearer {your_token_here}
```

### Main Endpoints

#### 🔐 Authentication Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/request-otp/whatsapp` | Request OTP via WhatsApp |
| `POST` | `/request-otp/sms` | Request OTP via SMS |
| `POST` | `/verify-otp` | Verify OTP code |
| `POST` | `/login` | Login with email/password |
| `POST` | `/login-otp` | Login with OTP |
| `POST` | `/register-otp` | Register with OTP |
| `POST` | `/logout` | Logout (authenticated) |
| `GET` | `/user` | Get authenticated user (authenticated) |

#### 👑 Super Admin Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/super-admin/create-department` | Create a new department |
| `POST` | `/super-admin/create-categorie` | Create a new category |
| `POST` | `/super-admin/create-type` | Create a new task type |
| `POST` | `/super-admin/create-permission` | Create a new permission |
| `POST` | `/super-admin/create-role` | Create a new role |
| `POST` | `/super-admin/assign-user-roles-permissions` | Assign roles/permissions to user |

#### 📝 Admin Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/admin/create-task` | Create a new task |

#### 🔔 Webhook Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/webhooks/whatsapp/callback` | WhatsApp webhook callback |

### Response Format

All endpoints return JSON responses:

**Success Response:**
```json
{
    "success": true,
    "message": "Operation successful",
    "data": {
        // Response data
    }
}
```

**Error Response:**
```json
{
    "success": false,
    "message": "Error message",
    "errors": {
        // Validation errors (if applicable)
    }
}
```

### Postman Collection

A complete Postman collection is available for testing:

1. Import `TaskManager_API.postman_collection.json` into Postman
2. Configure environment variables:
   - `base_url`: `http://localhost:8000`
   - `auth_token`: (will be set automatically after login)
3. See [README_POSTMAN.md](README_POSTMAN.md) for detailed setup instructions

---

## 📁 Project Structure

```
taskiano/
├── app/
│   ├── Console/Commands/        # Artisan commands
│   ├── Http/
│   │   ├── Controllers/         # API controllers
│   │   ├── Middleware/          # Custom middleware
│   │   └── Requests/            # Form request validation
│   ├── Models/                  # Eloquent models
│   └── Services/                # Business logic services
├── database/
│   ├── migrations/              # Database migrations
│   └── seeders/                 # Database seeders
├── routes/
│   └── api.php                  # API routes
├── config/                      # Configuration files
├── public/                      # Public assets
└── tests/                      # Test files
```

### Key Models

- **User** - User accounts with roles and permissions
- **Task** - Task entities
- **Department** - Organizational departments
- **Category** - Task categories
- **Type** - Task types (Urgent, Pending, etc.)
- **OtpLog** - OTP request/verification logs

---

## 💡 Usage Examples

### 1. Request OTP via WhatsApp

```bash
curl -X POST http://localhost:8000/api/request-otp/whatsapp \
  -H "Content-Type: application/json" \
  -d '{
    "phone_number": "+212612345678",
    "purpose": "login"
  }'
```

### 2. Verify OTP and Login

```bash
curl -X POST http://localhost:8000/api/verify-otp \
  -H "Content-Type: application/json" \
  -d '{
    "phone_number": "+212612345678",
    "otp_code": "123456"
  }'
```

### 3. Create Department (Super Admin)

```bash
curl -X POST http://localhost:8000/api/super-admin/create-department \
  -H "Authorization: Bearer {your_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "IT Department",
    "description": "Information Technology",
    "is_active": true
  }'
```

### 4. Create Task (Admin)

```bash
curl -X POST http://localhost:8000/api/admin/create-task \
  -H "Authorization: Bearer {your_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Implement API endpoint",
    "description": "Create new API endpoint for tasks",
    "department_id": 1,
    "category_id": 1,
    "type_id": 1
  }'
```

---

## 🧪 Testing

### Run Tests

```bash
php artisan test
```

### Test Individual Feature

```bash
php artisan test --filter FeatureName
```

---

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. **Fork the repository**
2. **Create a feature branch** (`git checkout -b feature/amazing-feature`)
3. **Commit your changes** (`git commit -m 'Add some amazing feature'`)
4. **Push to the branch** (`git push origin feature/amazing-feature`)
5. **Open a Pull Request**

### Code Style

This project uses Laravel Pint for code formatting:

```bash
./vendor/bin/pint
```

---

## 📄 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

## 📞 Support

For issues, questions, or contributions:

- 📧 Create an issue on GitHub
- 📖 Check the [API Documentation](README_POSTMAN.md)
- 🔧 Review [Configuration Guide](ENV_CONFIGURATION.md)
- 💾 See [Database Setup](DATABASE_SETUP.md)

---

## 🙏 Acknowledgments

- Built with [Laravel](https://laravel.com)
- Permission management by [Spatie Laravel Permission](https://github.com/spatie/laravel-permission)
- Authentication powered by [Laravel Sanctum](https://laravel.com/docs/sanctum)

---

<div align="center">

**Made with ❤️ using Laravel**

⭐ Star this repo if you find it helpful!

</div>
