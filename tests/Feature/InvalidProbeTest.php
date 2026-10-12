<?php

declare(strict_types=1);

namespace Mykholy\AccessProof\Tests\Feature;

use InvalidArgumentException;
use Mykholy\AccessProof\AccessProof;
use Mykholy\AccessProof\Exceptions\InvalidProbe;
use Mykholy\AccessProof\Exceptions\UnsafeEnvironment;
use Mykholy\AccessProof\Tests\Fixtures\Order;
use Mykholy\AccessProof\Tests\TestCase;
use PHPUnit\Framework\AssertionFailedError;

final class InvalidProbeTest extends TestCase
{
    public function test_request_is_required(): void
    {
        $this->expectException(InvalidProbe::class);
        AccessProof::request($this)->send();
    }

    public function test_only_one_request_is_allowed(): void
    {
        $this->expectException(InvalidProbe::class);
        AccessProof::request($this)->getJson('/one')->getJson('/two');
    }

    public function test_sent_probes_cannot_be_reused(): void
    {
        $probe = AccessProof::request($this)->getJson('/missing');
        $probe->send();

        $this->expectException(InvalidProbe::class);
        $probe->send();
    }

    public function test_watch_requires_a_persisted_row(): void
    {
        $this->expectException(InvalidProbe::class);
        AccessProof::request($this)->watch(new Order())->getJson('/missing')->send();
    }

    public function test_assert_unchanged_requires_an_explicit_watch(): void
    {
        $result = AccessProof::request($this)->getJson('/missing')->send();

        $this->expectException(AssertionFailedError::class);
        $result->assertUnchanged();
    }

    public function test_watch_rejects_unknown_database_columns(): void
    {
        $order = $this->order(2);

        $this->expectException(InvalidArgumentException::class);
        AccessProof::request($this)
            ->watch($order, ['non_existing_column'])
            ->getJson('/missing')
            ->send();
    }

    public function test_empty_request_uri_is_invalid(): void
    {
        $this->expectException(InvalidProbe::class);
        AccessProof::request($this)->getJson('');
    }

    public function test_empty_protected_marker_is_invalid(): void
    {
        $result = AccessProof::request($this)->getJson('/missing')->send();

        $this->expectException(InvalidArgumentException::class);
        $result->assertBodyDoesNotContain('');
    }

    public function test_production_environment_is_rejected(): void
    {
        $this->app['env'] = 'production';

        try {
            $this->expectException(UnsafeEnvironment::class);
            AccessProof::request($this)->getJson('/missing')->send();
        } finally {
            $this->app['env'] = 'testing';
        }
    }
}
