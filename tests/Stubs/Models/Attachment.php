<?php

namespace LaraCollab\TeamworkImport\Tests\Stubs\Models;

use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    protected $table = 'attachments';

    protected $fillable = ['name', 'description', 'size', 'path', 'type', 'task_id', 'user_id'];
}
