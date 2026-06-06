<?php

namespace LaraCollab\TeamworkImport\Tests\Stubs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Model
{
    protected $table = 'users';

    protected $fillable = [
        'name', 'email', 'password', 'first_name', 'last_name',
        'phone', 'job_title', 'rate', 'avatar',
    ];

    protected $hidden = ['password'];

    public function assignRole($role): void
    {
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_user');
    }
}
