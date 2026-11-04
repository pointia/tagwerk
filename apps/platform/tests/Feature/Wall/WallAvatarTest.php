<?php

declare(strict_types=1);

namespace Tests\Feature\Wall;

use App\Models\Household;
use App\Models\Person;
use App\Models\Task;
use App\Services\DevicePairingService;
use App\Support\HouseholdContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A person's picture accompanies them through all views.
 *
 * The URL is checked, not the file: whether the image really exists is up to
 * the storage — what counts here is that every response relating to a person
 * includes the URL and honestly reports null without a picture.
 */
final class WallAvatarTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create(['timezone' => 'Europe/Vienna']);
        app(HouseholdContext::class)->setId((int) $this->household->id);

        $device = app(DevicePairingService::class)->createCode($this->household, 'Küche');
        $this->token = app(DevicePairingService::class)->redeem($device->pairing_code)['token'];
    }

    private function person(?string $avatar): Person
    {
        return Person::factory()->child()->create([
            'household_id' => $this->household->id,
            'name' => 'Lena',
            'avatar_path' => $avatar,
        ]);
    }

    public function test_person_without_picture_reports_null(): void
    {
        $this->person(null);

        $this->withToken($this->token)
            ->getJson('/api/wall/points')
            ->assertOk()
            ->assertJsonPath('people.0.avatar', null);
    }

    public function test_points_return_the_picture_url(): void
    {
        $this->person('avatars/lena.jpg');

        $url = $this->withToken($this->token)
            ->getJson('/api/wall/points')
            ->assertOk()
            ->json('people.0.avatar');

        $this->assertStringEndsWith('/storage/avatars/lena.jpg', (string) $url);
    }

    public function test_agenda_returns_the_picture_url(): void
    {
        $this->person('avatars/lena.jpg');

        $url = $this->withToken($this->token)
            ->getJson('/api/wall/agenda?from=2026-09-17&to=2026-09-18')
            ->assertOk()
            ->json('people.0.avatar');

        $this->assertStringEndsWith('/storage/avatars/lena.jpg', (string) $url);
    }

    public function test_task_returns_the_picture_of_the_assigned_person(): void
    {
        $person = $this->person('avatars/lena.jpg');

        Task::factory()->create([
            'household_id' => $this->household->id,
            'person_id' => $person->id,
            'due_on' => '2026-09-17',
        ]);

        $url = $this->withToken($this->token)
            ->getJson('/api/wall/tasks?from=2026-09-17&to=2026-09-17')
            ->assertOk()
            ->json('occurrences.0.avatar');

        $this->assertStringEndsWith('/storage/avatars/lena.jpg', (string) $url);
    }
}
