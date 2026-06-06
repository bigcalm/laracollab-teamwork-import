<?php

namespace LaraCollab\TeamworkImport\Tests\Stubs\Models;

use Illuminate\Database\Eloquent\Model;

class Label extends Model
{
    protected $table = 'labels';

    protected $fillable = ['name', 'color'];

    public function tasks()
    {
        return $this->belongsToMany(Task::class, 'label_task');
    }
}
