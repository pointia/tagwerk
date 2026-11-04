<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Areas\ApiArea;
use App\Auth\ApiTokens;
use App\Filament\Concerns\BelongsToArea;
use App\Filament\NavigationGroup;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Support\HouseholdContext;
use App\Support\HouseholdTimezone;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Integrations → API access: the signed-in account's tokens for other programs
 * in the current household. Each one is shown once, at creation.
 */
class ApiAccess extends Page implements HasTable
{
    use BelongsToArea {
        BelongsToArea::canAccess as private canAccessArea;
    }
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Integrations;

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'api-access';

    public static function getNavigationLabel(): string
    {
        return __('API access');
    }

    public function getTitle(): string
    {
        return __('API access');
    }

    public static function area(): string
    {
        return ApiArea::KEY;
    }

    public static function canAccess(): bool
    {
        return auth()->user() instanceof User && static::canAccessArea();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Text::make(__('Tokens let other programs — Home Assistant, a script, an AI assistant — act as you in this household. A token can never do more than your account.')),
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => app(ApiTokens::class)->of(self::user())
                ->where('household_id', app(HouseholdContext::class)->id()))
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->formatStateUsing(fn (string $state): string => substr($state, strlen(ApiTokens::PREFIX))),
                IconColumn::make('abilities')
                    ->label(__('May write'))
                    ->state(fn (PersonalAccessToken $record): bool => in_array(ApiTokens::WRITE, (array) $record->abilities, true))
                    ->boolean(),
                TextColumn::make('last_used_at')
                    ->label(__('Last used'))
                    ->dateTime()
                    ->timezone(fn (): string => HouseholdTimezone::current())
                    ->placeholder(__('Never')),
                TextColumn::make('expires_at')
                    ->label(__('Expires'))
                    ->date()
                    ->timezone(fn (): string => HouseholdTimezone::current())
                    ->placeholder(__('Never')),
            ])
            ->headerActions([
                Action::make('create')
                    ->label(__('New token'))
                    ->schema([
                        TextInput::make('label')->label(__('Name'))->required()->maxLength(60),
                        Toggle::make('write')->label(__('May write'))
                            ->helperText(__('Without it the token only reads.')),
                        DatePicker::make('expires_on')->label(__('Expires'))->minDate(now()->addDay()),
                    ])
                    ->action(function (array $data): void {
                        $plain = app(ApiTokens::class)->create(
                            self::user(),
                            (int) app(HouseholdContext::class)->id(),
                            (string) $data['label'],
                            (bool) ($data['write'] ?? false),
                            isset($data['expires_on'])
                                ? CarbonImmutable::parse((string) $data['expires_on'], HouseholdTimezone::current())->endOfDay()
                                : null,
                        );

                        Notification::make()
                            ->title(__('Copy the token now — it is not shown again.'))
                            ->body($plain)
                            ->persistent()
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                DeleteAction::make()->label(__('Revoke')),
            ])
            ->emptyStateHeading(__('No tokens yet'));
    }

    private static function user(): User
    {
        $user = auth()->user();
        assert($user instanceof User);

        return $user;
    }
}
