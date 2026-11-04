<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\PersonRole;
use App\Filament\Pages\Auth\EditProfile;
use App\Models\Device;
use App\Models\Household;
use App\Models\Person;
use App\Models\User;
use App\Services\DevicePairingService;
use App\Services\ParentPin;
use App\Support\HouseholdContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Every parent has a PIN of their own. At the wall any of them unlocks —
 * nobody has to know the other's, and each can change theirs alone.
 */
final class ParentPinTest extends TestCase
{
    use RefreshDatabase;

    private Household $household;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->household = Household::factory()->create();
        app(HouseholdContext::class)->setId((int) $this->household->id);

        $device = app(DevicePairingService::class)->createCode($this->household, 'Flur');
        $this->token = app(DevicePairingService::class)->redeem($device->pairing_code)['token'];
    }

    private function parent(string $name): User
    {
        return User::factory()->create(['household_id' => $this->household->id, 'name' => $name]);
    }

    private function unlocks(string $pin): bool
    {
        Auth::forgetGuards();
        app(ParentPin::class)->lock((string) Device::query()->firstOrFail()->id);

        return $this->withToken($this->token)->postJson('/api/wall/pin', ['pin' => $pin])->status() === 200;
    }

    public function test_each_parent_sets_their_own_pin_in_the_profile(): void
    {
        $mama = $this->parent('Mama');
        $papa = $this->parent('Papa');
        Filament::setCurrentPanel('admin');

        $this->actingAs($mama);
        Livewire::test(EditProfile::class)
            ->fillForm(['new_pin' => '1111'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->actingAs($papa);
        Livewire::test(EditProfile::class)
            ->fillForm(['new_pin' => '2222'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($this->household->refresh()->hasPin());
        $this->assertTrue($this->unlocks('1111'));
        $this->assertTrue($this->unlocks('2222'));
        $this->assertFalse($this->unlocks('3333'));
    }

    public function test_the_pin_is_digits_only_and_can_be_removed(): void
    {
        $mama = $this->parent('Mama');
        Filament::setCurrentPanel('admin');
        $this->actingAs($mama);

        Livewire::test(EditProfile::class)
            ->fillForm(['new_pin' => '12'])
            ->call('save')
            ->assertHasFormErrors(['new_pin']);

        app(ParentPin::class)->set($mama, '4711');

        // An empty field keeps it: nobody retypes the PIN to change their name.
        Livewire::test(EditProfile::class)
            ->assertFormSet(['new_pin' => null])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertTrue($this->unlocks('4711'));

        // The wall request above switched to the device's guard.
        Auth::forgetGuards();
        $this->actingAs($mama, 'web');
        Livewire::test(EditProfile::class)
            ->fillForm(['remove_pin' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($mama->refresh()->pin_hash);
        $this->assertFalse($this->household->refresh()->hasPin());
    }

    /*
     * Whoever may no longer manage the household no longer unlocks the wall,
     * even with a PIN still on the account.
     */
    public function test_an_account_without_the_manage_right_does_not_unlock(): void
    {
        $person = Person::factory()->create([
            'household_id' => $this->household->id,
            'role' => PersonRole::Adult,
            'permissions' => [Permission::TasksCompleteOwn->value],
        ]);
        $former = User::factory()->create(['household_id' => $this->household->id, 'person_id' => $person->id]);
        app(ParentPin::class)->set($former, '5555');

        $this->assertFalse($this->unlocks('5555'));
    }

    public function test_a_pin_from_another_household_does_not_unlock(): void
    {
        $foreign = User::factory()->create(['household_id' => Household::factory()->create()->id]);
        app(ParentPin::class)->set($foreign, '9090');

        $this->assertFalse($this->household->refresh()->hasPin());
        $this->assertFalse($this->unlocks('9090'));
    }

    /** The PIN stands in for a sign-in on the wall; an account is never asked. */
    public function test_an_account_is_never_asked_for_the_pin(): void
    {
        $this->parentWithPin($this->household);
        $account = User::factory()->create(['household_id' => $this->household->id]);

        $this->withToken($account->createToken('phone')->plainTextToken)
            ->getJson('/api/wall/pin')
            ->assertOk()
            ->assertJson(['required' => false, 'unlocked' => true]);
    }
}
