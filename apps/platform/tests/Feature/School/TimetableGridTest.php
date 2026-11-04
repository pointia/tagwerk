<?php

declare(strict_types=1);

namespace Tests\Feature\School;

use App\Filament\Resources\Lessons\Pages\CreateLesson;
use App\Filament\Resources\Lessons\Pages\TimetableGrid;
use App\Models\Household;
use App\Models\Lesson;
use App\Models\LessonPeriod;
use App\Models\Person;
use App\Models\User;
use App\Support\HouseholdContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The admin shows the timetable as a weekly grid per child — the list is
 * one click away.
 */
final class TimetableGridTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private Person $lena;

    private Person $max;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create();
        $this->actingAs(User::factory()->create(['household_id' => $this->household->id]));
        app(HouseholdContext::class)->setId((int) $this->household->id);

        $this->lena = Person::factory()->child()->create(['household_id' => $this->household->id, 'name' => 'Lena']);
        $this->max = Person::factory()->child()->create(['household_id' => $this->household->id, 'name' => 'Max']);

        foreach ([$this->lena, $this->max] as $child) {
            foreach ([1 => ['08:00', '08:50'], 2 => ['08:55', '09:45']] as $number => [$from, $to]) {
                LessonPeriod::factory()->create([
                    'household_id' => $this->household->id,
                    'person_id' => $child->id,
                    'number' => $number,
                    'starts_at' => $from,
                    'ends_at' => $to,
                ]);
            }
        }
    }

    public function test_the_grid_is_the_start_page_and_the_list_stays(): void
    {
        $this->lesson($this->lena, 'Mathematik', 1, 1);

        $this->get('/admin/lessons')
            ->assertSuccessful()
            ->assertSee('Lena')
            ->assertSee('Max')
            ->assertSee('Mathematik')
            ->assertSee('data-timetable="'.$this->lena->id.'"', false);

        $this->get('/admin/lessons/list')->assertSuccessful()->assertSee('Mathematik');
    }

    public function test_each_lesson_sits_in_its_slot_and_own_times_are_listed_below(): void
    {
        $this->lesson($this->lena, 'Mathematik', 2, 1);
        Lesson::factory()->create([
            'household_id' => $this->household->id,
            'person_id' => $this->lena->id,
            'title' => 'Musikschule',
            'weekday' => 3,
            'period_number' => null,
            'starts_at' => '15:00',
            'ends_at' => '15:45',
        ]);

        $page = Livewire::test(TimetableGrid::class)->set('child', (string) $this->lena->id);

        $page->assertSee('Mathematik')
            ->assertSee('Musikschule')
            ->assertSee('15:00–15:45')
            // The taken slot is no create link; a free one is.
            ->assertDontSeeHtml('data-slot="2-1"')
            ->assertSeeHtml('data-slot="2-2"');
    }

    public function test_the_child_filter_shows_only_that_child(): void
    {
        $this->lesson($this->lena, 'Mathematik', 1, 1);
        $this->lesson($this->max, 'Werken', 1, 1);

        Livewire::test(TimetableGrid::class)
            ->set('child', (string) $this->max->id)
            ->assertSee('Werken')
            ->assertDontSee('Mathematik')
            ->assertDontSeeHtml('data-timetable="'.$this->lena->id.'"');
    }

    public function test_search_rhythm_and_inactive_filter_the_grid(): void
    {
        $this->lesson($this->lena, 'Mathematik', 1, 1);
        $this->lesson($this->lena, 'Sport', 2, 1, ['interval' => 2]);
        $this->lesson($this->lena, 'Latein', 3, 1, ['active' => false]);

        Livewire::test(TimetableGrid::class)
            ->assertSee('Mathematik')
            ->assertSee('Sport')
            ->assertDontSee('Latein')
            ->set('search', 'math')
            ->assertSee('Mathematik')
            ->assertDontSee('Sport')
            ->set('search', '')
            ->set('rhythm', 'biweekly')
            ->assertSee('Sport')
            ->assertDontSee('Mathematik')
            ->set('rhythm', '')
            ->set('inactive', true)
            ->assertSee('Latein');
    }

    public function test_two_alternating_subjects_share_a_slot(): void
    {
        $this->lesson($this->lena, 'Physik', 4, 2, ['interval' => 2]);
        $this->lesson($this->lena, 'Chemie', 4, 2, ['interval' => 2]);

        Livewire::test(TimetableGrid::class)
            ->set('child', (string) $this->lena->id)
            ->assertSee('Physik')
            ->assertSee('Chemie');
    }

    public function test_another_household_stays_invisible(): void
    {
        $foreign = Household::factory()->create();
        $stranger = Person::factory()->child()->create(['household_id' => $foreign->id, 'name' => 'Fremdkind']);
        Lesson::factory()->create([
            'household_id' => $foreign->id,
            'person_id' => $stranger->id,
            'title' => 'Geheimfach',
            'weekday' => 1,
            'period_number' => 1,
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $this->get('/admin/lessons')
            ->assertSuccessful()
            ->assertDontSee('Fremdkind')
            ->assertDontSee('Geheimfach');
    }

    public function test_an_empty_cell_opens_the_form_with_child_and_slot(): void
    {
        Livewire::withQueryParams(['person' => $this->max->id, 'slot' => '2-2'])
            ->test(CreateLesson::class)
            ->assertSet('data.person_id', $this->max->id)
            ->assertSet('data.slots', ['2-2']);
    }

    public function test_the_form_ignores_a_taken_slot_or_a_foreign_child(): void
    {
        $this->lesson($this->max, 'Werken', 2, 2);

        Livewire::withQueryParams(['person' => $this->max->id, 'slot' => '2-2'])
            ->test(CreateLesson::class)
            ->assertSet('data.person_id', $this->max->id)
            ->assertNotSet('data.slots', ['2-2']);

        $stranger = Person::factory()->child()->create(['household_id' => Household::factory()->create()->id]);

        Livewire::withQueryParams(['person' => $stranger->id, 'slot' => '1-1'])
            ->test(CreateLesson::class)
            ->assertNotSet('data.person_id', $stranger->id);
    }

    /** @param  array<string, mixed>  $extra */
    private function lesson(Person $child, string $title, int $weekday, int $period, array $extra = []): Lesson
    {
        return Lesson::factory()->create([
            'household_id' => $this->household->id,
            'person_id' => $child->id,
            'title' => $title,
            'weekday' => $weekday,
            'period_number' => $period,
            'starts_at' => null,
            'ends_at' => null,
            ...$extra,
        ]);
    }
}
