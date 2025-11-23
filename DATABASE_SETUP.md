# Database Setup Guide

## Issue Found
MySQL is running via XAMPP on **port 4000**, but Laravel is trying to connect to the default port **3306**.

## Solution

### Option 1: Update .env file (Recommended)

Update your `.env` file with the correct MySQL settings:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=4000
DB_DATABASE=tasky
DB_USERNAME=root
DB_PASSWORD=
```

**Note:** If you're using XAMPP's default setup, the username is usually `root` with no password.

### Option 2: Use MySQL Socket (Alternative)

If port connection doesn't work, you can use the socket file:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=4000
DB_SOCKET=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock
DB_DATABASE=tasky
DB_USERNAME=root
DB_PASSWORD=
```

## Steps to Fix

1. **Open your `.env` file** in the project root

2. **Update the database configuration**:
   ```env
   DB_PORT=4000
   DB_DATABASE=tasky
   DB_USERNAME=root
   DB_PASSWORD=
   ```

3. **Create the database** (if it doesn't exist):
   ```bash
   mysql -u root -P 4000 -e "CREATE DATABASE IF NOT EXISTS tasky;"
   ```
   
   Or use XAMPP's phpMyAdmin to create the database.

4. **Test the connection**:
   ```bash
   php artisan migrate
   ```

## Verify Database Connection

Test the connection manually:
```bash
mysql -u root -P 4000 -e "SHOW DATABASES;"
```

## If MySQL is not running

Start XAMPP MySQL:
```bash
sudo /Applications/XAMPP/xamppfiles/xampp startmysql
```

Or use XAMPP Control Panel to start MySQL service.

## Common Issues

### Connection Refused
- Check if MySQL is running: `ps aux | grep mysql`
- Verify the port: XAMPP uses port 4000 by default
- Check XAMPP Control Panel

### Access Denied
- Default XAMPP MySQL user: `root` with no password
- If you set a password, update `DB_PASSWORD` in `.env`

### Database doesn't exist
- Create it manually: `mysql -u root -P 4000 -e "CREATE DATABASE tasky;"`
- Or use phpMyAdmin at `http://localhost/phpmyadmin`

