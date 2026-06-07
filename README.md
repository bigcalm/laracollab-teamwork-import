# LaraCollab Teamwork Import

Import data from Teamwork.com API v3 into LaraCollab. CLI-only v1 — `php artisan teamwork:import` handles everything.

## Installation

```bash
composer require bigcalm/laracollab-teamwork-import

php artisan vendor:publish --tag=teamwork-config
php artisan migrate
```

## Environment

```bash
TEAMWORK_API_SITE_NAME=mycompany
TEAMWORK_API_TOKEN=xxx
TEAMWORK_DEFAULT_ROLE=developer
TEAMWORK_CLIENT_ROLE=client
TEAMWORK_CLIENT_BY_COMPANY=true
```

| Variable | Default | Description |
|---|---|---|
| `TEAMWORK_API_SITE_NAME` | — | Teamwork site name (e.g. `mycompany` → `mycompany.teamwork.com`) |
| `TEAMWORK_API_TOKEN` | — | API token for basic auth |
| `TEAMWORK_DEFAULT_ROLE` | `developer` | Spatie role assigned to non-client users |
| `TEAMWORK_CLIENT_ROLE` | `client` | Spatie role assigned to client users |
| `TEAMWORK_CLIENT_BY_COMPANY` | `false` | When `true`, any user with a resolved `companyId` gets the client role, even without the `isClientUser` API flag |

## Usage

### Main import: `teamwork:import`

Imports all data except project files. Entities are processed in dependency order (see [Import Order](#import-order)).

```bash
# Full import (all entities)
php artisan teamwork:import

# Partial import (comma-separated entity keys)
# Valid keys: companies, users, tags, projects, tasklists, tasks, time, comments
php artisan teamwork:import --entities=users,companies

# Single Teamwork project
php artisan teamwork:import --project=456

# Override role for all imported users (overrides both default_role and client_role)
php artisan teamwork:import --role=admin

# Dispatch to queue
php artisan teamwork:import --queue
```

### File import: `teamwork:import-files`

Imports project files and links them to tasks. Must be run after `teamwork:import` so project and task mappings exist.

```bash
# Import files for all projects
php artisan teamwork:import-files

# Import files for specific Teamwork project IDs
php artisan teamwork:import-files --project=67133,498141
```

## Import Order

1. **Client Companies** — creates `ClientCompany` records
2. **Users** — creates `User` records, syncs client company pivot
3. **Labels (Tags)** — creates `Label` records, deduplicates by name
4. **Projects** — creates `Project` records linked to client companies, syncs project-user access
5. **Task Groups** — creates `TaskGroup` records linked to projects
6. **Tasks** — creates `Task` records linked to projects/groups/users, maps Teamwork priorities to LaraCollab priorities
7. **Time Logs** — fetches per project (`projects/{id}/time.json`), creates `TimeLog` records linked to tasks and users
8. **Comments** — creates `Comment` records linked to tasks and users

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

Or manually:

```bash
cd /path/to/lara-collab
rsync -a --delete /path/to/standalone-repo/ packages/bigcalm/laracollab-teamwork-import/
php composer update bigcalm/laracollab-teamwork-import --no-interaction
php artisan vendor:publish --tag=teamwork-config --force
php vendor/bin/pest tests/Feature/TeamworkImportHostTest.php
```
