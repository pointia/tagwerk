<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Areas\ApiArea;
use App\Auth\ApiTokens;
use App\Filament\Pages\ApiAccess;
use App\Models\Checklist;
use App\Models\Household;
use App\Models\Person;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Support\HouseholdContext;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Tokens for other programs: read-only or with writing, in one household,
 * never more than the account itself may do.
 */
final class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private User $parent;

    private Checklist $list;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create();
        $this->optIn($this->household, ApiArea::KEY);
        $this->parent = $this->account(Person::factory()->for($this->household)->create());
        $this->list = Checklist::factory()->create(['household_id' => $this->household->id]);
    }

    private function account(Person $person): User
    {
        return User::factory()->create(['household_id' => $this->household->id, 'person_id' => $person->id]);
    }

    private function token(User $user, bool $write): string
    {
        return app(ApiTokens::class)->create($user, (int) $this->household->id, 'Home Assistant', $write);
    }

    public function test_a_read_token_reads(): void
    {
        $this->withToken($this->token($this->parent, write: false))
            ->getJson('/api/wall/lists')
            ->assertOk();
    }

    public function test_a_read_token_does_not_write(): void
    {
        $this->withToken($this->token($this->parent, write: false))
            ->postJson("/api/wall/lists/{$this->list->id}/items", ['title' => 'Milch'])
            ->assertForbidden();
    }

    public function test_a_write_token_writes(): void
    {
        $this->withToken($this->token($this->parent, write: true))
            ->postJson("/api/wall/lists/{$this->list->id}/items", ['title' => 'Milch'])
            ->assertSuccessful();
    }

    public function test_the_app_token_is_not_affected(): void
    {
        $this->withToken($this->parent->createToken('mobile', ['*'])->plainTextToken)
            ->postJson("/api/wall/lists/{$this->list->id}/items", ['title' => 'Milch'])
            ->assertSuccessful();
    }

    public function test_a_token_can_do_no_more_than_its_account(): void
    {
        $child = $this->account(Person::factory()->child()->for($this->household)->create());

        $this->withToken($this->token($child, write: true))
            ->postJson('/api/wall/tasks', ['title' => 'Fernsehen', 'points' => 99])
            ->assertForbidden();
    }

    public function test_a_token_looks_at_its_household(): void
    {
        $this->token($this->parent, write: false);

        $this->assertSame(
            $this->household->id,
            (int) PersonalAccessToken::query()->where('name', 'api:Home Assistant')->value('household_id'),
        );
    }

    public function test_no_token_for_a_household_without_membership(): void
    {
        $other = Household::factory()->create();

        $this->expectException(HttpException::class);

        app(ApiTokens::class)->create($this->parent, (int) $other->id, 'Fremd', false);
    }

    public function test_a_new_password_revokes_the_tokens(): void
    {
        $plain = $this->token($this->parent, write: true);
        $this->parent->update(['password' => 'a-completely-new-password']);
        Auth::forgetGuards();

        $this->withToken($plain)->getJson('/api/wall/lists')->assertUnauthorized();
    }

    public function test_the_page_creates_lists_and_revokes(): void
    {
        $this->actingAs($this->parent);
        app(HouseholdContext::class)->setId((int) $this->household->id);

        Livewire::test(ApiAccess::class)
            ->callAction(TestAction::make('create')->table(), ['label' => 'Skript', 'write' => true])
            ->assertNotified();

        $token = app(ApiTokens::class)->of($this->parent)->sole();
        $this->assertSame(['api', 'read', 'write'], $token->abilities);

        Livewire::test(ApiAccess::class)
            ->assertCanSeeTableRecords([$token])
            ->callAction(TestAction::make('delete')->table($token));

        $this->assertSame(0, app(ApiTokens::class)->of($this->parent)->count());
    }
}
