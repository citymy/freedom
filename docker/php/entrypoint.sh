#!/bin/sh

# If .env does not exist, copy from .env.example
if [ ! -f .env ]; then
    echo "Creating .env from .env.example..."
    cp .env.example .env
fi


# Run database migrations only for the main FPM container
if [ "$1" = "php-fpm" ]; then
    echo "Running database migrations..."
    php bin/migrate.php
fi

# Execute the main container command
exec "$@"
