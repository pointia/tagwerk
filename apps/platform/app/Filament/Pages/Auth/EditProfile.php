<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Models\Household;
use App\Models\User;
use App\Services\AccountDeletion;
use App\Services\ParentPin;
use App\Support\Locale;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\Contracts\MultiFactorAuthenticationProvider;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Filament's profile plus the parent PIN.
 *
 * Every parent keeps their own PIN here, next to their password: it belongs
 * to the person, not to the household, and nobody has to know the other's.
 */
class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
                $this->getLocaleFormComponent(),
                $this->getPinSection(),
                $this->getDeleteAccountSection(),
            ]);
    }

    /**
     * Self-service: where this account is the last manager the household
     * goes too, named before and confirmed by its typed name.
     */
    private function getDeleteAccountSection(): Section
    {
        return Section::make(__('Delete account'))
            ->visible(fn (): bool => $this->profileUser()->is_operator !== true)
            ->schema([
                Actions::make([
                    Action::make('deleteAccount')
                        ->label(__('Delete account'))
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading(__('Delete account'))
                        ->modalDescription(function (AccountDeletion $deletion): string {
                            $lost = array_map(fn (Household $household): string => $household->name, $deletion->householdsLost($this->profileUser()));

                            return $lost === []
                                ? __('Your account and its sign-ins go; the households you belong to keep everything.')
                                : __('You are the last one managing :households. It is deleted with your account — calendars, tasks, stars, photos, recipes. Download an export first if you want to keep anything.', ['households' => implode(', ', $lost)]);
                        })
                        ->schema(fn (AccountDeletion $deletion): array => [
                            TextInput::make('password')
                                ->label(__('Current password'))
                                ->password()
                                ->required(),
                            ...array_map(fn (Household $household): TextInput => TextInput::make('confirm.'.$household->id)
                                ->label(__('Type :name to confirm', ['name' => $household->name]))
                                ->required(), $deletion->householdsLost($this->profileUser())),
                        ])
                        ->action(function (array $data, AccountDeletion $deletion, Action $action) {
                            try {
                                $deletion->delete($this->profileUser(), (string) $data['password'], array_values(array_map('strval', $data['confirm'] ?? [])));
                            } catch (ValidationException $e) {
                                Notification::make()->danger()->title((string) collect($e->errors())->flatten()->first())->send();
                                $action->halt();
                            }

                            Filament::auth()->logout();
                            session()->invalidate();
                            session()->regenerateToken();

                            return redirect('/');
                        }),
                ]),
            ]);
    }

    /** Also the phone's language; the app offers the same choice. */
    private function getLocaleFormComponent(): Select
    {
        return Select::make('locale')
            ->label(__('Language'))
            ->options(Locale::supported())
            // The operator has no household to follow.
            ->placeholder(fn (): string => $this->profileUser()->memberships()->doesntExist()
                ? Locale::supported()[config('app.locale')]
                : __('As the household'));
    }

    private function getPinSection(): Section
    {
        $hasPin = fn (): bool => $this->profileUser()->pin_hash !== null;

        return Section::make(__('Parent PIN'))
            ->description(__('Unlocks on the wall tablet what children should not do themselves: correct stars, approve rewards, rearrange the dashboard. Every parent has their own. As long as nobody has set one, everything is allowed on the wall.'))
            // The operator belongs to no household and has no wall to unlock.
            ->visible(fn (): bool => $this->profileUser()->memberships()->exists())
            ->columns(2)
            ->schema([
                TextInput::make('new_pin')
                    ->label(fn (): string => $hasPin() ? __('New PIN') : __('Set PIN'))
                    ->helperText(fn (): string => $hasPin()
                        ? __('Your PIN is set. Leave empty to keep it.')
                        : __('No PIN yet. 4 to 12 digits.'))
                    ->password()
                    ->revealable()
                    ->inputMode('numeric')
                    ->autocomplete('new-password')
                    ->regex('/^\d{4,12}$/')
                    ->validationMessages(['regex' => __('The PIN consists of 4 to 12 digits.')]),
                Toggle::make('remove_pin')
                    ->label(__('Remove PIN'))
                    ->helperText(__('Your PIN then no longer unlocks the wall.'))
                    ->visible($hasPin),
            ]);
    }

    /**
     * The PIN is no column of the form: hashed on its way in, never read back.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(#[SensitiveParameter] array $data): array
    {
        $pin = app(ParentPin::class);

        if (($data['remove_pin'] ?? false) === true) {
            $pin->set($this->profileUser(), null);
        } elseif (filled($data['new_pin'] ?? null)) {
            $pin->set($this->profileUser(), (string) $data['new_pin']);
        }

        unset($data['new_pin'], $data['remove_pin']);

        return parent::mutateFormDataBeforeSave($data);
    }

    /**
     * Filament's section for a second factor, shown only while an extension
     * offers one: an installed but switched-off extension leaves its
     * providers with nothing to manage.
     */
    public function getMultiFactorAuthenticationContentComponent(): ?Component
    {
        return parent::getMultiFactorAuthenticationContentComponent()?->visible(
            fn (): bool => array_any(
                Filament::getMultiFactorAuthenticationProviders(),
                fn (MultiFactorAuthenticationProvider $provider): bool => $provider->getManagementSchemaComponents() !== [],
            ),
        );
    }

    private function profileUser(): User
    {
        $user = $this->getUser();
        assert($user instanceof User);

        return $user;
    }
}
