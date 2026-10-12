<?php

declare(strict_types=1);

namespace Mykholy\AccessProof\Tests\Feature;

use Mykholy\AccessProof\AccessProof;
use Mykholy\AccessProof\Tests\TestCase;
use PHPUnit\Framework\AssertionFailedError;

final class ProbeResultTest extends TestCase
{
    public function test_a_missing_route_is_not_evidence_of_explicit_forbidden_access(): void
    {
        $result = AccessProof::request($this)->getJson('/api/does-not-exist')->send();

        $this->expectException(AssertionFailedError::class);
        $result->assertForbidden();
    }

    public function test_unauthenticated_access_is_not_a_cross_tenant_denial(): void
    {
        $order = $this->order(2);
        $result = AccessProof::request($this)->getJson('/api/orders/'.$order->id)->send();

        $this->expectException(AssertionFailedError::class);
        $result->assertDenied();
    }

    public function test_no_request_data_is_written_after_a_forbidden_patch(): void
    {
        $outsider = $this->user(1);
        $order = $this->order(2);

        AccessProof::request($this)
            ->as($outsider)
            ->watch($order)
            ->watchCount('proof_orders', ['tenant_id' => 2])
            ->patchJson('/api/orders/'.$order->id, ['status' => 'paid'])
            ->send()
            ->assertForbidden()
            ->assertUnchanged();
    }
}
