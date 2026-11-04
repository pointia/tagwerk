<?php

declare(strict_types=1);

namespace Tests\Feature\Wall;

use App\Models\Household;
use App\Models\Person;
use App\Models\PointEntry;
use App\Models\User;
use App\Services\DevicePairingService;
use App\Support\HouseholdContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WallPointsGiftTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-09-16 08:00', 'Europe/Vienna'));

        $this->household = Household::factory()->create(['timezone' => 'Europe/Vienna']);
        app(HouseholdContext::class)->setId((int) $this->household->id);

        $device = app(DevicePairingService::class)->createCode($this->household, 'Küche');
        $this->token = app(DevicePairingService::class)->redeem($device->pairing_code)['token'];
    }

    private function child(string $name): Person
    {
        return Person::factory()->child()->create(['household_id' => $this->household->id, 'name' => $name]);
    }

    private function book(Person $person, int $points): void
    {
        PointEntry::query()->create(['household_id' => $this->household->id, 'person_id' => $person->id, 'points' => $points, 'reason' => 'Start']);
    }

    /** @return array<string, mixed> */
    private function gift(Person $from, Person $to, int $points, ?string $note = null): array
    {
        return ['from_person_id' => $from->id, 'to_person_id' => $to->id, 'points' => $points, 'note' => $note];
    }

    public function test_a_gift_moves_stars_from_one_child_to_another(): void
    {
        $lena = $this->child('Lena');
        $max = $this->child('Max');
        $this->book($lena, 5);

        $this->withToken($this->token)
            ->postJson('/api/wall/points/gift', $this->gift($lena, $max, 3, 'fürs Kino'))
            ->assertCreated()
            ->assertJsonPath('from.points', -3)
            ->assertJsonPath('from.reason', 'Geschenk an Max: fürs Kino')
            ->assertJsonPath('to.points', 3)
            ->assertJsonPath('to.reason', 'Geschenk von Lena: fürs Kino');

        $this->assertSame(2, PointEntry::balanceFor($lena->id));
        $this->assertSame(3, PointEntry::balanceFor($max->id));
        $this->assertSame(1, PointEntry::query()->whereNotNull('gift_id')->distinct()->count('gift_id'));
    }

    public function test_everything_the_giver_has_may_go(): void
    {
        $lena = $this->child('Lena');
        $max = $this->child('Max');
        $this->book($lena, 4);

        $this->withToken($this->token)
            ->postJson('/api/wall/points/gift', $this->gift($lena, $max, 4))
            ->assertCreated();

        $this->assertSame(0, PointEntry::balanceFor($lena->id));
    }

    public function test_never_more_than_the_giver_has(): void
    {
        $lena = $this->child('Lena');
        $max = $this->child('Max');
        $this->book($lena, 2);

        $this->withToken($this->token)
            ->postJson('/api/wall/points/gift', $this->gift($lena, $max, 3))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['points' => 'Lena hat nur 2 Sterne zum Schenken.']);

        $this->assertSame(2, PointEntry::balanceFor($lena->id));
        $this->assertSame(0, PointEntry::balanceFor($max->id));
    }

    public function test_only_between_two_different_children(): void
    {
        $lena = $this->child('Lena');
        $parent = Person::factory()->create(['household_id' => $this->household->id, 'name' => 'Mama']);
        $this->book($lena, 5);
        $this->book($parent, 5);

        $this->withToken($this->token)
            ->postJson('/api/wall/points/gift', $this->gift($lena, $lena, 1))
            ->assertJsonValidationErrors('to_person_id');

        $this->withToken($this->token)
            ->postJson('/api/wall/points/gift', $this->gift($parent, $lena, 1))
            ->assertJsonValidationErrors('from_person_id');

        $this->assertSame(0, PointEntry::query()->whereNotNull('gift_id')->count());
    }

    public function test_on_the_wall_a_gift_needs_the_pin(): void
    {
        $this->parentWithPin($this->household);
        $lena = $this->child('Lena');
        $max = $this->child('Max');
        $this->book($lena, 5);

        $this->withToken($this->token)
            ->postJson('/api/wall/points/gift', $this->gift($lena, $max, 1))
            ->assertStatus(423);

        $this->withToken($this->token)->postJson('/api/wall/pin', ['pin' => '1234'])->assertOk();

        $this->withToken($this->token)
            ->postJson('/api/wall/points/gift', $this->gift($lena, $max, 1))
            ->assertCreated();
    }

    public function test_a_child_on_the_phone_cannot_give(): void
    {
        $lena = $this->child('Lena');
        $max = $this->child('Max');
        $this->book($lena, 5);

        $phone = User::factory()->create(['household_id' => $this->household->id, 'person_id' => $lena->id])
            ->createToken('phone')->plainTextToken;

        $this->withToken($phone)
            ->postJson('/api/wall/points/gift', $this->gift($lena, $max, 1))
            ->assertForbidden();

        $this->assertSame(5, PointEntry::balanceFor($lena->id));
    }

    public function test_a_gift_is_not_earned(): void
    {
        $lena = $this->child('Lena');
        $max = $this->child('Max');
        $this->book($lena, 5);

        $this->withToken($this->token)
            ->postJson('/api/wall/points/gift', $this->gift($lena, $max, 3))
            ->assertCreated();

        $people = collect($this->withToken($this->token)->getJson('/api/wall/points')->json('people'))->keyBy('name');

        $this->assertSame(2, $people['Lena']['balance']);
        $this->assertSame(5, $people['Lena']['week_points']);
        $this->assertSame(3, $people['Max']['balance']);
        $this->assertSame(0, $people['Max']['week_points']);

        $bonus = $this->withToken($this->token)
            ->getJson('/api/wall/tasks?from=2026-09-16&to=2026-09-16')
            ->json('bonus');

        $this->assertSame([5], collect($bonus)->pluck('points')->all());
    }

    public function test_deleting_one_half_takes_the_other(): void
    {
        $lena = $this->child('Lena');
        $max = $this->child('Max');
        $this->book($lena, 5);

        $this->withToken($this->token)
            ->postJson('/api/wall/points/gift', $this->gift($lena, $max, 3))
            ->assertCreated();

        PointEntry::query()->where('person_id', $max->id)->firstOrFail()->delete();

        $this->assertSame(5, PointEntry::balanceFor($lena->id));
        $this->assertSame(0, PointEntry::balanceFor($max->id));
    }
}
