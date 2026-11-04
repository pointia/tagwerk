<?php

declare(strict_types=1);

namespace App\Filament\Resources\Lessons;

use App\Enums\Feature;
use App\Filament\NavigationGroup;
use App\Filament\Resources\Lessons\Pages\CreateLesson;
use App\Filament\Resources\Lessons\Pages\EditLesson;
use App\Filament\Resources\Lessons\Pages\ListLessons;
use App\Filament\Resources\Lessons\Pages\TimetableGrid;
use App\Filament\Resources\Lessons\Schemas\LessonForm;
use App\Filament\Resources\Lessons\Tables\LessonsTable;
use App\Models\Household;
use App\Models\Lesson;
use App\Support\HouseholdContext;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class LessonResource extends Resource
{
    protected static ?string $model = Lesson::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::AcademicCap;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Calendar;

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return __('Lesson');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Timetable');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::isEnabled();
    }

    /**
     * Disabled means disabled here too.
     *
     * Hiding the navigation is not enough: a bookmark to /admin/lessons would
     * otherwise still lead into a form for an area that no longer exists.
     */
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
        return LessonForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LessonsTable::configure($table);
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            // The grid first: a timetable is read as a week, not as rows.
            'index' => TimetableGrid::route('/'),
            'list' => ListLessons::route('/list'),
            'create' => CreateLesson::route('/create'),
            'edit' => EditLesson::route('/{record}/edit'),
        ];
    }
}
