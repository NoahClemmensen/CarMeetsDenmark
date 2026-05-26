#!/bin/bash
set -e

# Wait for MySQL to be ready
echo "Waiting for database..."
sleep 10

echo "Running migrations..."
php bin/console doctrine:schema:update --force --no-interaction

echo "Starting Symfony..."
exec symfony serve --no-tls --port=8000 --allow-all-ip
