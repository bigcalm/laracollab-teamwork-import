<?php

namespace LaraCollab\TeamworkImport\Models;

use Illuminate\Database\Eloquent\Model;

class ImportRun extends Model
{
    protected $table = 'teamwork_import_runs';

    protected $fillable = [
        'status',
        'entities_imported',
        'errors',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'entities_imported' => 'array',
        'errors' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function idMappings()
    {
        return $this->hasMany(IdMapping::class);
    }
}
