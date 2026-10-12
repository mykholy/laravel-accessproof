<?php

declare(strict_types=1);

namespace Mykholy\AccessProof\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

final class Order extends Model
{
    public $timestamps = false;

    protected $table = 'proof_orders';

    protected $guarded = [];
}
