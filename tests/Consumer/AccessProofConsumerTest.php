<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Mykholy\AccessProof\AccessProof;
use Tests\TestCase;

final class AccessProofConsumerTest extends TestCase
{
    public function test_guest_is_rejected_by_the_host_application(): void
    {
        Route::middleware('auth')->get('/_accessproof/private', fn () => response()->json(['ok' => true]));

        AccessProof::request($this)
            ->getJson('/_accessproof/private')
            ->send()
            ->assertUnauthenticated();
    }

    public function test_installed_package_compares_owner_and_outsider_access(): void
    {
        Route::middleware('auth')->get('/_accessproof/guarded/{owner}', function (string $owner) {
            abort_unless((string) auth()->id() === $owner, 404);

            return response()->json(['value' => 'PRIVATE_MARKER_1']);
        });

        AccessProof::compareRead($this)
            ->owner(new GenericUser(['id' => 20]))
            ->outsider(new GenericUser(['id' => 10]))
            ->getJson('/_accessproof/guarded/20')
            ->withoutBodyMarker('PRIVATE_MARKER_1')
            ->verify();
    }

    public function test_installed_package_watches_scoped_database_rows(): void
    {
        Schema::create('_accessproof_fixture_orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('status');
        });

        $orderId = DB::table('_accessproof_fixture_orders')->insertGetId([
            'tenant_id' => 91,
            'status' => 'pending',
        ]);

        Route::middleware('auth')->post('/_accessproof/fixture-orders/{order}', function (int $order) {
            abort_unless((int) auth()->id() === $order, 403);

            DB::table('_accessproof_fixture_orders')
                ->where('id', $order)
                ->update(['status' => 'paid']);

            return response()->json(['ok' => true]);
        });

        AccessProof::request($this)
            ->as(new GenericUser(['id' => 123]))
            ->watchRows('_accessproof_fixture_orders', ['tenant_id' => 91], ['status'])
            ->postJson('/_accessproof/fixture-orders/'.$orderId)
            ->send()
            ->assertForbidden()
            ->assertUnchanged();

        $this->assertSame('pending', DB::table('_accessproof_fixture_orders')->value('status'));
    }

    public function test_installed_package_executes_real_laravel_routes(): void
    {
        Route::middleware('auth')->get('/_accessproof/orders/{owner}', function (string $owner) {
            abort_unless((string) auth()->id() === $owner, 404);

            return response()->json(['value' => 'ACCESSPROOF_PRIVATE_MARKER_1']);
        });

        $outsider = new GenericUser(['id' => 10, 'name' => 'outsider']);
        $owner = new GenericUser(['id' => 20, 'name' => 'owner']);

        AccessProof::request($this)
            ->as($outsider)
            ->getJson('/_accessproof/orders/20')
            ->send()
            ->assertDenied()
            ->assertBodyDoesNotContain('ACCESSPROOF_PRIVATE_MARKER_1');

        AccessProof::request($this)
            ->as($owner)
            ->getJson('/_accessproof/orders/20')
            ->send()
            ->assertAllowed()
            ->response()
            ->assertJsonPath('value', 'ACCESSPROOF_PRIVATE_MARKER_1');
    }
}
