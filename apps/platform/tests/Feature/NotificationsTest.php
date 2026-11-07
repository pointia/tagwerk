<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DeviceKind;
use App\Jobs\AnnounceHouseholdUpdateJob;
use App\Models\Device;
use App\Models\Household;
use App\Models\Notification;
use App\Models\Person;
use App\Models\User;
use App\Notifications\HouseholdMovedIn;
use App\Notifications\RewardRequested;
use App\Support\HouseholdContext;
use Filament\Livewire\DatabaseNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Messages are events, stored per account and household: the admin's bell
 * and the app's list read the same rows.
 */
final class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private User $parent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create();
        $this->parent = User::factory()->create(['household_id' => $this->household->id]);
    }

    public function test_a_message_is_stored_with_its_household_and_announced(): void
    {
        Queue::fake([AnnounceHouseholdUpdateJob::class]);

        $this->parent->notifyNow(new RewardRequested((int) $this->household->id, 'Max', 'Kino', 20));

        $stored = Notification::query()->sole();
        $this->assertSame((int) $this->household->id, (int) $stored->household_id);
        $this->assertSame('filament', $stored->data['format']);
        Queue::assertPushed(AnnounceHouseholdUpdateJob::class, fn (AnnounceHouseholdUpdateJob $job): bool => $job->area === 'notifications');
    }

    public function test_a_reward_request_leads_to_the_stars(): void
    {
        $message = new RewardRequested((int) $this->household->id, 'Max', 'Kino', 20);
        $this->parent->notifyNow($message);

        $this->assertSame('/?area=points', $message->toPush($this->parent)['url'] ?? null);
        $this->actingAs($this->parent)->getJson('/api/notifications')
            ->assertJsonPath('items.0.url', '/?area=points');
    }

    public function test_the_app_lists_and_marks_read(): void
    {
        $this->parent->notifyNow(new HouseholdMovedIn((int) $this->household->id, 'Müller', []));

        $this->actingAs($this->parent)->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('items.0.title', 'Müller ist eingezogen')
            ->assertJsonPath('items.0.url', '/admin')
            ->assertJsonPath('items.0.read', false);

        $this->actingAs($this->parent)->postJson('/api/notifications/read')->assertNoContent();

        $this->actingAs($this->parent)->getJson('/api/notifications')->assertJsonPath('unread', 0);
    }

    public function test_another_households_messages_wait_until_it_is_in_view(): void
    {
        $other = Household::factory()->create();
        $this->parent->memberships()->create([
            'household_id' => $other->id,
            'person_id' => Person::factory()->create(['household_id' => $other->id])->id,
            'accepted_at' => now(),
        ]);

        $this->parent->notifyNow(new RewardRequested((int) $other->id, 'Lena', 'Eis', 5));

        $this->actingAs($this->parent)->getJson('/api/notifications')->assertJsonPath('unread', 0);

        app(HouseholdContext::class)->setId((int) $other->id);
        $this->assertSame(1, $this->parent->unreadNotifications()->count());
    }

    public function test_the_admin_has_the_bell(): void
    {
        $this->actingAs($this->parent)->get('/admin')
            ->assertOk()
            ->assertSeeLivewire(DatabaseNotifications::class);
    }

    public function test_a_wall_has_no_messages(): void
    {
        $wall = Device::factory()->create(['household_id' => $this->household->id])->createToken('wall')->plainTextToken;

        $this->withToken($wall)->getJson('/api/notifications')->assertForbidden();
    }

    public function test_deleting_the_account_takes_its_messages(): void
    {
        $this->parent->notifyNow(new HouseholdMovedIn((int) $this->household->id, 'Müller', []));

        $this->parent->delete();

        $this->assertSame(0, Notification::query()->count());
    }

    public function test_a_push_failure_costs_only_the_push(): void
    {
        config(['services.webpush.public_key' => 'broken', 'services.webpush.private_key' => 'broken', 'services.webpush.subject' => 'mailto:x@example.org']);
        Device::factory()->create([
            'household_id' => $this->household->id,
            'user_id' => $this->parent->id,
            'kind' => DeviceKind::Mobile,
        ])->forceFill([
            'push_endpoint' => 'https://push.example.org/1',
            'push_p256dh' => 'x',
            'push_auth' => 'y',
        ])->save();

        $this->parent->notifyNow(new RewardRequested((int) $this->household->id, 'Max', 'Kino', 20));

        $this->assertSame(1, Notification::query()->count());
    }
}
