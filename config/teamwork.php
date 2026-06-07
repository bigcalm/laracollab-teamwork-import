<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Model Bindings
    |--------------------------------------------------------------------------
    |
    | Map entity types to their Eloquent model classes. Users can publish
    | this config and override any binding for their own namespace.
    |
    */
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

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    */
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

    /*
    |--------------------------------------------------------------------------
    | Default Role
    |--------------------------------------------------------------------------
    |
    | The Spatie role assigned to imported users.
    |
    */
    'default_role' => env('TEAMWORK_DEFAULT_ROLE', 'developer'),

    'client_role' => env('TEAMWORK_CLIENT_ROLE', 'client'),

    /*
    |--------------------------------------------------------------------------
    | Entity Configuration
    |--------------------------------------------------------------------------
    |
    | Each entity defines the API resource path and the mapping from Teamwork
    | API field names to LaraCollab model attribute names.
    |
    | Field names from the API use camelCase. When multiple API fields map
    | to the same local attribute, later entries serve as fallbacks.
    |
    */
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

        'companies' => [
            'resource'  => 'companies.json',
            'field_map' => [
                'id'         => 'teamwork_id',
                'name'       => 'name',
                'addressOne' => 'address',
                'zip'        => 'postal_code',
            ],
        ],

        'tags' => [
            'resource'  => 'tags.json',
            'field_map' => [
                'id'    => 'teamwork_id',
                'name'  => 'name',
                'color' => 'color',
            ],
        ],

        'projects' => [
            'resource'  => 'projects.json',
            'field_map' => [
                'id'          => 'teamwork_id',
                'name'        => 'name',
                'description' => 'description',
                'companyId'   => 'client_company_id',
            ],
        ],

        'tasklists' => [
            'resource'  => 'tasklists.json',
            'field_map' => [
                'id'        => 'teamwork_id',
                'name'      => 'name',
                'projectId' => 'project_id',
            ],
        ],

        'project_people' => [
            'resource'  => 'projects/{project_id}/people.json',
            'field_map' => [
                'id' => 'teamwork_id',
            ],
        ],

        'tasks' => [
            'resource'  => 'tasks.json',
            'field_map' => [
                'id'               => 'teamwork_id',
                'name'             => 'name',
                'description'      => 'description',
                'estimateMinutes'  => 'estimation',
                'tasklistId'       => 'group_id',
                'assigneeUserIds'  => 'assigned_to_user_id',
                'createdByUserId'  => 'created_by_user_id',
                'projectId'        => 'project_id',
                'companyId'        => 'client_company_id',
            ],
        ],

        'time' => [
            'resource'  => 'time.json',
            'field_map' => [
                'id'             => 'teamwork_id',
                'minutes'        => 'minutes',
                'personId'       => 'user_id',
                'userId'         => 'user_id',
                'loggedByUserId' => 'user_id',
                'taskId'         => 'task_id',
                'projectId'      => 'project_id',
            ],
        ],

        'comments' => [
            'resource'  => 'comments.json',
            'field_map' => [
                'id'             => 'teamwork_id',
                'body'           => 'content',
                'postedByUserId' => 'user_id',
                'postedBy'       => 'user_id',
                'personId'       => 'user_id',
                'authorId'       => 'user_id',
                'projectId'      => 'project_id',
                'objectId'       => 'object_id',
            ],
        ],

        'files' => [
            'resource'  => 'files.json',
            'field_map' => [
                'id'          => 'teamwork_id',
                'originalName' => 'name',
                'description' => 'description',
                'size'        => 'size',
                'downloadURL' => 'path',
                'projectId'   => 'project_id',
                'uploadedBy'  => 'user_id',
                'uploadedByUserID' => 'user_id',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Entity Import Order
    |--------------------------------------------------------------------------
    |
    | Determines the sequence in which entities are imported. This order
    | respects foreign-key dependencies (users before projects, etc.).
    |
    */
    'entity_order' => [
        'companies',
        'users',
        'tags',
        'projects',
        'tasklists',
        'project_people',
        'tasks',
        'time',
        'comments',
    ],
];
