<?php

declare(strict_types=1);

namespace Mykholy\AccessProof;

use Mykholy\AccessProof\Http\PendingProbe;
use PHPUnit\Framework\TestCase;

final class AccessProof
{
    public static function request(TestCase $testCase): PendingProbe
    {
        return new PendingProbe($testCase);
    }
}
