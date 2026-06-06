<?php

namespace LaraCollab\TeamworkImport\Tests\Stubs\Models;

use Illuminate\Database\Eloquent\Model;

class TimeLog extends Model
{
    protected $table = 'time_logs';

    protected $fillable = ['minutes', 'user_id', 'task_id', 'project_id'];
}
