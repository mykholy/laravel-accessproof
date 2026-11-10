<?php

declare(strict_types=1);

namespace Mykholy\AccessProof\Tests\Feature;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Mykholy\AccessProof\AccessProof;
use Mykholy\AccessProof\Exceptions\InvalidProbe;
use Mykholy\AccessProof\Tests\TestCase;
use PHPUnit\Framework\AssertionFailedError;

final class SideEffectProbeTest extends TestCase
{
    public function test_denial_with_no_queued_jobs_passes(): void
    {
        Route::post('/_proof/queue-clean', fn () => abort(403));

        AccessProof::request($this)
            ->watchJobs()
            ->postJson('/_proof/queue-clean')
            ->send()
            ->assertForbidden()
            ->assertNoJobsQueued()
            ->assertNoSideEffects();
    }

    public function test_denied_request_cannot_hide_a_queued_job(): void
    {
        Route::post('/_proof/queue-broken', function (): void {
            SideEffectTestJob::dispatch();
            abort(403);
        });

        $result = AccessProof::request($this)
            ->watchJobs()
            ->postJson('/_proof/queue-broken')
            ->send()
            ->assertForbidden();

        $this->expectException(AssertionFailedError::class);
        $result->assertNoJobsQueued();
    }

    public function test_queued_job_filter_checks_only_the_named_class(): void
    {
        Route::post('/_proof/queue-other', function (): void {
            OtherSideEffectTestJob::dispatch();
            abort(403);
        });

        AccessProof::request($this)
            ->watchJobs([SideEffectTestJob::class])
            ->postJson('/_proof/queue-other')
            ->send()
            ->assertForbidden()
            ->assertNoJobsQueued();
    }

    public function test_raw_queue_push_is_not_silently_ignored_for_watch_all(): void
    {
        Route::post('/_proof/raw-queue', function (): void {
            Queue::pushRaw('{"test":true}');
            abort(403);
        });

        $result = AccessProof::request($this)
            ->watchJobs()
            ->postJson('/_proof/raw-queue')
            ->send()
            ->assertForbidden();

        $this->expectException(AssertionFailedError::class);
        $result->assertNoJobsQueued();
    }

    public function test_denial_with_no_watched_events_passes(): void
    {
        Route::post('/_proof/event-clean', fn () => abort(403));

        AccessProof::request($this)
            ->watchEvents([SideEffectTestEvent::class])
            ->postJson('/_proof/event-clean')
            ->send()
            ->assertForbidden()
            ->assertNoEventsDispatched();
    }

    public function test_denied_request_cannot_hide_a_dispatched_event(): void
    {
        Route::post('/_proof/event-broken', function (): void {
            Event::dispatch(new SideEffectTestEvent);
            abort(403);
        });

        $result = AccessProof::request($this)
            ->watchEvents([SideEffectTestEvent::class])
            ->postJson('/_proof/event-broken')
            ->send()
            ->assertForbidden();

        $this->expectException(AssertionFailedError::class);
        $result->assertNoEventsDispatched();
    }

    public function test_watching_selected_events_does_not_suppress_unrelated_listeners(): void
    {
        $handled = false;

        Event::listen(OtherSideEffectTestEvent::class, function () use (&$handled): void {
            $handled = true;
        });

        Route::post('/_proof/other-event', function (): void {
            Event::dispatch(new OtherSideEffectTestEvent);
            abort(403);
        });

        AccessProof::request($this)
            ->watchEvents([SideEffectTestEvent::class])
            ->postJson('/_proof/other-event')
            ->send()
            ->assertForbidden()
            ->assertNoEventsDispatched();

        $this->assertTrue($handled);
    }

    public function test_combined_assertion_detects_a_queued_job_even_when_the_row_is_unchanged(): void
    {
        $order = $this->order(2);

        Route::post('/_proof/combined-queue', function (): void {
            SideEffectTestJob::dispatch();
            abort(403);
        });

        $result = AccessProof::request($this)
            ->watch($order, ['status'])
            ->watchJobs()
            ->watchEvents([SideEffectTestEvent::class])
            ->postJson('/_proof/combined-queue')
            ->send()
            ->assertForbidden();

        $this->expectException(AssertionFailedError::class);
        $result->assertNoSideEffects();
    }

    public function test_combined_assertion_passes_when_every_watched_category_is_clean(): void
    {
        $order = $this->order(2);
        Route::post('/_proof/combined-clean', fn () => abort(403));

        AccessProof::request($this)
            ->watch($order, ['status'])
            ->watchJobs()
            ->watchEvents([SideEffectTestEvent::class])
            ->postJson('/_proof/combined-clean')
            ->send()
            ->assertForbidden()
            ->assertNoSideEffects();
    }

    public function test_original_facades_and_model_dispatcher_are_restored(): void
    {
        $queue = Queue::getFacadeRoot();
        $events = Event::getFacadeRoot();
        $modelEvents = Model::getEventDispatcher();

        Route::post('/_proof/restore', fn () => abort(403));

        AccessProof::request($this)
            ->watchJobs()
            ->watchEvents([SideEffectTestEvent::class])
            ->postJson('/_proof/restore')
            ->send()
            ->assertForbidden()
            ->assertNoSideEffects();

        $this->assertSame($queue, Queue::getFacadeRoot());
        $this->assertSame($events, Event::getFacadeRoot());
        $this->assertSame($modelEvents, Model::getEventDispatcher());
    }

    public function test_existing_fakes_are_restored_after_the_probe(): void
    {
        Queue::fake();
        Event::fake();

        $queue = Queue::getFacadeRoot();
        $events = Event::getFacadeRoot();

        Route::post('/_proof/existing-fakes', fn () => abort(403));

        AccessProof::request($this)
            ->watchJobs()
            ->watchEvents([SideEffectTestEvent::class])
            ->postJson('/_proof/existing-fakes')
            ->send()
            ->assertForbidden()
            ->assertNoSideEffects();

        $this->assertSame($queue, Queue::getFacadeRoot());
        $this->assertSame($events, Event::getFacadeRoot());
    }

    public function test_queue_assertion_requires_an_explicit_watch(): void
    {
        $result = AccessProof::request($this)->getJson('/missing')->send();

        $this->expectException(InvalidProbe::class);
        $result->assertNoJobsQueued();
    }

    public function test_event_assertion_requires_an_explicit_watch(): void
    {
        $result = AccessProof::request($this)->getJson('/missing')->send();

        $this->expectException(InvalidProbe::class);
        $result->assertNoEventsDispatched();
    }

    public function test_combined_assertion_rejects_an_unwatched_probe(): void
    {
        $result = AccessProof::request($this)->getJson('/missing')->send();

        $this->expectException(InvalidProbe::class);
        $result->assertNoSideEffects();
    }
}

final class SideEffectTestJob implements ShouldQueue
{
    use Dispatchable;

    public function handle(): void {}
}

final class OtherSideEffectTestJob implements ShouldQueue
{
    use Dispatchable;

    public function handle(): void {}
}

final class SideEffectTestEvent {}

final class OtherSideEffectTestEvent {}
