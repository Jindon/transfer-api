#!/bin/sh
set -e

echo "Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction

echo "Seeding accounts..."
php bin/console app:seed:accounts --no-interaction || true

exec "$@"
