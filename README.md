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
# Full import
php artisan teamwork:import

# Partial import
php artisan teamwork:import --entities=users,companies

# Single project
php artisan teamwork:import --project=456

# Override default role
php artisan teamwork:import --role=admin

# Dispatch to queue
php artisan teamwork:import --queue
```

## Import Order

1. Users
2. Client Companies
3. Labels (Tags)
4. Projects (syncs project-user access)
5. Task Groups
6. Tasks (links labels, priorities, assignees)
7. Time Logs
8. Comments
9. Attachments
