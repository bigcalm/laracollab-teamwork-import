<?php

namespace LaraCollab\TeamworkImport\Tests\Stubs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Project extends Model
{
    protected $table = 'projects';

    protected $fillable = ['name', 'description', 'client_company_id'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user');
    }
}
