# PHP Configuration for Video File Uploads

## Problem
When uploading video files, you may encounter errors because PHP's default upload limits are too small (typically 2MB).

## Solution

### Option 1: PHP Configuration File (php.ini)

If you have access to `php.ini`, update these values:

```ini
upload_max_filesize = 500M
post_max_size = 500M
max_execution_time = 300
max_input_time = 300
memory_limit = 512M
```

**Location of php.ini:**
- Windows: Usually in `C:\php\php.ini` or `C:\xampp\php\php.ini`
- Linux: Usually in `/etc/php/8.x/apache2/php.ini` or `/etc/php/8.x/fpm/php.ini`
- macOS: Usually in `/usr/local/etc/php/8.x/php.ini`

**To find your php.ini location:**
```bash
php --ini
```

### Option 2: .htaccess (Apache only)

The `.htaccess` file in `public/.htaccess` has been updated with upload limits. This only works if:
- You're using Apache (not Nginx)
- PHP is running as an Apache module (not PHP-FPM)
- `AllowOverride` is enabled in Apache config

### Option 3: PHP-FPM Configuration (if using PHP-FPM)

If you're using PHP-FPM, edit the pool configuration file:

**Location:** Usually `/etc/php/8.x/fpm/pool.d/www.conf`

```ini
php_admin_value[upload_max_filesize] = 500M
php_admin_value[post_max_size] = 500M
php_admin_value[max_execution_time] = 300
php_admin_value[max_input_time] = 300
```

Then restart PHP-FPM:
```bash
sudo systemctl restart php8.x-fpm
```

### Option 4: Nginx Configuration

If using Nginx with PHP-FPM, add to your Nginx site configuration:

```nginx
client_max_body_size 500M;
```

### Verification

After making changes, restart your web server and verify:

```bash
php -i | grep upload_max_filesize
php -i | grep post_max_size
```

Or create a `phpinfo.php` file:
```php
<?php phpinfo(); ?>
```

Look for:
- `upload_max_filesize` should be 500M
- `post_max_size` should be 500M (must be >= upload_max_filesize)

## Important Notes

1. **post_max_size must be >= upload_max_filesize**
2. **Restart required**: After changing php.ini, restart Apache/Nginx/PHP-FPM
3. **Server limits**: Some hosting providers have hard limits that cannot be changed
4. **Memory**: Large video uploads may require increased `memory_limit`

## Current Application Limits

The application has been configured to accept:
- **Video files**: Up to 500MB
- **Image files**: Up to 50MB
- **PDF files**: Up to 100MB
- **Mixed (foto or video)**: Up to 500MB

These limits are enforced in `app/Http/Controllers/FileController.php`.

