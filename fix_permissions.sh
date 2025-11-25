#!/bin/bash

# Fix Laravel storage permissions
# Run this script from the TaskManager_API directory on your server

# Navigate to the Laravel project directory
cd /home/elsahariano/public_html/TaskManager_API

# Set ownership (adjust www-data to your web server user if different)
# You may need to use your actual username instead of www-data
sudo chown -R elsahariano:elsahariano storage bootstrap/cache

# Set directory permissions (775 allows read, write, execute for owner and group)
find storage -type d -exec chmod 775 {} \;

# Set file permissions (664 allows read and write for owner and group)
find storage -type f -exec chmod 664 {} \;

# Set bootstrap/cache permissions
find bootstrap/cache -type d -exec chmod 775 {} \;
find bootstrap/cache -type f -exec chmod 664 {} \;

# Ensure the logs directory exists and is writable
mkdir -p storage/logs
chmod 775 storage/logs

# If the log file exists, make sure it's writable
if [ -f storage/logs/laravel.log ]; then
    chmod 664 storage/logs/laravel.log
fi

echo "Permissions fixed! Storage directories should now be writable."

