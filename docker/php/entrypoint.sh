#!/bin/sh

# If .env does not exist, copy from .env.example
if [ ! -f .env ]; then
    echo "Creating .env from .env.example..."
    cp .env.example .env
fi

# Install composer dependencies
echo "Installing composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloade

# Dynamically change ownership of the vendor directory to match the host directory owne
HOST_UID=$(stat -c "%u" .)
HOST_GID=$(stat -c "%g" .)
chown -R $HOST_UID:$HOST_GID vendor/ 2>/dev/null

# Run database migrations only for the main FPM containe
if [ "$1" = "php-fpm" ]; then
    echo "Running database migrations..."
    php bin/migrate.php
fi

# Execute the main container command
exec "$@"
