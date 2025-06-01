<?php

namespace EduLazaro\Larallow\Tests\Support\Models;

use EduLazaro\Larallow\Concerns\HasRoles;
use EduLazaro\Larallow\Concerns\HasPermissions;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasPermissions, HasRoles;

    protected $guarded = [];
    protected $table = 'users';
}
