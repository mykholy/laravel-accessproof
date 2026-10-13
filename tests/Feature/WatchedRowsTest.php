<?php

declare(strict_types=1);

namespace Mykholy\AccessProof\Tests\Feature;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Mykholy\AccessProof\AccessProof;
use Mykholy\AccessProof\Exceptions\InvalidProbe;
use Mykholy\AccessProof\Tests\TestCase;
use PHPUnit\Framework\AssertionFailedError;

final class WatchedRowsTest extends TestCase
{
    public function test_denied_request_cannot_modify_an_unwatched_individual_record(): void
    {
        $this->order(2);
        $result = AccessProof::request($this)
            ->as($this->user(1))
            ->watchRows('proof_orders', ['tenant_id' => 2], ['status'])
            ->patchJson('/api/broken-bulk-orders')
            ->send()
            ->assertForbidden();

        $this->expectException(AssertionFailedError::class);
        $result->assertUnchanged();
    }

    public function test_denied_request_cannot_replace_one_row_with_another(): void
    {
        $this->order(2);

        $result = AccessProof::request($this)
            ->as($this->user(1))
            ->watchCount('proof_orders', ['tenant_id' => 2])
            ->watchRows('proof_orders', ['tenant_id' => 2])
            ->postJson('/api/broken-replacements')
            ->send()
            ->assertForbidden();

        $this->expectException(AssertionFailedError::class);
        $result->assertUnchanged();
    }

    public function test_count_watch_alone_cannot_detect_a_net_zero_replacement(): void
    {
        $this->order(2);

        AccessProof::request($this)
            ->as($this->user(1))
            ->watchCount('proof_orders', ['tenant_id' => 2])
            ->postJson('/api/broken-replacements')
            ->send()
            ->assertForbidden()
            ->assertUnchanged();
    }

    public function test_unchanged_scoped_rows_pass_after_denial(): void
    {
        $this->order(2);

        AccessProof::request($this)
            ->as($this->user(1))
            ->watchRows('proof_orders', ['tenant_id' => 2], ['status'])
            ->patchJson('/api/orders/999')
            ->send()
            ->assertDenied()
            ->assertUnchanged();
    }

    public function test_scoped_watch_with_no_matching_rows_detects_insertions(): void
    {
        $result = AccessProof::request($this)
            ->as($this->user(1))
            ->watchRows('proof_orders', ['tenant_id' => 2])
            ->postJson('/api/broken-orders', ['tenant_id' => 2])
            ->send()
            ->assertForbidden();

        $this->expectException(AssertionFailedError::class);
        $result->assertUnchanged();
    }

    public function test_row_watch_requires_a_scope(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AccessProof::request($this)
            ->watchRows('proof_orders', [])
            ->getJson('/missing')
            ->send();
    }

    public function test_row_watch_rejects_qualified_or_unsafe_identifiers(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AccessProof::request($this)
            ->watchRows('proof_orders', ['tenant_id' => 2], ['status; DROP TABLE proof_orders'])
            ->getJson('/missing')
            ->send();
    }

    public function test_row_watch_enforces_a_bounded_snapshot(): void
    {
        $rows = array_fill(0, 501, [
            'tenant_id' => 2,
            'status' => 'pending',
            'secret' => 'synthetic-marker',
        ]);

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('proof_orders')->insert($chunk);
        }

        $this->expectException(InvalidProbe::class);

        AccessProof::request($this)
            ->watchRows('proof_orders', ['tenant_id' => 2])
            ->getJson('/missing')
            ->send();
    }
}
