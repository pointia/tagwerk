<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * During a rollout two containers run a scheduler side by side; only the lock
 * of onOneServer() keeps a task from running twice.
 */
final class ScheduleTest extends TestCase
{
    public function test_every_scheduled_task_runs_on_one_server(): void
    {
        $events = $this->app->make(Schedule::class)->events();

        $this->assertNotEmpty($events);

        foreach ($events as $event) {
            $this->assertTrue($event->onOneServer, $event->command.' runs without ->onOneServer()');
        }
    }
}
