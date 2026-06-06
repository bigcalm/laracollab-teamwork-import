<?php

namespace LaraCollab\TeamworkImport\Tests\Stubs\Models;

use Illuminate\Database\Eloquent\Model;

class ClientCompany extends Model
{
    protected $table = 'client_companies';

    protected $fillable = ['name', 'address', 'postal_code'];
}
