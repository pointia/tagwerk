<?php

declare(strict_types=1);

namespace Tests\Feature\Wall;

use App\Jobs\AnnounceHouseholdUpdateJob;
use App\Models\Household;
use App\Services\DevicePairingService;
use App\Support\HouseholdContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Every announced change makes wall and phone fetch everything again. A read
 * that saves something — even an unchanged model, whose save() still fires
 * `saved` — would announce itself, and the devices would fetch in a loop.
 */
final class ReadingAnnouncesNothingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_devices_reads_announce_no_change(): void
    {
        $this->seed();

        $household = Household::query()->firstOrFail();
        app(HouseholdContext::class)->setId((int) $household->id);

        $code = app(DevicePairingService::class)->createCode($household, 'Küche')->pairing_code;
        $token = app(DevicePairingService::class)->redeem($code)['token'];

        Queue::fake();

        $today = now($household->timezone)->toDateString();
        $later = now($household->timezone)->addDays(13)->toDateString();

        foreach ([
            "/api/wall/agenda?from={$today}&to={$later}",
            '/api/wall/dashboard',
            '/api/wall/lists',
            '/api/wall/history',
            "/api/wall/tasks?from={$today}&to={$later}",
            "/api/wall/tasks/progress?from={$today}&to={$later}",
            '/api/wall/points',
            '/api/wall/rewards',
            "/api/wall/meals?from={$today}&to={$later}",
            '/api/preferences',
            '/api/theme',
        ] as $url) {
            $this->withToken($token)->getJson($url)->assertSuccessful();
            Queue::assertNotPushed(AnnounceHouseholdUpdateJob::class, $url);
        }
    }
}
