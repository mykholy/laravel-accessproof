<?php

declare(strict_types=1);

namespace Mykholy\AccessProof\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Mykholy\AccessProof\Tests\Fixtures\Order;
use Mykholy\AccessProof\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('proof_orders');
        Schema::dropIfExists('proof_users');

        Schema::create('proof_users', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
        });

        Schema::create('proof_orders', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->string('status');
            $table->string('secret');
        });

        Route::middleware('auth')->group(function (): void {
            Route::get('/api/orders/{order}', function (int $order) {
                $model = Order::query()->findOrFail($order);

                abort_unless($model->tenant_id === auth()->user()->tenant_id, 404);

                return response()->json($model);
            });

            Route::get('/api/exposed-orders/{order}', function (int $order) {
                return response()->json(Order::query()->findOrFail($order));
            });

            // Deliberately vulnerable: a denied response still leaks a protected value.
            Route::get('/api/leaky-denials/{order}', function (int $order) {
                $model = Order::query()->findOrFail($order);

                return response()->json([
                    'message' => 'Forbidden',
                    'debug' => $model->secret,
                ], 403);
            });

            Route::delete('/api/broken-orders/{order}', function (int $order) {
                Order::query()->findOrFail($order)->delete();
                abort(403);
            });

            Route::post('/api/broken-orders', function () {
                Order::query()->create([
                    'tenant_id' => (int) request()->input('tenant_id'),
                    'status' => 'pending',
                    'secret' => 'new-order',
                ]);

                abort(403);
            });

            Route::patch('/api/broken-orders/{order}', function (int $order) {
                Order::query()->findOrFail($order)->update(['status' => 'paid']);
                abort(403);
            });

            Route::patch('/api/orders/{order}', function (int $order) {
                $model = Order::query()->findOrFail($order);
                abort_unless($model->tenant_id === auth()->user()->tenant_id, 403);

                $model->update(['status' => 'paid']);

                return response()->json(['ok' => true]);
            });
        });
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $driver = getenv('ACCESSPROOF_TEST_DRIVER') ?: 'sqlite';

        $connection = match ($driver) {
            'sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
            'mysql' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => 3306,
                'database' => 'accessproof_tests',
                'username' => 'root',
                'password' => 'root',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
            ],
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => '127.0.0.1',
                'port' => 5432,
                'database' => 'accessproof_tests',
                'username' => 'postgres',
                'password' => 'postgres',
                'charset' => 'utf8',
                'prefix' => '',
            ],
            default => throw new \InvalidArgumentException('Unsupported test database driver.'),
        };

        $app['config']->set('database.connections.testing', $connection);
    }

    protected function user(int $tenant): User
    {
        return User::query()->create(['tenant_id' => $tenant]);
    }

    protected function order(int $tenant): Order
    {
        return Order::query()->create([
            'tenant_id' => $tenant,
            'status' => 'pending',
            'secret' => 'CANARY-ORDER-SECRET-42',
        ]);
    }
}
