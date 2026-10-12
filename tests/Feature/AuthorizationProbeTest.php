<?php

declare(strict_types=1);

namespace Mykholy\AccessProof\Tests\Feature;

use Mykholy\AccessProof\AccessProof;
use Mykholy\AccessProof\Tests\TestCase;
use PHPUnit\Framework\AssertionFailedError;

final class AuthorizationProbeTest extends TestCase
{
    public function test_denied_cross_tenant_read_and_allowed_owner_access(): void
    {
        $outsider = $this->user(1);
        $owner = $this->user(2);
        $order = $this->order(2);

        AccessProof::request($this)
            ->as($outsider)
            ->getJson('/api/orders/'.$order->id)
            ->send()
            ->assertDenied()
            ->assertBodyDoesNotContain('CANARY-ORDER-SECRET-42');

        AccessProof::request($this)
            ->as($owner)
            ->getJson('/api/orders/'.$order->id)
            ->send()
            ->assertAllowed()
            ->response()
            ->assertJsonPath('id', $order->id);
    }

    public function test_insecure_endpoint_is_not_accepted_as_a_denial(): void
    {
        $outsider = $this->user(1);
        $order = $this->order(2);

        $this->expectException(AssertionFailedError::class);

        AccessProof::request($this)
            ->as($outsider)
            ->getJson('/api/exposed-orders/'.$order->id)
            ->send()
            ->assertDenied();
    }

    public function test_denied_update_cannot_modify_watched_columns(): void
    {
        $outsider = $this->user(1);
        $order = $this->order(2);

        AccessProof::request($this)
            ->as($outsider)
            ->watch($order, ['tenant_id', 'status'])
            ->patchJson('/api/orders/'.$order->id, ['status' => 'paid'])
            ->send()
            ->assertForbidden()
            ->assertUnchanged();
    }

    public function test_denial_does_not_hide_a_database_update(): void
    {
        $outsider = $this->user(1);
        $order = $this->order(2);

        $result = AccessProof::request($this)
            ->as($outsider)
            ->watch($order, ['status'])
            ->patchJson('/api/broken-orders/'.$order->id, ['status' => 'paid'])
            ->send()
            ->assertForbidden();

        $this->expectException(AssertionFailedError::class);
        $result->assertUnchanged();
    }

    public function test_denial_does_not_hide_a_deleted_row(): void
    {
        $outsider = $this->user(1);
        $order = $this->order(2);

        $result = AccessProof::request($this)
            ->as($outsider)
            ->watch($order)
            ->deleteJson('/api/broken-orders/'.$order->id)
            ->send()
            ->assertForbidden();

        $this->expectException(AssertionFailedError::class);
        $result->assertUnchanged();
    }

    public function test_denial_does_not_hide_insertions(): void
    {
        $outsider = $this->user(1);

        $result = AccessProof::request($this)
            ->as($outsider)
            ->watchCount('proof_orders', ['tenant_id' => 2])
            ->postJson('/api/broken-orders', ['tenant_id' => 2])
            ->send()
            ->assertForbidden();

        $this->expectException(AssertionFailedError::class);
        $result->assertUnchanged();
    }

    public function test_count_watch_passes_on_unchanged_count(): void
    {
        $outsider = $this->user(1);

        AccessProof::request($this)
            ->as($outsider)
            ->watchCount('proof_orders', ['tenant_id' => 2])
            ->patchJson('/api/orders/999', ['status' => 'paid'])
            ->send()
            ->assertDenied()
            ->assertUnchanged();
    }

    public function test_protected_marker_leak_is_detected(): void
    {
        $outsider = $this->user(1);
        $order = $this->order(2);

        $result = AccessProof::request($this)
            ->as($outsider)
            ->getJson('/api/exposed-orders/'.$order->id)
            ->send();

        $this->expectException(AssertionFailedError::class);
        $result->assertBodyDoesNotContain('CANARY-ORDER-SECRET-42');
    }

    public function test_guest_is_separate_from_authorization_failure(): void
    {
        $order = $this->order(2);

        AccessProof::request($this)
            ->getJson('/api/orders/'.$order->id)
            ->send()
            ->assertUnauthenticated();
    }
}
