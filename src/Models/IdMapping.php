<?php

namespace LaraCollab\TeamworkImport\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class IdMapping extends Model
{
    protected $table = 'teamwork_id_mappings';

    protected $fillable = [
        'teamwork_id',
        'teamwork_type',
        'local_id',
        'local_type',
        'import_run_id',
    ];

    protected $casts = [
        'teamwork_id' => 'integer',
    ];

    public function local(): MorphTo
    {
        return $this->morphTo();
    }

    public function importRun()
    {
        return $this->belongsTo(ImportRun::class);
    }
}
