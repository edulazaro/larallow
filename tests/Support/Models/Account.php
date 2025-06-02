<?php

namespace EduLazaro\Larallow\Tests\Support\Models;

use EduLazaro\Larallow\Concerns\IsRoleTenant;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Account extends Authenticatable
{
    use IsRoleTenant;

    protected $guarded = [];
    protected $table = 'accounts';
}
