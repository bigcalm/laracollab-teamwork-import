<?php

namespace LaraCollab\TeamworkImport\Tests\Stubs\Models;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    protected $table = 'comments';

    protected $fillable = ['content', 'user_id', 'task_id', 'project_id'];
}
