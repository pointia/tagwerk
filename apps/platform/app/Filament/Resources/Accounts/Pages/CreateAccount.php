<?php

declare(strict_types=1);

namespace App\Filament\Resources\Accounts\Pages;

use App\Filament\Resources\Accounts\AccountResource;
use App\Models\Household;
use App\Models\Membership;
use App\Models\Person;
use App\Services\Memberships;
use App\Support\HouseholdContext;
use App\Support\OutgoingMail;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateAccount extends CreateRecord
{
    protected static string $resource = AccountResource::class;

    /**
     * A new address becomes an account, a known one is asked to join. The
     * page answers the same either way.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $household = Household::query()->findOrFail(app(HouseholdContext::class)->id());
        $person = Person::query()->findOrFail($data['person_id']);
        $email = (string) $data['email'];

        // Already here: the list shows it anyway, so saying so reveals nothing.
        $here = Membership::query()
            ->where('household_id', $household->id)
            ->whereHas('user', fn ($user) => $user->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))]))
            ->exists();

        if ($here) {
            throw ValidationException::withMessages(['data.email' => __('This address already has an account in this household.')]);
        }

        return app(Memberships::class)->invite($household, $person, $email, $data['password'] ?? null)
            ?? new Membership;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return OutgoingMail::enabled() ? __('Account created, invitation sent') : __('Account created');
    }
}
