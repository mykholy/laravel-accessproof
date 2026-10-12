<?php

declare(strict_types=1);

namespace Mykholy\AccessProof\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

final class User extends Authenticatable
{
    public $timestamps = false;

    protected $table = 'proof_users';

    protected $guarded = [];
}
