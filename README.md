# LaraCollab Teamwork Import

Import data from Teamwork.com API v3 into LaraCollab. CLI-only v1 — `php artisan teamwork:import` handles everything.

## Installation

```bash
composer require bigcalm/laracollab-teamwork-import

php artisan vendor:publish --tag=teamwork-config
php artisan migrate

php artisan teamwork:import
```

## Environment

```bash
TEAMWORK_API_SITE_NAME=mycompany
TEAMWORK_API_TOKEN=xxx
TEAMWORK_DEFAULT_ROLE=developer
```

## Usage

```bash
# Full import (all entities except files)
php artisan teamwork:import

# Partial import (comma-separated entity keys)
# Valid keys: companies, users, tags, projects, tasklists, tasks, time, comments
php artisan teamwork:import --entities=users,companies

# Single project
php artisan teamwork:import --project=456

# Override default role
php artisan teamwork:import --role=admin

# Dispatch to queue
php artisan teamwork:import --queue

# Import project files and link to tasks (run after teamwork:import)
php artisan teamwork:import-files

# Import files for specific Teamwork project IDs
php artisan teamwork:import-files --project=67133 --project=498141
```

## Import Order

1. Client Companies
2. Users (syncs client users to their companies)
3. Labels (Tags)
4. Projects (syncs project-user access)
5. Task Groups
6. Tasks (links labels, priorities, assignees)
7. Time Logs
8. Comments

Attachments are imported separately via `teamwork:import-files` after the main import completes.

## Testing

### Standalone (package-level)

```bash
composer install
vendor/bin/phpunit
```

Tests use an in-memory SQLite database and `Http::fake()` to stub the Teamwork API. No real API credentials needed. Fixtures in `tests/Fixtures/` are modelled on the Teamwork v3 OpenAPI spec.

- **Unit tests** cover transformers (pure data mapping, no DB)
- **Feature tests** cover persisters, the import service, and the CLI command

### Host (against real LaraCollab models)

Validates that import output conforms to real `App\Models` schemas, fillables, casts, NOT NULL constraints, and observers. The `bin/host-test.sh` script auto-detects ddev, Laravel Sail, or native PHP.

```bash
bin/host-test.sh /path/to/lara-collab
```

Or manually with ddev:

```bash
cd /path/to/lara-collab
rsync -a --delete /path/to/standalone-repo/ packages/bigcalm/laracollab-teamwork-import/
ddev composer update bigcalm/laracollab-teamwork-import --no-interaction
ddev artisan vendor:publish --tag=teamwork-config --force
ddev exec vendor/bin/pest tests/Feature/TeamworkImportHostTest.php
```

Or manually with Laravel Sail:

```bash
cd /path/to/lara-collab
rsync -a --delete /path/to/standalone-repo/ packages/bigcalm/laracollab-teamwork-import/
sail composer update bigcalm/laracollab-teamwork-import --no-interaction
sail artisan vendor:publish --tag=teamwork-config --force
sail exec vendor/bin/pest tests/Feature/TeamworkImportHostTest.php
```
