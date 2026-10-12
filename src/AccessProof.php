<?php

declare(strict_types=1);

namespace Mykholy\AccessProof;

use Illuminate\Foundation\Testing\TestCase;
use Mykholy\AccessProof\Http\PendingProbe;

final class AccessProof
{
    public static function request(TestCase $testCase): PendingProbe
    {
        return new PendingProbe($testCase);
    }
}
