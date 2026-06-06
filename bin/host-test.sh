#!/usr/bin/env bash
# Run package tests against real LaraCollab models and schemas.
# Supports ddev or Laravel Sail, detected automatically.
set -euo pipefail

HOST_DIR="${1:-}"

if [ -z "$HOST_DIR" ] || [ ! -d "$HOST_DIR" ]; then
    echo "Usage: $0 <path-to-lara-collab>"
    echo "Example: $0 ~/projects/lara-collab"
    exit 1
fi

cd "$HOST_DIR"

# Detect containerised environment
if command -v ddev &>/dev/null && [ -f .ddev/config.yaml ]; then
    CMD="ddev"
elif command -v sail &>/dev/null && [ -f docker-compose.yml ]; then
    CMD="sail"
elif [ -n "${LARAVEL_SAIL:-}" ] || [ -n "${DDEV_PROJECT:-}" ]; then
    CMD=""
elif [ -f vendor/bin/pest ]; then
    echo "No ddev or sail detected. Running pest directly (assuming native PHP)."
    vendor/bin/pest tests/Feature/TeamworkImportHostTest.php
    exit $?
else
    echo "No ddev, sail, or vendor/bin/pest found."
    echo "To run manually:"
    echo "  1. Sync this package into your host:"
    echo '     rsync -a --delete /path/to/laracollab-teamwork-import/ packages/bigcalm/laracollab-teamwork-import/'
    echo "  2. Update composer: composer update bigcalm/laracollab-teamwork-import"
    echo "  3. Publish config: php artisan vendor:publish --tag=teamwork-config --force"
    echo "  4. Run tests: vendor/bin/pest tests/Feature/TeamworkImportHostTest.php"
    exit 1
fi

# Sync standalone package into host
rsync -a --delete "$(dirname "$0")/.." packages/bigcalm/laracollab-teamwork-import/

# Update composer and publish config
"$CMD" composer update bigcalm/laracollab-teamwork-import --no-interaction
"$CMD" artisan vendor:publish --tag=teamwork-config --force

# Run the host tests
"$CMD" exec vendor/bin/pest tests/Feature/TeamworkImportHostTest.php
