#!/bin/sh
set -eu

cd /var/www/html

# Render provides the APP_KEY and all secrets at run time.
# Discover installed Laravel packages and cache non-secret configuration.
php artisan package:discover --no-interaction --ansi
php artisan config:cache

# Intentionally DO NOT run migrate --force automatically.
# Existing Supabase data must be backed up and migrations applied deliberately.
exec apache2-foreground
