<?php

declare(strict_types=1);

namespace App\Filament\Resources\SchoolClosures;

use App\Enums\Feature;
use App\Filament\NavigationGroup;
use App\Filament\Resources\SchoolClosures\Pages\ListSchoolClosures;
use App\Filament\Resources\SchoolClosures\Schemas\SchoolClosureForm;
use App\Filament\Resources\SchoolClosures\Tables\SchoolClosuresTable;
use App\Models\Household;
use App\Models\SchoolClosure;
use App\Support\HouseholdContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SchoolClosureResource extends Resource
{
    protected static ?string $model = SchoolClosure::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Sun;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Calendar;

    protected static ?int $navigationSort = 21;

    public static function getModelLabel(): string
    {
        return __('school closure');
    }

    public static function getPluralModelLabel(): string
    {
        return __('School closures');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::isEnabled();
    }

    /** Same reasoning as in `LessonResource`. */
    public static function canAccess(): bool
    {
        return self::isEnabled();
    }

    private static function isEnabled(): bool
    {
        $household = Household::query()->find(app(HouseholdContext::class)->id());

        // Only inside a household; the operator without one has no timetable.
        return $household !== null && $household->has(Feature::Timetable);
    }

    public static function form(Schema $schema): Schema
    {
        return SchoolClosureForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SchoolClosuresTable::configure($table);
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListSchoolClosures::route('/'),
        ];
    }
}
