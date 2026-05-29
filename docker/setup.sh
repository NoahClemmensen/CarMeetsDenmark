#!/bin/bash
set -e

# Wait for MySQL to be ready
echo "Waiting for database..."
sleep 10

echo "Running migrations (serialized via MySQL advisory lock)..."
php docker/migrate.php

echo "Starting Symfony..."
exec symfony serve --no-tls --port=8000 --allow-all-ip
