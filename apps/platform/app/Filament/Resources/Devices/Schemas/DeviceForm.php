<?php

declare(strict_types=1);

namespace App\Filament\Resources\Devices\Schemas;

use App\Enums\DeviceKind;
use App\Enums\Feature;
use App\Enums\Permission;
use App\Filament\Schemas\PermissionsSection;
use App\Models\Calendar;
use App\Models\Household;
use App\Models\PhotoAlbum;
use App\Support\HouseholdContext;
use App\Support\Locale;
use App\Support\Preferences;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class DeviceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Title'))
                    ->helperText(__('Where the device hangs, for example “Kitchen” or “Hallway”.'))
                    ->required()
                    ->maxLength(255),
                Select::make('kind')
                    ->label(__('Kind'))
                    ->options(collect(DeviceKind::cases())
                        ->mapWithKeys(fn (DeviceKind $kind): array => [
                            $kind->value => $kind->label(),
                        ])
                        ->all())
                    ->default(DeviceKind::Wall->value)
                    ->required(),
                Select::make('locale')
                    ->label(__('Language'))
                    ->options(Locale::supported())
                    ->placeholder(__('As the household')),

                // Stored as the hidden ones (EditDevice): a calendar shown later appears by itself.
                CheckboxList::make('shown_calendar_ids')
                    ->label(__('Calendars on this device'))
                    ->helperText(__('You can choose from the calendars shown under Calendars.'))
                    ->options(fn (): array => Calendar::query()
                        ->where('is_active', true)
                        ->orderBy('summary')
                        ->pluck('summary', 'id')
                        ->all())
                    ->bulkToggleable()
                    ->columns(2)
                    // Filled and saved by EditDevice; a new device shows all.
                    ->visibleOn('edit'),

                // Stored in `preferences`, the same place the wall writes to.
                Section::make(__('Resting mode'))
                    ->description(__('After a while without a touch the wall goes dark and shows the chosen areas in turn. Without areas it stays as it is.'))
                    ->columns(2)
                    ->schema([
                        Select::make('preferences.screensaver_areas')
                            ->label(__('Areas, in this order'))
                            ->multiple()
                            ->options(self::screensaverAreas())
                            ->placeholder(__('Off'))
                            ->columnSpanFull(),
                        Select::make('preferences.screensaver_idle')
                            ->label(__('After'))
                            ->options(fn (): array => collect(['1', '2', '5', '10', '15'])
                                ->mapWithKeys(fn (string $minutes): array => [$minutes => trans_choice(':count minute|:count minutes', (int) $minutes)])
                                ->all())
                            ->default('5')
                            ->selectablePlaceholder(false),
                        Select::make('preferences.screensaver_interval')
                            ->label(__('Switch every'))
                            ->options(fn (): array => collect(['15', '30', '60'])
                                ->mapWithKeys(fn (string $seconds): array => [$seconds => trans_choice(':count second|:count seconds', (int) $seconds)])
                                ->all())
                            ->default('30')
                            ->selectablePlaceholder(false),
                        Slider::make('preferences.screensaver_dim')
                            ->label(__('Darkening by day'))
                            ->helperText(__('In percent. Fully Kiosk also lowers the real brightness by this share.'))
                            ->range(0, 90)
                            ->step(5)
                            ->tooltips()
                            ->default(50)
                            ->columnSpanFull(),
                        Slider::make('preferences.screensaver_dim_night')
                            ->label(__('Darkening at night'))
                            ->helperText(__('Applies while the household shows the dark theme.'))
                            ->range(0, 90)
                            ->step(5)
                            ->tooltips()
                            ->default(70)
                            ->columnSpanFull(),
                    ])
                    ->visibleOn('edit'),

                // Stored in `preferences` as well; the wall's settings show the same.
                Section::make(__('Photo frame'))
                    ->description(__('How this tablet shows the household’s pictures.'))
                    ->columns(2)
                    ->schema([
                        Select::make('preferences.photo_style')
                            ->label(__('Layout'))
                            ->options(fn (): array => self::photoStyles())
                            ->selectablePlaceholder(false),
                        Select::make('preferences.photo_rest_style')
                            ->label(__('Layout at rest'))
                            ->options(fn (): array => ['same' => __('Same as above'), ...self::photoStyles()])
                            ->selectablePlaceholder(false),
                        Select::make('preferences.photo_source')
                            ->label(__('Pictures'))
                            ->options(fn (): array => [
                                'all' => __('All pictures'),
                                'favorites' => __('Favourites'),
                                'albums' => __('Chosen albums'),
                            ])
                            ->live()
                            ->selectablePlaceholder(false),
                        Select::make('preferences.photo_albums')
                            ->label(__('Albums'))
                            ->multiple()
                            ->options(fn (): array => PhotoAlbum::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->visible(fn (Get $get): bool => $get('preferences.photo_source') === 'albums'),
                        Select::make('preferences.photo_interval')
                            ->label(__('Change every'))
                            ->options(fn (): array => collect(['10', '30', '60', '300'])
                                ->mapWithKeys(fn (string $seconds): array => [$seconds => (int) $seconds >= 60
                                    ? trans_choice(':count minute|:count minutes', intdiv((int) $seconds, 60))
                                    : trans_choice(':count second|:count seconds', (int) $seconds)])
                                ->all())
                            ->selectablePlaceholder(false),
                        Select::make('preferences.photo_fit')
                            ->label(__('Picture'))
                            ->options(fn (): array => ['fit' => __('Whole picture'), 'fill' => __('Fill the frame')])
                            ->selectablePlaceholder(false),
                        Select::make('preferences.photo_motion')
                            ->label(__('Slow zoom'))
                            ->options(fn (): array => ['on' => __('On'), 'off' => __('Off')])
                            ->selectablePlaceholder(false),
                        Select::make('preferences.photo_caption')
                            ->label(__('Caption and date'))
                            ->options(fn (): array => ['on' => __('On'), 'off' => __('Off')])
                            ->selectablePlaceholder(false),
                        Select::make('preferences.photo_clock')
                            ->label(__('Clock over the picture'))
                            ->options(fn (): array => ['off' => __('Off'), 'on' => __('On')])
                            ->selectablePlaceholder(false),
                    ])
                    // Filled and saved by EditDevice, like resting mode.
                    ->visible(fn (string $operation): bool => $operation === 'edit'
                        && (Household::query()->find(app(HouseholdContext::class)->id())?->has(Feature::Photos) ?? false)),

                PermissionsSection::make(
                    __('What works on this device without a PIN. The parent PIN unlocks everything for a few minutes.'),
                    fn (): array => Permission::deviceDefaults(),
                ),
            ]);
    }

    /** @return array<string, string> */
    private static function photoStyles(): array
    {
        return [
            'single' => __('One picture'),
            'duo' => __('Two side by side'),
            'mosaic' => __('Mosaic'),
            'ambient' => __('Picture with clock and appointments'),
            'memories' => __('Memories'),
        ];
    }

    /** @return array<string, string> the household's areas that may rest on the wall */
    private static function screensaverAreas(): array
    {
        $household = Household::query()->find(app(HouseholdContext::class)->id());
        $options = [];

        foreach (Feature::cases() as $feature) {
            if (in_array($feature->wallMode(), Preferences::SCREENSAVER_AREAS, true) && ($household === null || $household->has($feature))) {
                $options[$feature->wallMode()] = $feature->label();
            }
        }

        return $options;
    }
}
