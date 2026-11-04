<?php

declare(strict_types=1);

namespace App\Filament\Resources\People\Schemas;

use App\Enums\Feature;
use App\Enums\Gender;
use App\Enums\Permission;
use App\Enums\PersonRole;
use App\Exceptions\InvalidImage;
use App\Filament\Schemas\PermissionsSection;
use App\Models\Household;
use App\Services\AvatarImage;
use App\Support\HouseholdContext;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class PersonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->maxLength(255),
                FileUpload::make('avatar_path')
                    ->label(__('Picture'))
                    ->helperText(__('Appears on the wall next to the name. After choosing, pick the crop: drag to move, mouse wheel or two fingers to zoom.'))
                    ->avatar()
                    // After avatar(): it would otherwise set image/* and let SVG with scripts through.
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    /*
                     * The cropper opens by itself for anything not square, so
                     * the face is chosen instead of cut off in the middle.
                     * The browser then scales to the stored size: a phone
                     * photo of several megabytes leaves as a small square.
                     */
                    ->imageEditor()
                    ->circleCropper()
                    ->imageEditorAspectRatioOptions(['1:1'])
                    ->automaticallyCropImagesToAspectRatio(false)
                    ->automaticallyOpenImageEditorForAspectRatio()
                    ->automaticallyResizeImagesToWidth((string) AvatarImage::EDGE)
                    ->automaticallyResizeImagesToHeight((string) AvatarImage::EDGE)
                    ->disk('public')
                    // The original may be large; it is scaled down before upload.
                    ->maxSize(20480)
                    // Never the uploaded file is stored, but a freshly redrawn image.
                    ->saveUploadedFileUsing(function (FileUpload $component, TemporaryUploadedFile $file): string {
                        try {
                            return app(AvatarImage::class)->store($file->get());
                        } catch (InvalidImage $error) {
                            throw ValidationException::withMessages([$component->getStatePath() => $error->getMessage()]);
                        }
                    }),
                ColorPicker::make('color')
                    ->label(__('Colour'))
                    ->required(),
                Select::make('role')
                    ->label(__('Role'))
                    ->options(collect(PersonRole::cases())
                        ->mapWithKeys(fn (PersonRole $role): array => [
                            $role->value => $role->label(),
                        ])
                        ->all())
                    ->default(PersonRole::Child->value)
                    ->live()
                    ->required(),
                Select::make('gender')
                    ->label(__('Gender'))
                    ->options(collect(Gender::cases())
                        ->mapWithKeys(fn (Gender $gender): array => [$gender->value => $gender->label()])
                        ->all())
                    ->placeholder(__('Not stated'))
                    ->helperText(__('Only for portion sizes in recipes. Without it, the mean is used.')),
                TextInput::make('weekly_goal')
                    ->label(__('Weekly goal'))
                    ->helperText(__('Stars per week. 0 hides the display.'))
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
                Select::make('savings_reward_id')
                    ->label(__('Saving up for'))
                    ->helperText(__('Shows on the wall how far there is still to go.'))
                    ->relationship('savingsReward', 'name')
                    ->searchable()
                    ->preload(),
                DatePicker::make('birthday')
                    ->label(__('Birthday'))
                    ->native(false)
                    ->firstDayOfWeek(1)
                    ->maxDate(now()),

                PermissionsSection::make(
                    __('What this person may change on the phone and in the admin panel. Everyone may view everything.'),
                    fn (Get $get): array => Permission::defaultsFor(PersonRole::tryFrom((string) $get('role')) ?? PersonRole::Child),
                ),

                Section::make(__('School bell schedule'))
                    ->description(__('Enter it once — after that “Monday, 1st lesson” is enough in the timetable.'))
                    ->collapsed()
                    ->columnSpanFull()
                    // Only when the timetable is enabled: otherwise there would
                    // be a field here for an area that does not exist.
                    // And only for children: a parent has no school to go to.
                    ->visible(fn (Get $get): bool => self::timetableEnabled()
                        && PersonRole::tryFrom((string) $get('role')) === PersonRole::Child)
                    ->schema([
                        Repeater::make('lessonPeriods')
                            ->hiddenLabel()
                            ->relationship()
                            ->addActionLabel(__('Add lesson'))
                            ->reorderable(false)
                            ->defaultItems(0)
                            ->columns(3)
                            ->itemLabel(fn (array $state): ?string => isset($state['number'])
                                ? __('Period :number', ['number' => $state['number']])
                                : null)
                            ->schema([
                                TextInput::make('number')
                                    ->label(__('Number'))
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->maxValue(20)
                                    ->required(),

                                TimePicker::make('starts_at')
                                    ->label(__('From'))
                                    ->seconds(false)
                                    ->required(),

                                TimePicker::make('ends_at')
                                    ->label(__('To'))
                                    ->seconds(false)
                                    ->required(),
                            ]),
                    ]),
            ]);
    }

    private static function timetableEnabled(): bool
    {
        $household = Household::query()->find(app(HouseholdContext::class)->id());

        return $household === null || $household->has(Feature::Timetable);
    }
}
