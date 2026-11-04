<?php

declare(strict_types=1);

namespace App\Filament\Resources\Accounts\Pages;

use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Resources\Accounts\Schemas\AccountForm;
use App\Models\Membership;
use App\Models\Person;
use App\Services\Memberships;
use App\Support\OutgoingMail;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAccount extends EditRecord
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Removes the account from this household; it is deleted only
            // when it belongs to no other one.
            DeleteAction::make()
                // Deleting oneself would end the session mid-click and could
                // leave the household without any parent.
                ->hidden(fn (Membership $record): bool => $record->user_id === auth()->id())
                ->using(function (Membership $record, Memberships $memberships): bool {
                    if ($memberships->isLastManager($record)) {
                        Notification::make()->danger()->title(__('Somebody has to keep managing the household.'))->send();

                        return false;
                    }

                    $memberships->remove($record);

                    return true;
                }),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        return [...$data, 'email' => $record instanceof Membership ? $record->user?->email : null];
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        assert($record instanceof Membership);

        $record->update(['person_id' => $data['person_id']]);

        $user = $record->user;

        if ($user === null) {
            return $record;
        }

        $account = ['name' => Person::query()->whereKey($data['person_id'])->value('name')];

        if (! AccountForm::isShared($record)) {
            $account += array_intersect_key($data, array_flip(['email', 'password']));
        }

        $user->update($account);

        // Without mail an invitation can never be accepted: the parent's password stands in for it.
        if (! OutgoingMail::enabled() && $user->wasChanged('password') && $user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $record;
    }
}
