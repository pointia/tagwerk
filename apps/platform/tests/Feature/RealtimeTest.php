<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\HouseholdUpdated;
use App\Jobs\AnnounceHouseholdUpdateJob;
use App\Models\Checklist;
use App\Models\Household;
use App\Models\ListItem;
use App\Models\Task;
use App\Services\DevicePairingService;
use App\Support\HouseholdContext;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Changes appear on all devices without reloading (section 8).
 *
 * The WebSocket itself cannot be tested here — but it can be tested that the
 * message is created, lands in the right channel, lets no outsider in and
 * takes nothing down with it when Reverb is unreachable.
 */
final class RealtimeTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private string $token;

    /** @var array<string, array{0: string|false, 1: string|null, 2: string|null}> */
    private array $previousEnvironment = [];

    /**
     * The connection must be set before the application boots.
     *
     * `Broadcast::channel()` has no method of its own on the BroadcastManager
     * — the call goes through __call to the driver that matches the default
     * at boot time. The channels from `routes/channels.php` therefore live on
     * exactly this one driver. Whoever switches the default only inside the
     * test gets a fresh driver without any channel — and thus a test that
     * rejects every channel and proves nothing.
     *
     * The default in the test environment is `null`, and the `null` driver
     * conversely authorises every channel silently. That proves nothing
     * either.
     *
     * The target is deliberately 127.0.0.1 on a dead port: a real send then
     * fails immediately with "connection refused" instead of on a name
     * lookup that slows the run down.
     */
    protected function setUp(): void
    {
        $this->setEnvironment([
            'BROADCAST_CONNECTION' => 'reverb',
            'REVERB_APP_ID' => 'testing',
            'REVERB_APP_KEY' => 'test-key',
            'REVERB_APP_SECRET' => 'test-secret',
            'REVERB_HOST' => '127.0.0.1',
            'REVERB_PORT' => '6001',
            'REVERB_SCHEME' => 'http',
        ]);

        parent::setUp();

        $this->household = Household::factory()->create();
        app(HouseholdContext::class)->setId((int) $this->household->id);

        $device = app(DevicePairingService::class)->createCode($this->household, 'Küche');
        $this->token = app(DevicePairingService::class)->redeem($device->pairing_code)['token'];
    }

    protected function tearDown(): void
    {
        foreach ($this->previousEnvironment as $name => [$inProcess, $inEnv, $inServer]) {
            $inProcess === false ? putenv($name) : putenv("{$name}={$inProcess}");

            if ($inEnv === null) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $inEnv;
            }

            if ($inServer === null) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $inServer;
            }
        }

        $this->previousEnvironment = [];

        parent::tearDown();
    }

    /**
     * Laravel reads the environment from several sources, and $_SERVER as
     * well as $_ENV take precedence over putenv(). PHPUnit fills both from
     * phpunit.xml — a plain putenv() would therefore have no effect.
     *
     * @param  array<string, string>  $values
     */
    private function setEnvironment(array $values): void
    {
        foreach ($values as $name => $value) {
            $this->previousEnvironment[$name] = [
                getenv($name),
                $_ENV[$name] ?? null,
                $_SERVER[$name] ?? null,
            ];

            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    public function test_event_runs_in_the_household_channel(): void
    {
        $event = new HouseholdUpdated(42, 'tasks');

        $this->assertEquals([new PrivateChannel('household.42')], $event->broadcastOn());
        $this->assertSame(['area' => 'tasks'], $event->broadcastWith());
        $this->assertSame('updated', $event->broadcastAs());
    }

    public function test_a_new_task_is_announced(): void
    {
        Queue::fake();

        Task::factory()->create(['household_id' => $this->household->id]);

        Queue::assertPushed(AnnounceHouseholdUpdateJob::class);
    }

    public function test_a_deleted_list_is_announced(): void
    {
        $list = Checklist::factory()->create(['household_id' => $this->household->id]);

        Queue::fake();
        $list->delete();

        Queue::assertPushed(AnnounceHouseholdUpdateJob::class);
    }

    /**
     * A sync saves many rows at once. One announcement per row made every
     * open wall and phone fetch everything again per row — hundreds of
     * requests, until the browser ran out of connections.
     */
    public function test_a_burst_of_changes_is_announced_once_per_area(): void
    {
        $list = Checklist::factory()->create(['household_id' => $this->household->id]);

        Queue::fake();

        for ($i = 1; $i <= 30; $i++) {
            ListItem::create(['household_id' => $this->household->id, 'list_id' => $list->id, 'title' => "Eintrag {$i}"]);
        }
        Task::factory()->create(['household_id' => $this->household->id]);

        Queue::assertPushed(AnnounceHouseholdUpdateJob::class, fn (AnnounceHouseholdUpdateJob $job): bool => $job->area === 'lists');
        Queue::assertPushed(AnnounceHouseholdUpdateJob::class, fn (AnnounceHouseholdUpdateJob $job): bool => $job->area === 'tasks');
        Queue::assertPushedTimes(AnnounceHouseholdUpdateJob::class, 2);
    }

    /**
     * The core guarantee: an unreachable Reverb costs the immediate update,
     * not the change.
     */
    public function test_reverb_outage_takes_nothing_down(): void
    {
        config(['broadcasting.default' => 'missing']);

        $job = new AnnounceHouseholdUpdateJob((int) $this->household->id, 'tasks');

        $job->handle();

        $this->assertTrue(true, 'The job caught the failure.');
    }

    public function test_writing_stays_possible_despite_outage(): void
    {
        config(['broadcasting.default' => 'missing', 'queue.default' => 'sync']);

        $task = Task::factory()->create([
            'household_id' => $this->household->id,
            'title' => 'Müll rausbringen',
        ]);

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_own_channel_is_authorised(): void
    {
        $this->withToken($this->token)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => "private-household.{$this->household->id}",
            ])
            ->assertOk()
            // The signature is the proof that access was really granted.
            ->assertJsonStructure(['auth']);
    }

    public function test_foreign_channel_stays_closed(): void
    {
        $foreign = Household::factory()->create();

        $this->withToken($this->token)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => "private-household.{$foreign->id}",
            ])
            ->assertForbidden();
    }

    public function test_no_channel_without_sign_in(): void
    {
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-household.{$this->household->id}",
        ])->assertUnauthorized();
    }

    /**
     * The frontends fetch address and key at runtime. The key names the
     * application and may go out; the secret never.
     */
    public function test_connection_details_come_from_the_api(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/realtime/config')
            ->assertOk()
            ->assertJson([
                'enabled' => true,
                'key' => 'test-key',
                'host' => '127.0.0.1',
                'port' => 6001,
                'scheme' => 'http',
                'channel' => 'household.'.$this->household->id,
            ])
            ->assertJsonMissingPath('secret')
            ->assertJsonMissingPath('app_id');
    }
}
