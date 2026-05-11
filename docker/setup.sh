#!/bin/bash
set -e

# Wait for MySQL to be ready
echo "Waiting for database..."
sleep 10

echo "Give permissions..."
chmod -R 777 ./


echo "Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
php bin/console doctrine:schema:update --force

echo "Starting Symfony..."
exec symfony serve --no-tls --port=8000 --allow-all-ip
