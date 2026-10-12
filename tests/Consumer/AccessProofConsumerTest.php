<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Mykholy\AccessProof\AccessProof;
use Tests\TestCase;

final class AccessProofConsumerTest extends TestCase
{
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
