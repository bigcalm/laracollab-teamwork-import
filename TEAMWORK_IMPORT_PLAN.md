# Teamwork.com Import System — Package Plan

## Overview

A **self-contained Laravel package** that imports data from Teamwork.com API v3 directly into a LaraCollab instance. Published as a separate repository on Packagist — any LaraCollab instance can `composer require` it with zero app-level file modifications.

**Design principles:**
- **Zero files outside the package** — no edits to the host app's `app/`, `routes/`, `database/`, or `resources/`
- **CLI-only v1** — `php artisan teamwork:import` handles everything
- **Host-model mapping via config** — the host configures which model classes to use

---

## Installation Flow

```bash
composer require bigcalm/laracollab-teamwork-import

# Publish config (optional — sensible defaults for LaraCollab)
php artisan vendor:publish --tag=teamwork-config

# Publish migrations (auto-loaded, but user can publish to customize)
php artisan vendor:publish --tag=teamwork-migrations

# Run migrations
php artisan migrate

# Set env vars
TEAMWORK_API_SITE_NAME=mycompany
TEAMWORK_API_TOKEN=xxx
TEAMWORK_DEFAULT_ROLE=developer

# Run import
php artisan teamwork:import
```

---

## Package Structure

```
teamwork-import/
├── composer.json
├── README.md
├── src/
│   ├── TeamworkImportServiceProvider.php         # Auto-discovery registration
│   ├── Console/
│   │   └── ImportTeamworkCommand.php             # artisan teamwork:import
│   ├── Models/
│   │   ├── ImportRun.php                         # teamwork_import_runs
│   │   └── IdMapping.php                         # teamwork_id_mappings
│   ├── Services/
│   │   ├── ApiClient.php                         # HTTP client, auth, pagination
│   │   ├── ImportService.php                     # Orchestrator
│   │   ├── IdMappingService.php                  # Teamwork ID → local model
│   │   ├── Transformers/                         # API data → model attributes
│   │   │   ├── UserTransformer.php
│   │   │   ├── CompanyTransformer.php
│   │   │   ├── ProjectTransformer.php
│   │   │   ├── TaskListTransformer.php
│   │   │   ├── TagTransformer.php
│   │   │   ├── TaskTransformer.php
│   │   │   ├── TimeEntryTransformer.php
│   │   │   ├── CommentTransformer.php
│   │   │   └── FileTransformer.php
│   │   └── Persisters/                           # Fetch → transform → persist
│   │       ├── UserPersister.php
│   │       ├── CompanyPersister.php
│   │       ├── ProjectPersister.php
│   │       ├── LabelPersister.php
│   │       ├── TaskGroupPersister.php
│   │       ├── TaskPersister.php
│   │       ├── TimeLogPersister.php
│   │       ├── CommentPersister.php
│   │       └── AttachmentPersister.php
│   └── Database/
│       └── Migrations/
│           ├── create_teamwork_import_runs_table.php
│           └── create_teamwork_id_mappings_table.php
├── config/
│   └── teamwork.php                               # API settings, entity maps, model bindings
└── routes/
    └── (none — CLI only)
```

**Namespace**: `LaraCollab\TeamworkImport` (PSR-4 autoloaded in `composer.json`)

**`composer.json` dependencies:**
```json
{
    "name": "bigcalm/laracollab-teamwork-import",
    "require": {
        "php": "^8.2",
        "laravel/framework": "^11.0",
        "spatie/laravel-permission": "^6.9"
    },
    "extra": {
        "laravel": {
            "providers": ["LaraCollab\\TeamworkImport\\TeamworkImportServiceProvider"]
        }
    }
}
```

---

## Key Design: Model Binding

The package cannot hardcode `App\Models\User` because users may have different namespaces. Instead, the config ships with a `models` map that users can override after publishing:

```php
// config/teamwork.php
'models' => [
    'user'           => App\Models\User::class,
    'client_company' => App\Models\ClientCompany::class,
    'project'        => App\Models\Project::class,
    'task_group'     => App\Models\TaskGroup::class,
    'task'           => App\Models\Task::class,
    'label'          => App\Models\Label::class,
    'time_log'       => App\Models\TimeLog::class,
    'comment'        => App\Models\Comment::class,
    'attachment'     => App\Models\Attachment::class,
    'task_priority'  => App\Models\TaskPriority::class,
    'role'           => App\Models\Role::class,
    'import_run'     => LaraCollab\TeamworkImport\Models\ImportRun::class,
    'id_mapping'     => LaraCollab\TeamworkImport\Models\IdMapping::class,
],
```

Persisters resolve models at runtime:
```php
$modelClass = config("teamwork.models.{$entity}");
$modelClass::create($attributes);
```

---

## Architecture

```
┌──────────────┐     ┌───────────────────┐     ┌──────────────────┐
│ Teamwork API │────▶│  ApiClient         │────▶│  ImportService   │
│   v3         │     │  (HTTP, auth,      │     │  (orchestrator)  │
│              │     │   pagination)      │     │                  │
└──────────────┘     └───────────────────┘     └───────┬──────────┘
                                                          │
                      ┌───────────────────────────────────┤
                      │          order matters ───────────┤
                      ▼                                   ▼
            ┌─────────────────┐                 ┌──────────────────┐
            │ Transformers    │                 │ Persisters       │
            │ (API → Model    │                 │ (save to DB,     │
            │  attributes)    │                 │  track ID map)   │
            └─────────────────┘                 └──────────────────┘
```

---

## Service Provider (`TeamworkImportServiceProvider`)

| Responsibility | Mechanism |
|---|---|
| Register config | `mergeConfigFrom()` + publishable tag |
| Register migrations | `loadMigrationsFrom()` + publishable tag |
| Register command | `$this->commands([ImportTeamworkCommand::class])` |
| Auto-discovery | `composer.json` `extra.laravel.providers` entry |

No routes, no views, no controllers, no assets.

---

## Import Ordering (FK dependency chain)

| Step | Entity | Teamwork Resource | LaraCollab Model | Depends On |
|---|---|---|---|---|
| 1 | Users | `people.json` | `User` | — |
| 2 | Client Companies | `companies.json` | `ClientCompany` | — |
| 3 | Labels | `tags.json` | `Label` | — |
| 4 | Projects | `projects.json` | `Project` | Client companies |
| 5 | Task Groups | `tasklists.json` | `TaskGroup` | Projects |
| 6 | Project User Access | `projects/{id}/people.json` | `User` pivot | Projects, Users |
| 7 | Tasks | `projects/{id}/tasks.json` | `Task` | Projects, Task Groups, Users, Labels |
| 8 | Time Logs | `time.json` | `TimeLog` | Tasks, Users |
| 9 | Comments | `comments.json` | `Comment` | Tasks, Users |
| 10 | Attachments | `files.json` | `Attachment` | Tasks, Users |

> **Divergence:** Tasks are fetched **per-project** (`projects/{id}/tasks.json`) rather than globally. The global `tasks.json` endpoint returns tasks whose `tasklistId` references tasklists not covered by the global `tasklists.json` response, causing all tasks to be skipped.

---

## Service Layer

### `ApiClient.php`

- Constructor resolves base URL from env (`{site}.teamwork.com/projects/api/v3` or explicit `base_url`)
- Auth modes: `basic_token` (token as username, blank password) or `basic_credentials`
- Pagination: `page`/`pageSize` params, reads `meta.page.hasMore` or count-based fallback. **Total page count is unavailable** — the API's exposed `x-pages`/`x-records` headers are never sent.
- Retry on 429 with `Retry-After` header support and 5xx via Laravel HTTP client's `->retry()`
- Inter-page delay of 200ms to stay under rate limits
- Response extraction is robust: handles flat lists, `data`-wrapped lists, and singular-key wrappers (`{"tasks": {"task": [...]}}`)
- `setOnPageCallback()` reports live progress: `page`, `totalSoFar`, `totalPages` (when available)

> **Divergence:** Uses vibe-hub's `extractRecords` approach instead of hardcoded key mapping. Total pages are not available from the API despite declared headers — only `page N (records)` is shown.

### `IdMappingService.php`

- `find(teamworkId, teamworkType)` — returns local model instance or null
- `findOrFail(...)`
- `store(teamworkId, teamworkType, localModel, importRunId)` — creates mapping
- `bulkStore(Collection $mappings)` — batch insert

### `ImportService.php`

Orchestrator with progress reporting:
```
run(importRun):
  for each entity in config.entities.order (skipping completed):
    persister = resolve(entity)
    persister->run()
    report progress (before/page/after/done)
    collect skipped & rationalised records for summary
```

> **Divergence:** Accepts `$skipEntities` for resume support. Passes `$skipped` and `$rationalised` arrays through progress callbacks for the end-of-import summary report. Each persister returns `['imported' => N, 'fetched' => N, 'skipped' => [...], 'rationalised' => N]`.

---

## Transformers

One class per entity. Static `transform(array $apiData, array $fieldMap)` method that:
1. Applies the `field_map` from config — **first non-null value wins** (supports fallback entries)
2. Converts `estimateMinutes` to hours (÷60)
3. Concatenates `firstName`+`lastName` → `name`
4. Truncates task `name` to 255 characters (VARCHAR limit)
5. Returns cleaned attribute array

| Transformer | Key Mappings |
|---|---|
| `UserTransformer` | `firstName+lastName → name`, `userRate → rate`, `email → email`, `title → job_title` |
| `CompanyTransformer` | `name → name`, `addressOne → address`, `zip → postal_code` |
| `ProjectTransformer` | `name/description → same`, `companyId → client_company_id` |
| `TaskListTransformer` | `name → name`, `projectId → project_id` |
| `TagTransformer` | `name → name`, `color → color` |
| `TaskTransformer` | `name/description → same`, `estimateMinutes → estimation` (hours), `tasklistId → group_id`, `assigneeUserIds → assigned_to_user_id` (first user), `priority → priority_id`, `createdByUserId → created_by_user_id` |
| `TimeEntryTransformer` | `minutes → minutes`, `personId/userId/loggedByUserId → user_id`, `taskId → task_id` |
| `CommentTransformer` | `body → content`, `postedByUserId/personId/authorId → user_id`, `objectId → object_id` |
| `FileTransformer` | `originalName → name`, `size → size`, `downloadURL → path`, `uploadedBy/uploadedByUserID → user_id` |

> **Divergence:** Actual API field names differ from the original plan. Field names were determined by inspecting live API responses via diagnostic dumps. Multiple fallback field names are mapped to the same local attribute for resilience.

---

## Persisters

Each persister:
1. Fetches data from API via `ApiClient`
2. Transforms via the corresponding `Transformer`
3. Creates the LaraCollab model via `createModel()` (uses `Model::withoutEvents` to prevent observer/audit side effects)
4. Records the ID mapping
5. Returns `['imported' => N, 'fetched' => N, 'skipped' => [...], 'rationalised' => N]`

| Persister | Notes |
|---|---|
| `UserPersister` | Generates random password; assigns `default_role` from config; deduplicates by email |
| `CompanyPersister` | Creates client company records |
| `LabelPersister` | Deduplicates by name; counts rationalised labels in report |
| `ProjectPersister` | Links to client company via ID mapping; syncs `project_user_access` pivot |
| `TaskGroupPersister` | Assigns `order_column` sequentially; default color if missing |
| `TaskPersister` | **Fetches per project.** Maps priority, group, assignee, creator via ID mapping; creates `label_task` pivot; auto-generates `number`. Creates default "Imported" taskgroup for unmatched tasklistIds. |
| `TimeLogPersister` | Maps user/task via ID mapping; **creates placeholder `[deleted user (ID)]`** when user mapping missing |
| `CommentPersister` | Maps user/task via `objectId`/`objectType`; creates placeholder user for missing user mappings |
| `AttachmentPersister` | Metadata-only; checks `version.tasks` for task links (usually empty — project-level files skipped) |

> **Divergence:** `BasePersister::createModel()` uses `Model::withoutEvents()` to suppress observers and the Auditable trait. `BasePersister::resolveOrCreatePlaceholderUser()` creates `[deleted user (ID)]` with a unique email when no user mapping exists, preserving referential integrity. The `recordMapping` call within this method explicitly uses `'user'` as the teamwork type, not the persister's type.

All persisters receive `$importRun`, `$apiClient`, `$idMappingService` via constructor DI.

---

## Config Schema (`config/teamwork.php`)

```php
return [
    'models' => [ /* see model binding section above */ ],

    'api' => [
        'base_url'        => env('TEAMWORK_API_BASE_URL'),
        'token'           => env('TEAMWORK_API_TOKEN'),
        'auth_mode'       => env('TEAMWORK_API_AUTH_MODE', 'basic_token'),
        'username'        => env('TEAMWORK_API_USERNAME'),
        'password'        => env('TEAMWORK_API_PASSWORD'),
        'site_name'       => env('TEAMWORK_API_SITE_NAME'),
        'timeout'         => (int) env('TEAMWORK_API_TIMEOUT', 30),
        'connect_timeout' => (int) env('TEAMWORK_API_CONNECT_TIMEOUT', 10),
        'page_size'       => (int) env('TEAMWORK_API_PAGE_SIZE', 100),
    ],

    'default_role' => env('TEAMWORK_DEFAULT_ROLE', 'developer'),

    'entities' => [
        'users' => [
            'resource'  => 'people.json',
            'field_map' => [
                'id'        => 'teamwork_id',
                'firstName' => 'first_name',
                'lastName'  => 'last_name',
                'email'     => 'email',
                'phone'     => 'phone',
                'title'     => 'job_title',
                'userRate'  => 'rate',
                'avatarUrl' => 'avatar',
            ],
        ],
        // ... companies, tags, projects, tasklists, tasks, time, comments, files
        // Each entity has resource + field_map with fallback entries for user IDs
    ],

    'entity_order' => [
        'users', 'companies', 'tags', 'projects', 'tasklists',
        'project_people', 'tasks', 'time', 'comments', 'files',
    ],
];
```

> **Divergence:** The field_map entries differ significantly from the original plan. Key differences:
> - Users: `email` not `emailAddress`, `title` not `jobTitle`, `userRate` not `hourlyRate`, `avatar` not `avatar_url`
> - Tasks: `estimateMinutes` not `estimatedMinutes`, `assigneeUserIds` not `assignedUserIds`, added `createdByUserId`
> - Time: `userId`, `loggedByUserId` as fallbacks alongside `personId`
> - Comments: `postedByUserId` as primary, `personId`/`authorId` as fallbacks
> - Files: `originalName` not `name`, `size` not `latestFileVersionSize`, added `downloadURL`, `uploadedBy`/`uploadedByUserID`

---

## Database Migrations

**`create_teamwork_import_runs_table`**
```php
Schema::create('teamwork_import_runs', function (Blueprint $table) {
    $table->id();
    $table->string('status');                       // running | completed | failed | partial
    $table->json('entities_imported')->nullable();  // {"users":42, "tasks":150}
    $table->json('errors')->nullable();             // [{"entity":"tasks","teamwork_id":123,"error":"..."}]
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
});
```

**`create_teamwork_id_mappings_table`**
```php
Schema::create('teamwork_id_mappings', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('teamwork_id');
    $table->string('teamwork_type');                // user, company, project, tasklist, etc.
    $table->morphs('local');                        // local_id + local_type (the LaraCollab model)
    $table->foreignId('import_run_id')->constrained('teamwork_import_runs')->cascadeOnDelete();
    $table->timestamps();
    $table->unique(['teamwork_id', 'teamwork_type', 'local_type']);
    $table->index('import_run_id');
});
```

Migrations use the host app's default DB connection, loaded via `loadMigrationsFrom()` in the ServiceProvider.

---

## CLI Command

```
php artisan teamwork:import
  {--entities=}            Comma-separated subset (default: all)
  {--role=}                Override default role for imported users
  {--project=}             Only import a single Project ID from Teamwork
  {--queue}                Dispatch to queue for async processing
```

```bash
# Full import (all entities)
php artisan teamwork:import

# Partial: users + companies only
php artisan teamwork:import --entities=users,companies

# Single project
php artisan teamwork:import --project=456

# Dispatch to queue
php artisan teamwork:import --queue
```

**Progress output:**
- Per-entity line updates in-place: `Labels ⚠ 392/393 imported (some skipped)`
- Live page progress during pagination: `Time Logs page 136 (13600 records)...`
- End-of-import summary with:
  - **Rationalised** records (duplicates mapped to existing): `ℹ Labels: 10 duplicates mapped to existing records`
  - **Skipped** records grouped by reason: `Time Logs: 19668 records / - missing_task: 19660 records / - created_placeholder_user: 8 records`
  - **Errors** section (API failures, constraint violations)

> **Divergence:** Added per-page progress, resume detection, and detailed skipped/rationalised record reports not in the original plan.

---

## Package Models (`src/Models/`)

Eloquent models in `LaraCollab\TeamworkImport\Models` namespace, on the host's default DB connection:

- **`ImportRun`** — `$table = 'teamwork_import_runs'`; `$casts = ['entities_imported' => 'array', 'errors' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime']`
- **`IdMapping`** — `$table = 'teamwork_id_mappings'`; `$casts = ['teamwork_id' => 'integer']`; `morphTo` for `local`

> **Divergence:** `$table` properties added to both models — Laravel convention would pluralise `import_runs` and `id_mappings`, but the migrations use `teamwork_` prefix.

---

## What Users Must Configure

### `.env` variables

```
TEAMWORK_API_SITE_NAME=mycompany         # or:
TEAMWORK_API_BASE_URL=https://mycompany.teamwork.com/projects/api/v3
TEAMWORK_API_TOKEN=xxx
TEAMWORK_API_AUTH_MODE=basic_token       # basic_token | basic_credentials
TEAMWORK_API_USERNAME=                   # only if auth_mode=basic_credentials
TEAMWORK_API_PASSWORD=                   # only if auth_mode=basic_credentials
TEAMWORK_DEFAULT_ROLE=developer          # role assigned to imported users
```

### Existing LaraCollab state

The import assumes the host already has:
- Seeded task priorities (the 5 defaults from `TaskPrioritySeeder`)
- Seeded roles (`admin`, `manager`, `developer`, etc.)

> **Divergence:** No longer requires a default admin user. Users missing from the import set are created as `[deleted user (ID)]` placeholders to preserve referential integrity.

---

## Testing Strategy

### Test types

| Type | Layer | DB | HTTP | What's tested |
|---|---|---|---|---|
| Unit | Transformers | No | No | Field mapping, fallback logic, special coercions (name concat, estimateMinutes→hours, truncation) |
| Feature | Persisters | SQLite in-memory | `Http::fake()` | API calls, transformation, model creation, ID mapping, skip logic, placeholder users |
| Feature | ImportService | SQLite in-memory | `Http::fake()` | Entity ordering, skip-entity (resume), error handling, progress callbacks |
| Feature | Command | SQLite in-memory | `Http::fake()` | CLI flags, resume detection, output formatting |

### Test doubles

- **Stub models** in `tests/stubs/Models/` mirror the LaraCollab host models (minimal Eloquent models with only the columns the import touches). The config `models` map is overridden in `TestCase` to point at these stubs.
- **API responses** are JSON fixture files in `tests/fixtures/` modelled on the official Teamwork v3 OpenAPI spec (`view.User`, `company.CompaniesResponse`, `task.tasksResponseV205`, etc.). Pagination uses `meta.page.hasMore`.
- **`Http::fake()`** intercepts all outgoing API calls and returns fixture data. The `ApiClient` never reaches a real endpoint.

### Stub model relationships

| Model | Needed for |
|---|---|
| `User` | `assignRole()` (no-op), `belongsToMany('project')` |
| `Project` | `belongsToMany('user')` via `project_user` pivot |
| `Task` | `belongsToMany('label')` via `label_task` pivot |

### Fixture response structure

All fixtures follow the OpenAPI spec:
```json
{
  "people": [ ... ],
  "meta": { "page": { "hasMore": false, "pageOffset": 1, "pageSize": 100, "count": 2 } }
}
```

Or singular-wrapper where applicable: `{"tasks": {"task": [...]}}`.

### Known bugs captured by tests

`AttachmentPersister.php:36` references undefined variable `$taskIds` instead of `$versionTasks`. The `empty()` check on an undefined variable always returns `true`, so all files are skipped with `no_related_tasks`. The `foreach ($taskIds ...)` loop is dead code.

`AttachmentPersister.php:28-31` does not resolve `user_id` from a raw Teamwork ID to a local LaraCollab user ID. Files with an `uploadedBy` / `uploadedByUserID` value pass the null check but the raw value is stored directly.

`config/teamwork.php` tasks field_map is missing `createdByUserId`, leaving the `created_by_user_id` resolution logic in `TaskPersister` (`TaskPersister.php:63-67`) as dead code.

---

## Known Scope Limits

| Entity | Records | Imported | Skipped | Reason |
|---|---|---|---|---|
| Users | 137 | 137 | 0 | |
| Companies | 30 | 30 | 0 | |
| Labels | 393 | 392 | 1 | empty Teamwork tag name |
| Projects | 36 | 36 | 0 | |
| Task Groups | 161 | 161 | 0 | |
| Tasks | 1963 | 1963 | 0 | Fetched per project |
| Time Logs | 50000 | ~30340 | ~19660 | Missing task mappings (projects outside the 36 imported) |
| Comments | 16914 | ~2024 | ~14890 | Comments on non-task entities or tasks outside scope |
| Attachments | 4123 | 0 | 4123 | API does not surface task associations (`no_related_tasks`) |

Skipped records are reported in detail at the end of each import run, grouped by reason.

---

## Key Differences from vibe-hub

| Aspect | Vibe Hub | This Package |
|---|---|---|
| Deployment | In-app code | Separate Packagist package |
| Target DB | Separate `teamwork` connection (legacy schema) | Host app's models (configurable via `models` map) |
| Import mechanism | SQL dump ZIP → mysql CLI | Live API v3 → model transformers |
| ID tracking | Teamwork IDs = primary keys | `teamwork_id_mappings` bridging table |
| Pagination headers | Only `hasMore` used | Same — `x-pages`/`x-records` declared but never sent |
| Missing user handling | Unknown | Creates `[deleted user (ID)]` placeholder |
| Task fetch strategy | Global `tasks.json` | Per-project `projects/{id}/tasks.json` |
| API probing | Dedicated `teamwork:probe` command | No probe command (diagnostics via tinker) |
| Progress/UI | Filament admin panel + CLI progress | CLI only with per-page progress and summary report |
| Host coupling | Tight (app namespace, app DB) | Loose (model map + published config) |

---

## Suggested Implementation Order

| Step | What | Why |
|---|---|---|
| 1 | `composer.json` + `ServiceProvider` | Package skeleton |
| 2 | `config/teamwork.php` | Foundation — model bindings, field maps, entity order |
| 3 | Migrations + Models (`ImportRun`, `IdMapping`) | DB infrastructure |
| 4 | `ApiClient` | Gateway — needed by every persister |
| 5 | `IdMappingService` | FK resolution |
| 6 | `UserTransformer` + `UserPersister` | Lowest FK dependency |
| 7 | `CompanyTransformer` + `CompanyPersister` | Second-lowest |
| 8 | `LabelPersister` (tags) | Standalone |
| 9 | `ProjectTransformer` + `ProjectPersister` | Depends on companies + users |
| 10 | `TaskGroupTransformer` + `TaskGroupPersister` | Depends on projects |
| 11 | `TaskTransformer` + `TaskPersister` | Depends on everything above |
| 12 | `TimeEntryTransformer` + `TimeLogPersister` | Depends on tasks + users |
| 13 | `CommentTransformer` + `CommentPersister` | Depends on tasks + users |
| 14 | `FileTransformer` + `AttachmentPersister` | Depends on tasks + users |
| 15 | `ImportService` | Orchestrator |
| 16 | `ImportTeamworkCommand` | CLI entry point |
| 17 | README.md | Installation + usage docs |

---

## Future (Phase 2)

| Feature | Approach |
|---|---|
| Inertia UI page | Separate package `bigcalm/laracollab-teamwork-import-ui` with published JSX asset |
| Incremental sync | Store `last_synced_at`, pass `updatedAfter` to API, reuse persisters |
| File downloads | Requires separate file endpoint per attachment |
| Webhook receiver | Listen for Teamwork webhook events for near-real-time sync |
| Attachment task links | Query `tasks/{id}/files.json` per task to associate project files |

---

## References

- **Vibe Hub** (`vibe-hub`) — private repo — reusable patterns for: auth modes, URL resolution, pagination, field-map-based transformation, retry-on-429 logic
- **LaraCollab models + migrations** — target schemas for all import mappings
