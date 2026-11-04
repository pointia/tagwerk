<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PersonRole;
use App\Filament\Resources\PointEntries\Pages\ListPointEntries;
use App\Models\Household;
use App\Models\Person;
use App\Models\PointEntry;
use App\Models\User;
use App\Services\PointLedger;
use App\Support\HouseholdContext;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Parents look back at what was booked and put right what went wrong —
 * a double tap, a star for the wrong child.
 */
final class AdminPointHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private Person $child;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create();
        $this->actingAs(User::factory()->create(['household_id' => $this->household->id]));
        app(HouseholdContext::class)->setId($this->household->id);

        $this->child = Person::factory()->for($this->household)->create(['name' => 'Ben', 'role' => PersonRole::Child]);
    }

    public function test_the_history_lists_only_the_own_household(): void
    {
        $own = PointEntry::factory()->for($this->household)->for($this->child)->create(['reason' => 'Zähne geputzt']);

        $foreignHousehold = Household::factory()->create();
        $foreign = PointEntry::factory()->for($foreignHousehold)->create([
            'person_id' => Person::factory()->for($foreignHousehold),
            'reason' => 'Fremde Gutschrift',
        ]);

        $this->get('/admin/point-entries')->assertSuccessful();

        Livewire::test(ListPointEntries::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$foreign]);
    }

    public function test_parents_change_an_entry(): void
    {
        $entry = PointEntry::factory()->for($this->household)->for($this->child)->create(['points' => 5]);

        Livewire::test(ListPointEntries::class)
            ->callAction(TestAction::make('edit')->table($entry), [
                'points' => 3,
                'reason' => 'Nur halb aufgeräumt',
            ])
            ->assertHasNoFormErrors();

        $entry->refresh();
        $this->assertSame(3, $entry->points);
        $this->assertSame('Nur halb aufgeräumt', $entry->reason);
        $this->assertSame(3, PointEntry::balanceFor($this->child->id));
    }

    public function test_parents_delete_an_entry(): void
    {
        $entry = PointEntry::factory()->for($this->household)->for($this->child)->create();

        Livewire::test(ListPointEntries::class)
            ->callAction(TestAction::make('delete')->table($entry));

        $this->assertModelMissing($entry);
    }

    /** @return array{0: PointEntry, 1: PointEntry} */
    private function gift(): array
    {
        $giver = Person::factory()->for($this->household)->create(['name' => 'Lena', 'role' => PersonRole::Child]);
        PointEntry::factory()->for($this->household)->for($giver)->create(['points' => 5]);

        $result = app(PointLedger::class)->gift($giver, $this->child, 3, null);
        $this->assertIsArray($result);

        return $result;
    }

    public function test_a_gift_is_deleted_whole(): void
    {
        [$taken, $credited] = $this->gift();

        Livewire::test(ListPointEntries::class)
            ->assertTableColumnStateSet('source', 'Geschenk', $credited)
            ->assertActionHidden(TestAction::make('edit')->table($credited))
            ->callAction(TestAction::make('delete')->table($credited));

        $this->assertModelMissing($taken);
        $this->assertModelMissing($credited);
    }

    public function test_a_gift_is_deleted_whole_in_bulk(): void
    {
        [$taken, $credited] = $this->gift();

        Livewire::test(ListPointEntries::class)
            ->selectTableRecords([$taken])
            ->callAction(TestAction::make('delete')->table()->bulk());

        $this->assertModelMissing($taken);
        $this->assertModelMissing($credited);
    }
}
