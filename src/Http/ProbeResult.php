<?php

declare(strict_types=1);

namespace Mykholy\AccessProof\Http;

use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use Mykholy\AccessProof\Database\ModelSnapshot;
use Mykholy\AccessProof\Database\TableCountSnapshot;
use PHPUnit\Framework\Assert;

final class ProbeResult
{
    /**
     * @param TestResponse<\Symfony\Component\HttpFoundation\Response> $response
     * @param list<ModelSnapshot> $snapshots
     * @param list<TableCountSnapshot> $counts
     */
    public function __construct(
        private readonly TestResponse $response,
        private readonly array $snapshots,
        private readonly array $counts = [],
    ) {
    }

    /** @return TestResponse<\Symfony\Component\HttpFoundation\Response> */
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

    public function assertUnchanged(): self
    {
        Assert::assertTrue(
            $this->snapshots !== [] || $this->counts !== [],
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

        return $this;
    }
}
