#!/bin/bash
set -e

# Wait for MySQL to be ready
echo "Waiting for database..."
sleep 10

echo "debug"
ls

echo "Running migrations..."
php bin/console make:migrations
php bin/console doctrine:migrations:migrate --no-interaction

echo "Starting Symfony..."
symfony serve --no-tls --port=8000 --allow-all-ip
