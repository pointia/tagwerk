<?php

namespace App\Filament\Resources\People\Pages;

use App\Enums\Permission;
use App\Enums\PersonRole;
use App\Filament\Resources\People\PersonResource;
use App\Models\Membership;
use App\Models\Person;
use App\Support\Abilities;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPerson extends EditRecord
{
    protected static string $resource = PersonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Hidden while the defaults apply, and then null: a later change of
        // role takes effect again.
        $data['permissions'] ??= null;

        /** @var Person $person */
        $person = $this->record;
        $granted = $data['permissions'] !== null
            ? Permission::fromStored($data['permissions'])
            : Permission::defaultsFor(PersonRole::tryFrom((string) $data['role']) ?? PersonRole::Child);

        if (! in_array(Permission::HouseholdManage, $granted, true) && $this->isLastManager($person)) {
            Notification::make()
                ->danger()
                ->title(__('Otherwise nobody could open the admin panel any more.'))
                ->body(__('First give another account the permission “Open admin panel”.'))
                ->send();

            $this->halt();
        }

        return $data;
    }

    private function isLastManager(Person $person): bool
    {
        if (! Membership::query()->where('person_id', $person->id)->exists()) {
            return false;
        }

        $abilities = app(Abilities::class);

        return Membership::query()
            ->accepted()
            ->where('household_id', $person->household_id)
            ->where(fn ($query) => $query->whereNull('person_id')->orWhere('person_id', '!=', $person->id))
            ->get()
            ->doesntContain(fn (Membership $membership): bool => in_array(Permission::HouseholdManage, $abilities->ofMembership($membership), true));
    }
}
