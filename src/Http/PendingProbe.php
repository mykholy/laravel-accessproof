<?php

declare(strict_types=1);

namespace Mykholy\AccessProof\Http;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Testing\Fakes\EventFake;
use Illuminate\Support\Testing\Fakes\QueueFake;
use Illuminate\Testing\TestResponse;
use Mykholy\AccessProof\Database\ModelSnapshot;
use Mykholy\AccessProof\Database\TableCountSnapshot;
use Mykholy\AccessProof\Database\TableRowsSnapshot;
use Mykholy\AccessProof\Exceptions\InvalidProbe;
use Mykholy\AccessProof\Exceptions\UnsafeEnvironment;
use PHPUnit\Framework\TestCase;

final class PendingProbe
{
    private ?Authenticatable $actor = null;

    private ?string $guard = null;

    /** @var array<string, string> */
    private array $headers = [];

    /** @var array{method: string, uri: string, data: array<string, mixed>}|null */
    private ?array $request = null;

    /** @var list<array{model: Model, columns: list<string>}> */
    private array $watches = [];

    /** @var list<array{table: string, where: array<string, int|float|string|bool|null>, connection: ?string}> */
    private array $countWatches = [];

    /** @var list<array{table: string, where: array<string, int|float|string|bool|null>, columns: list<string>, connection: ?string, keyColumn: string}> */
    private array $rowWatches = [];

    /** @var list<string>|null Null means disabled; an empty list watches all queued jobs. */
    private ?array $jobWatches = null;

    /** @var list<string>|null Null means disabled; an empty list watches all events. */
    private ?array $eventWatches = null;

    private bool $sent = false;

    private readonly Closure $actingAs;

    private readonly Closure $json;

    public function __construct(TestCase $testCase)
    {
        if (! is_callable([$testCase, 'json']) || ! is_callable([$testCase, 'actingAs'])) {
            throw new InvalidProbe('AccessProof requires a Laravel Feature TestCase with json() and actingAs().');
        }

        $this->actingAs = Closure::fromCallable([$testCase, 'actingAs']);
        $this->json = Closure::fromCallable([$testCase, 'json']);
    }

    public function as(Authenticatable $actor, ?string $guard = null): self
    {
        $this->assertPending();
        $this->actor = $actor;
        $this->guard = $guard;

        return $this;
    }

    /** @param array<string, string> $headers */
    public function withHeaders(array $headers): self
    {
        $this->assertPending();
        $this->headers = array_replace($this->headers, $headers);

        return $this;
    }

    /** @param list<string> $columns */
    public function watch(Model $model, array $columns = []): self
    {
        $this->assertPending();
        $this->watches[] = ['model' => $model, 'columns' => $columns];

        return $this;
    }

    /** @param array<string, int|float|string|bool|null> $where */
    public function watchCount(string $table, array $where = [], ?string $connection = null): self
    {
        $this->assertPending();
        $this->countWatches[] = ['table' => $table, 'where' => $where, 'connection' => $connection];

        return $this;
    }

    /**
     * Watch the identities and values of matching rows, not just their count.
     *
     * @param  array<string, int|float|string|bool|null>  $where
     * @param  list<string>  $columns
     */
    public function watchRows(
        string $table,
        array $where,
        array $columns = [],
        ?string $connection = null,
        string $keyColumn = 'id',
    ): self {
        $this->assertPending();
        $this->rowWatches[] = compact('table', 'where', 'columns', 'connection', 'keyColumn');

        return $this;
    }

    /**
     * Intercept queued jobs for this request. An empty list watches every job;
     * a list checks only those classes, while all jobs remain faked.
     *
     * @param  list<class-string>  $jobs
     */
    public function watchJobs(array $jobs = []): self
    {
        $this->assertPending();
        $this->jobWatches = $jobs;

        return $this;
    }

    /**
     * Intercept selected events (or every event when no list is supplied).
     * Other events still run their listeners when a list is provided.
     *
     * @param  list<string>  $events
     */
    public function watchEvents(array $events = []): self
    {
        $this->assertPending();
        $this->eventWatches = $events;

        return $this;
    }

    public function getJson(string $uri): self
    {
        return $this->setRequest('GET', $uri);
    }

    /** @param array<string, mixed> $data */
    public function postJson(string $uri, array $data = []): self
    {
        return $this->setRequest('POST', $uri, $data);
    }

    /** @param array<string, mixed> $data */
    public function putJson(string $uri, array $data = []): self
    {
        return $this->setRequest('PUT', $uri, $data);
    }

    /** @param array<string, mixed> $data */
    public function patchJson(string $uri, array $data = []): self
    {
        return $this->setRequest('PATCH', $uri, $data);
    }

    /** @param array<string, mixed> $data */
    public function deleteJson(string $uri, array $data = []): self
    {
        return $this->setRequest('DELETE', $uri, $data);
    }

    public function send(): ProbeResult
    {
        $this->assertPending();

        if ($this->request === null) {
            throw new InvalidProbe('Specify an HTTP request before sending.');
        }

        if (! app()->environment('testing')) {
            throw new UnsafeEnvironment('AccessProof runs only in Laravel testing environments.');
        }

        $this->sent = true;
        $snapshots = [];

        foreach ($this->watches as $watch) {
            $snapshots[] = ModelSnapshot::capture($watch['model'], $watch['columns']);
        }

        $counts = [];

        foreach ($this->countWatches as $watch) {
            $counts[] = TableCountSnapshot::capture(
                DB::connection($watch['connection']),
                $watch['table'],
                $watch['where'],
            );
        }

        $rowSets = [];

        foreach ($this->rowWatches as $watch) {
            $rowSets[] = TableRowsSnapshot::capture(
                DB::connection($watch['connection']),
                $watch['table'],
                $watch['where'],
                $watch['columns'],
                $watch['keyColumn'],
            );
        }

        if ($this->actor !== null) {
            ($this->actingAs)($this->actor, $this->guard);
        }

        $queueFake = null;
        $eventFake = null;

        $execute = function () use (&$queueFake, &$eventFake) {
            if ($this->jobWatches !== null) {
                $queueFake = Queue::getFacadeRoot();

                if (! $queueFake instanceof QueueFake) {
                    throw new InvalidProbe('Laravel did not install the expected QueueFake.');
                }
            }

            if ($this->eventWatches !== null) {
                $eventFake = Event::getFacadeRoot();

                if (! $eventFake instanceof EventFake) {
                    throw new InvalidProbe('Laravel did not install the expected EventFake.');
                }
            }

            return ($this->json)(
                $this->request['method'],
                $this->request['uri'],
                $this->request['data'],
                $this->headers,
            );
        };

        // Laravel's scoped fakes restore the original dispatchers even if
        // the in-process request throws. Nothing leaks into later probes.
        $withEvents = fn () => $this->eventWatches !== null
            ? Event::fakeFor($execute, $this->eventWatches)
            : $execute();

        $response = $this->jobWatches !== null
            ? Queue::fakeFor($withEvents)
            : $withEvents();

        if (! $response instanceof TestResponse) {
            throw new InvalidProbe('Laravel json() must return an Illuminate TestResponse.');
        }

        return new ProbeResult(
            $response,
            $snapshots,
            $counts,
            $rowSets,
            $queueFake,
            $this->jobWatches,
            $eventFake,
            $this->eventWatches,
        );
    }

    /** @param array<string, mixed> $data */
    private function setRequest(string $method, string $uri, array $data = []): self
    {
        $this->assertPending();

        if ($this->request !== null) {
            throw new InvalidProbe('One probe can issue exactly one request.');
        }

        if ($uri === '') {
            throw new InvalidProbe('The request URI cannot be empty.');
        }

        $this->request = ['method' => $method, 'uri' => $uri, 'data' => $data];

        return $this;
    }

    private function assertPending(): void
    {
        if ($this->sent) {
            throw new InvalidProbe('A probe cannot be reused after sending.');
        }
    }
}
