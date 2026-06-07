<?php

namespace LaraCollab\TeamworkImport\Tests\Stubs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Task extends Model
{
    protected $table = 'tasks';

    protected $fillable = [
        'name', 'description', 'estimation', 'group_id', 'assigned_to_user_id',
        'project_id', 'client_company_id', 'created_by_user_id', 'priority_id',
        'number', 'order_column',
    ];

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'label_task');
    }

    public function subscribedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subscribe_task');
    }
}
