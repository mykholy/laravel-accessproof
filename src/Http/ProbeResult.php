<?php

declare(strict_types=1);

namespace Mykholy\AccessProof\Http;

use Illuminate\Support\Testing\Fakes\EventFake;
use Illuminate\Support\Testing\Fakes\QueueFake;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use Mykholy\AccessProof\Database\ModelSnapshot;
use Mykholy\AccessProof\Database\TableCountSnapshot;
use Mykholy\AccessProof\Database\TableRowsSnapshot;
use Mykholy\AccessProof\Exceptions\InvalidProbe;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Response;

final class ProbeResult
{
    /**
     * @param  TestResponse<Response>  $response
     * @param  list<ModelSnapshot>  $snapshots
     * @param  list<TableCountSnapshot>  $counts
     * @param  list<TableRowsSnapshot>  $rowSets
     * @param  list<class-string>|null  $jobWatches
     * @param  list<string>|null  $eventWatches
     */
    public function __construct(
        private readonly TestResponse $response,
        private readonly array $snapshots,
        private readonly array $counts = [],
        private readonly array $rowSets = [],
        private readonly ?QueueFake $queueFake = null,
        private readonly ?array $jobWatches = null,
        private readonly ?EventFake $eventFake = null,
        private readonly ?array $eventWatches = null,
    ) {}

    /** @return TestResponse<Response> */
    public function response(): TestResponse
    {
        return $this->response;
    }

    public function assertDenied(): self
    {
        Assert::assertContains(
            $this->response->status(),
            [403, 404],
            'Expected denial (403 or 404); a missing route may also return 404. Add an owner control.',
        );

        return $this;
    }

    public function assertForbidden(): self
    {
        $this->response->assertStatus(403);

        return $this;
    }

    public function assertUnauthenticated(): self
    {
        $this->response->assertStatus(401);

        return $this;
    }

    public function assertAllowed(int $status = 200): self
    {
        $this->response->assertStatus($status);

        return $this;
    }

    public function assertBodyDoesNotContain(string $sentinel): self
    {
        if ($sentinel === '') {
            throw new InvalidArgumentException('The leak sentinel cannot be empty.');
        }

        Assert::assertStringNotContainsString(
            $sentinel,
            (string) $this->response->getContent(),
            'A protected marker appeared in the response body.',
        );

        return $this;
    }

    /**
     * Assert that no matching queued jobs were dispatched during the request.
     *
     * watchJobs() without classes additionally catches raw queue pushes.
     */
    public function assertNoJobsQueued(): self
    {
        if ($this->jobWatches === null || $this->queueFake === null) {
            throw new InvalidProbe('Call watchJobs() before send() to assert queued jobs.');
        }

        if ($this->jobWatches === []) {
            $this->queueFake->assertNothingPushed();
            Assert::assertCount(0, $this->queueFake->rawPushes(), 'Unexpected raw queue payloads were pushed.');

            return $this;
        }

        foreach ($this->jobWatches as $job) {
            $this->queueFake->assertNotPushed($job);
        }

        return $this;
    }

    /** Assert no watched events were dispatched during the request. */
    public function assertNoEventsDispatched(): self
    {
        if ($this->eventWatches === null || $this->eventFake === null) {
            throw new InvalidProbe('Call watchEvents() before send() to assert events.');
        }

        if ($this->eventWatches === []) {
            $this->eventFake->assertNothingDispatched();

            return $this;
        }

        foreach ($this->eventWatches as $event) {
            $this->eventFake->assertNotDispatched($event);
        }

        return $this;
    }

    /**
     * Check every explicitly registered database, queue and event watch.
     * Never treats an unwatched category as proof that it did not change.
     */
    public function assertNoSideEffects(): self
    {
        $hasDatabaseWatches = $this->snapshots !== [] || $this->counts !== [] || $this->rowSets !== [];

        if (! $hasDatabaseWatches && $this->jobWatches === null && $this->eventWatches === null) {
            throw new InvalidProbe('Register a watch(), watchCount(), watchRows(), watchJobs() or watchEvents() before asserting side effects.');
        }

        if ($hasDatabaseWatches) {
            $this->assertUnchanged();
        }

        if ($this->jobWatches !== null) {
            $this->assertNoJobsQueued();
        }

        if ($this->eventWatches !== null) {
            $this->assertNoEventsDispatched();
        }

        return $this;
    }

    public function assertUnchanged(): self
    {
        Assert::assertTrue(
            $this->snapshots !== [] || $this->counts !== [] || $this->rowSets !== [],
            'Register a watched model or row count before asserting unchanged state.',
        );

        foreach ($this->snapshots as $snapshot) {
            Assert::assertSame([], $snapshot->changedColumns(), sprintf(
                'Protected row %s changed.',
                $snapshot->label(),
            ));
        }

        foreach ($this->counts as $count) {
            Assert::assertNull($count->change(), 'Protected table row count changed.');
        }

        foreach ($this->rowSets as $rowSet) {
            Assert::assertFalse($rowSet->changed(), 'Protected '.$rowSet->label().' changed.');
        }

        return $this;
    }
}
