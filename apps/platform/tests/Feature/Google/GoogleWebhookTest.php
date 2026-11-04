<?php

declare(strict_types=1);

namespace Tests\Feature\Google;

use App\Jobs\SyncCalendarJob;
use App\Models\Calendar;
use App\Models\Household;
use App\Models\ProviderAccount;
use App\Support\HouseholdContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class GoogleWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Calendar $calendar;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google.webhook_token' => 'channel-secret']);

        $household = Household::factory()->create();
        app(HouseholdContext::class)->setId((int) $household->id);

        $this->calendar = Calendar::factory()->create([
            'household_id' => $household->id,
            'provider_account_id' => ProviderAccount::factory()->create([
                'household_id' => $household->id,
            ])->id,
            'push_channel_id' => 'channel-1',
        ]);

        Queue::fake();
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function notify(array $headers): TestResponse
    {
        return $this->postJson('/api/calendar/google/webhook', [], $headers);
    }

    public function test_notification_queues_the_sync(): void
    {
        $this->notify([
            'X-Goog-Channel-Token' => 'channel-secret',
            'X-Goog-Channel-ID' => 'channel-1',
            'X-Goog-Resource-State' => 'exists',
        ])->assertNoContent();

        Queue::assertPushed(
            SyncCalendarJob::class,
            fn (SyncCalendarJob $job): bool => $job->calendarId === $this->calendar->id,
        );
    }

    public function test_wrong_token_triggers_nothing(): void
    {
        $this->notify([
            'X-Goog-Channel-Token' => 'guessed',
            'X-Goog-Channel-ID' => 'channel-1',
            'X-Goog-Resource-State' => 'exists',
        ])->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_missing_token_triggers_nothing(): void
    {
        $this->notify([
            'X-Goog-Channel-ID' => 'channel-1',
            'X-Goog-Resource-State' => 'exists',
        ])->assertForbidden();

        Queue::assertNothingPushed();
    }

    /**
     * Google confirms every new channel once with resourceState "sync".
     * That is not a change yet.
     */
    public function test_confirmation_on_creation_triggers_nothing(): void
    {
        $this->notify([
            'X-Goog-Channel-Token' => 'channel-secret',
            'X-Goog-Channel-ID' => 'channel-1',
            'X-Goog-Resource-State' => 'sync',
        ])->assertNoContent();

        Queue::assertNothingPushed();
    }

    public function test_unknown_channel_is_acknowledged_silently(): void
    {
        // Anything outside 2xx would make Google deliver again.
        $this->notify([
            'X-Goog-Channel-Token' => 'channel-secret',
            'X-Goog-Channel-ID' => 'channel-unknown',
            'X-Goog-Resource-State' => 'exists',
        ])->assertNoContent();

        Queue::assertNothingPushed();
    }
}
