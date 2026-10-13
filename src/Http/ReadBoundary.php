<?php

declare(strict_types=1);

namespace Mykholy\AccessProof\Http;

use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use Mykholy\AccessProof\Exceptions\InvalidProbe;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that the same GET endpoint is accessible to its owner but not an outsider.
 */
final class ReadBoundary
{
    private ?Authenticatable $owner = null;

    private ?Authenticatable $outsider = null;

    private ?string $ownerGuard = null;

    private ?string $outsiderGuard = null;

    private ?string $uri = null;

    private ?string $protectedMarker = null;

    private int $ownerStatus = 200;

    private bool $verified = false;

    public function __construct(private readonly TestCase $testCase) {}

    public function owner(Authenticatable $actor, ?string $guard = null): self
    {
        $this->assertPending();
        $this->owner = $actor;
        $this->ownerGuard = $guard;

        return $this;
    }

    public function outsider(Authenticatable $actor, ?string $guard = null): self
    {
        $this->assertPending();
        $this->outsider = $actor;
        $this->outsiderGuard = $guard;

        return $this;
    }

    public function getJson(string $uri): self
    {
        $this->assertPending();

        if ($uri === '') {
            throw new InvalidProbe('The request URI cannot be empty.');
        }

        $this->uri = $uri;

        return $this;
    }

    public function expectedOwnerStatus(int $status): self
    {
        $this->assertPending();

        if ($status < 200 || $status > 299) {
            throw new InvalidArgumentException('The owner must receive a successful 2xx status.');
        }

        $this->ownerStatus = $status;

        return $this;
    }

    public function withoutBodyMarker(string $marker): self
    {
        $this->assertPending();

        if ($marker === '') {
            throw new InvalidArgumentException('The protected marker cannot be empty.');
        }

        $this->protectedMarker = $marker;

        return $this;
    }

    public function verify(): void
    {
        $this->assertPending();

        if ($this->owner === null || $this->outsider === null || $this->uri === null) {
            throw new InvalidProbe('Define an owner, outsider and GET URI before verification.');
        }

        $this->verified = true;

        // A positive control prevents a missing route from passing as a denied read.
        (new PendingProbe($this->testCase))
            ->as($this->owner, $this->ownerGuard)
            ->getJson($this->uri)
            ->send()
            ->assertAllowed($this->ownerStatus);

        $outsider = (new PendingProbe($this->testCase))
            ->as($this->outsider, $this->outsiderGuard)
            ->getJson($this->uri)
            ->send()
            ->assertDenied();

        if ($this->protectedMarker !== null) {
            $outsider->assertBodyDoesNotContain($this->protectedMarker);
        }
    }

    private function assertPending(): void
    {
        if ($this->verified) {
            throw new InvalidProbe('A read boundary cannot be reused after verification.');
        }
    }
}
