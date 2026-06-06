# LaraCollab Teamwork Import

Self-contained Laravel package that imports data from Teamwork.com API v3 into a LaraCollab instance.

## Setup

The package is installed into a LaraCollab host. Substitute `ddev` or `sail` below based on your environment.

```bash
cd /path/to/lara-collab
ddev composer require bigcalm/laracollab-teamwork-import:@dev   # or: sail composer require ...
ddev artisan vendor:publish --tag=teamwork-config --force         # or: sail artisan ...
ddev artisan migrate                                              # or: sail artisan ...
```

Development is done against the symlinked copy at `packages/bigcalm/laracollab-teamwork-import/src/`. An identical copy lives at `<standalone-repo>/src/` (the standalone repo). Keep both in sync with `rsync` or `cp` after edits.

## Build and test

- Lint: `find src -name '*.php' -exec php -l {} \;`
- Unit/feature tests (standalone): `vendor/bin/phpunit`
- Wipe and re-import: `ddev artisan db:wipe && ddev artisan migrate --seed && ddev artisan teamwork:import` — substitute `ddev` with `sail` if using Laravel Sail.
- Host tests (against real LaraCollab models): `bin/host-test.sh /path/to/lara-collab`
  - Auto-detects ddev, Laravel Sail, or native PHP.
  - The host test file lives at `tests/Feature/TeamworkImportHostTest.php` in the lara-collab repo.
  - Does not commit to the host repo.

## Code style

- Namespace: `LaraCollab\TeamworkImport`
- No docblocks on obvious methods. No `@param`/`@return` unless types cannot be declared.
- Config keys use `snake_case`. API field_map keys use the **exact camelCase** from the Teamwork v3 JSON response (determined by live dumps, not guesswork).
- Transformers are static classes with a single `transform(array $data, array $fieldMap): array` method.
- Persisters extend `BasePersister` and return `['imported' => N, 'fetched' => N, 'skipped' => [...]]`.
- Model creation uses `$this->createModel()` (wraps `Model::withoutEvents` to suppress observers/auditing).

## Architecture notes

- **Tasks are fetched per-project** — the global `tasks.json` returns tasks whose `tasklistId` may not appear in the global `tasklists.json` response. Each project's tasks are fetched via `projects/{id}/tasks.json`. See `TaskPersister`.
- **Placeholder users** — when a time log, comment, task assignee/creator, or attachment references a Teamwork user not in the import set, `BasePersister::resolveOrCreatePlaceholderUser()` creates `[deleted user (ID)]` with a unique email to preserve referential integrity. The mapping uses `'user'` as the teamwork type, not the persister's type.
- **Duplicate labels** — labels with matching names are mapped to the existing record via `IdMappingService`, not duplicated. Counted as `rationalised` in the output report.
- **Field_map fallbacks** — multiple API fields can map to the same local attribute. The first non-null value wins (transformer checks `array_key_exists` before setting). This handles API response variance.
- **Skipped records report** — each persister returns a `skipped` array of `['id' => teamworkId, 'reason' => 'string']`. The `done` phase groups by reason in the CLI output. IDs shown only for groups ≤20; larger groups show the count.
- **Resume** — the command checks for `ImportRun` records in `running`/`partial` status. Completed entities (tracked in `entities_imported`) are skipped on resume.

## API client specifics

- Base URL: `{site}.teamwork.com/projects/api/v3` (from `TEAMWORK_API_SITE_NAME` or `TEAMWORK_API_BASE_URL`)
- Auth: `basic_token` (token as username, blank password) or `basic_credentials`
- The API declares `access-control-expose-headers: x-page,x-pages,x-records` but **never sends those headers** — page totals are unavailable.
- Rate limit: 150 requests per 60 seconds. Client adds a 200ms delay between pages. Retry handles 429 with `Retry-After`.
- Response structure: top-level list key (plural entity name, e.g. `projects`, `people`, `timelogs`), with optional `meta.page.hasMore` for pagination. Some endpoints wrap items in a singular key (`{"tasks": {"task": [...]}}`) — `extractRecords` handles both.

## Known field name differences from the Teamwork v3 API

These were determined by inspecting live API responses and differ from common assumptions:

| Entity | Field | Notes |
|---|---|---|
| Users | `email` (not `emailAddress`), `title` (not `jobTitle`), `userRate` (not `hourlyRate`) | |
| Tasks | `estimateMinutes`, `assigneeUserIds`, `createdByUserId` | |
| Time | `userId`, `loggedByUserId` (fallbacks alongside `personId`) | |
| Comments | `postedByUserId` (primary), `personId`/`authorId` (fallbacks) | |
| Files | `originalName` (not `name`), `uploadedBy`/`uploadedByUserID`, `downloadURL` | |

## Gotchas

- **Config caching** — the published `config/teamwork.php` takes precedence over the package file. After changing field maps, run `ddev artisan vendor:publish --tag=teamwork-config --force` to sync (substitute `sail` for `ddev` as needed).
- **`allRationalised` variable** — must be declared, populated, and passed through the `done` callback in three separate locations. Missing any one causes `Undefined variable` or `Undefined array key` errors.
- **`recordMapping` inside `resolveOrCreatePlaceholderUser`** — must use `'user'` as the teamwork type, not `$this->teamworkType`. Otherwise the mapping stores `(id, time_entry, User)` and the find by `(id, user)` fails.
- **Two copies** — edits in `<standalone-repo>/src/` must be synced to `packages/bigcalm/laracollab-teamwork-import/src/` (the ddev-visible copy). Use `rsync -a --delete` or `cp`.
- **Attachments require task links** — the `attachments` table has `NOT NULL` on `task_id` but the files API does not return task associations. All project-level files are skipped with `no_related_tasks`.
- **Never commit changes in the lara-collab host repo** — only the standalone package repo should receive commits.
- **Never commit without being instructed** — do not create, amend, or push commits unless the user explicitly asks.
