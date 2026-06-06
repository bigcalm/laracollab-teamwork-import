<?php

namespace LaraCollab\TeamworkImport\Tests\Stubs\Models;

use Illuminate\Database\Eloquent\Model;

class TaskGroup extends Model
{
    protected $table = 'task_groups';

    protected $fillable = ['name', 'project_id', 'color', 'order_column'];
}
