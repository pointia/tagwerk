<?php

declare(strict_types=1);

namespace Tests\Feature\Push;

use App\Enums\DeviceKind;
use App\Jobs\SendPushNotificationJob;
use App\Models\Device;
use App\Models\Household;
use App\Models\Person;
use App\Models\User;
use App\Services\Push\WebPushService;
use App\Support\HouseholdContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class PushRecipientTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create();
        app(HouseholdContext::class)->setId((int) $this->household->id);
    }

    private function phone(string $name, ?Person $person): Device
    {
        $device = Device::factory()->create([
            'household_id' => $this->household->id,
            'name' => $name,
            'kind' => DeviceKind::Mobile,
            'user_id' => $person === null ? null : User::factory()->create([
                'household_id' => $this->household->id,
                'person_id' => $person->id,
            ])->id,
        ]);

        $device->forceFill([
            'push_endpoint' => 'https://push.example/'.$name,
            'push_p256dh' => 'p256dh',
            'push_auth' => 'auth',
        ])->save();

        return $device;
    }

    private function person(string $name, bool $child): Person
    {
        $factory = Person::factory();

        return ($child ? $factory->child() : $factory)->create([
            'household_id' => $this->household->id,
            'name' => $name,
        ]);
    }

    /** @return array<int, string> */
    private function names(bool $adultsOnly): array
    {
        $devices = app(WebPushService::class)->recipients((int) $this->household->id, $adultsOnly);

        return array_map(fn (Device $device): string => (string) $device->name, $devices);
    }

    public function test_daily_summary_goes_only_to_the_parents(): void
    {
        $this->phone('Mama', $this->person('Mama', child: false));
        $this->phone('Lena', $this->person('Lena', child: true));

        $this->assertSame(['Mama'], $this->names(adultsOnly: true));
    }

    public function test_general_notification_goes_to_all_phones(): void
    {
        $this->phone('Mama', $this->person('Mama', child: false));
        $this->phone('Lena', $this->person('Lena', child: true));

        $this->assertEqualsCanonicalizing(['Mama', 'Lena'], $this->names(adultsOnly: false));
    }

    /** A phone without an assigned account cannot be given a role. */
    public function test_phone_without_account_does_not_count_as_parent(): void
    {
        $this->phone('Gästehandy', null);

        $this->assertSame([], $this->names(adultsOnly: true));
        $this->assertSame(['Gästehandy'], $this->names(adultsOnly: false));
    }

    public function test_wall_tablet_gets_no_notification(): void
    {
        $wall = Device::factory()->create([
            'household_id' => $this->household->id,
            'name' => 'Küche',
            'kind' => DeviceKind::Wall,
        ]);
        $wall->forceFill(['push_endpoint' => 'https://push.example/wall'])->save();

        $this->assertSame([], $this->names(adultsOnly: false));
    }

    public function test_foreign_household_stays_out(): void
    {
        $foreign = Household::factory()->create();
        $person = Person::factory()->create(['household_id' => $foreign->id]);
        $device = Device::factory()->create([
            'household_id' => $foreign->id,
            'name' => 'Fremd',
            'kind' => DeviceKind::Mobile,
            'user_id' => User::factory()->create([
                'household_id' => $foreign->id,
                'person_id' => $person->id,
            ])->id,
        ]);
        $device->forceFill(['push_endpoint' => 'https://push.example/foreign'])->save();

        $this->assertSame([], $this->names(adultsOnly: true));
    }

    public function test_a_notification_reaching_no_phone_is_logged(): void
    {
        Log::spy();

        (new SendPushNotificationJob((int) $this->household->id, ['de' => ['title' => 'Tageszusammenfassung']], adultsOnly: true))
            ->handle(app(WebPushService::class));

        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $message, array $context): bool => $message === 'Notification reached no phone.'
                && $context['household_id'] === (int) $this->household->id
                && $context['phones'] === 0)
            ->once();
    }
}
