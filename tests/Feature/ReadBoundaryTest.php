<?php

declare(strict_types=1);

namespace Mykholy\AccessProof\Tests\Feature;

use InvalidArgumentException;
use Mykholy\AccessProof\AccessProof;
use Mykholy\AccessProof\Exceptions\InvalidProbe;
use Mykholy\AccessProof\Tests\TestCase;
use PHPUnit\Framework\AssertionFailedError;

final class ReadBoundaryTest extends TestCase
{
    public function test_owner_and_outsider_are_verified_against_the_same_route(): void
    {
        $owner = $this->user(2);
        $outsider = $this->user(1);
        $order = $this->order(2);

        AccessProof::compareRead($this)
            ->owner($owner)
            ->outsider($outsider)
            ->getJson('/api/orders/'.$order->id)
            ->withoutBodyMarker('CANARY-ORDER-SECRET-42')
            ->verify();
    }

    public function test_missing_route_fails_even_when_an_outsider_would_receive_404(): void
    {
        $this->expectException(AssertionFailedError::class);

        AccessProof::compareRead($this)
            ->owner($this->user(2))
            ->outsider($this->user(1))
            ->getJson('/api/unknown-resource/123')
            ->verify();
    }

    public function test_publicly_exposed_resource_fails_the_outsider_check(): void
    {
        $order = $this->order(2);
        $this->expectException(AssertionFailedError::class);

        AccessProof::compareRead($this)
            ->owner($this->user(2))
            ->outsider($this->user(1))
            ->getJson('/api/exposed-orders/'.$order->id)
            ->verify();
    }

    public function test_denied_response_containing_a_secret_marker_fails(): void
    {
        $order = $this->order(2);

        $this->expectException(AssertionFailedError::class);

        AccessProof::compareRead($this)
            ->owner($this->user(2))
            ->outsider($this->user(1))
            ->getJson('/api/leaky-protected-orders/'.$order->id)
            ->withoutBodyMarker('CANARY-ORDER-SECRET-42')
            ->verify();
    }

    public function test_missing_actor_is_rejected_before_sending_requests(): void
    {
        $this->expectException(InvalidProbe::class);

        AccessProof::compareRead($this)
            ->owner($this->user(2))
            ->getJson('/api/orders/1')
            ->verify();
    }

    public function test_missing_uri_is_rejected_before_sending_requests(): void
    {
        $this->expectException(InvalidProbe::class);

        AccessProof::compareRead($this)
            ->owner($this->user(2))
            ->outsider($this->user(1))
            ->verify();
    }

    public function test_empty_uri_is_rejected(): void
    {
        $this->expectException(InvalidProbe::class);

        AccessProof::compareRead($this)->getJson('');
    }

    public function test_empty_marker_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AccessProof::compareRead($this)->withoutBodyMarker('');
    }

    public function test_unsuccessful_owner_status_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AccessProof::compareRead($this)->expectedOwnerStatus(404);
    }

    public function test_verified_boundary_cannot_be_reused(): void
    {
        $owner = $this->user(2);
        $outsider = $this->user(1);
        $order = $this->order(2);

        $boundary = AccessProof::compareRead($this)
            ->owner($owner)
            ->outsider($outsider)
            ->getJson('/api/orders/'.$order->id);

        $boundary->verify();

        $this->expectException(InvalidProbe::class);
        $boundary->verify();
    }
}
