<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a person or a wall device may change. Reading is not a permission:
 * the wall shows everything anyway.
 *
 * "Own" means: the actor's person is among the people concerned. The matching
 * "all" permission includes it.
 */
enum Permission: string
{
    case CalendarEditOwn = 'calendar.edit_own';
    case CalendarEditAll = 'calendar.edit_all';
    case TasksCompleteOwn = 'tasks.complete_own';
    case TasksCompleteAll = 'tasks.complete_all';
    case TasksManage = 'tasks.manage';
    case ListsEdit = 'lists.edit';
    case MealsEdit = 'meals.edit';

    case RecipesEdit = 'recipes.edit';
    case TimetableChangeOwn = 'timetable.change_own';
    case TimetableChangeAll = 'timetable.change_all';
    case RewardsRequest = 'rewards.request';
    case PointsAward = 'points.award';
    case PhotosUpload = 'photos.upload';
    case DashboardArrange = 'dashboard.arrange';
    case HouseholdManage = 'household.manage';

    public function label(): string
    {
        return match ($this) {
            self::CalendarEditOwn => __('Create and change own events'),
            self::CalendarEditAll => __('Change all events, including family events'),
            self::TasksCompleteOwn => __('Tick off own tasks'),
            self::TasksCompleteAll => __('Tick off tasks for everyone'),
            self::TasksManage => __('Create, change and assign tasks'),
            self::ListsEdit => __('Edit lists'),
            self::MealsEdit => __('Edit meal plan'),
            self::RecipesEdit => __('Write and change recipes'),
            self::TimetableChangeOwn => __('Change own timetable'),
            self::TimetableChangeAll => __('Change all timetables'),
            self::RewardsRequest => __('Redeem rewards'),
            self::PointsAward => __('Award stars and approve redemptions'),
            self::PhotosUpload => __('Upload photos for the photo frame'),
            self::DashboardArrange => __('Arrange dashboard'),
            self::HouseholdManage => __('Open admin panel'),
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::CalendarEditOwn, self::CalendarEditAll => __('Calendar'),
            self::TasksCompleteOwn, self::TasksCompleteAll, self::TasksManage => __('Tasks'),
            self::ListsEdit, self::MealsEdit, self::RecipesEdit => __('Lists and meals'),
            self::TimetableChangeOwn, self::TimetableChangeAll => __('Timetable'),
            self::RewardsRequest, self::PointsAward => __('Stars'),
            self::PhotosUpload => __('Photos'),
            self::DashboardArrange, self::HouseholdManage => __('Wall and admin panel'),
        };
    }

    /** The permission that includes this one, if any. */
    public function widerPermission(): ?self
    {
        return match ($this) {
            self::CalendarEditOwn => self::CalendarEditAll,
            self::TasksCompleteOwn => self::TasksCompleteAll,
            self::TimetableChangeOwn => self::TimetableChangeAll,
            default => null,
        };
    }

    /** @return list<self> */
    public static function defaultsFor(PersonRole $role): array
    {
        return match ($role) {
            PersonRole::Adult => self::cases(),
            PersonRole::Child => [
                self::CalendarEditOwn,
                self::TasksCompleteOwn,
                self::ListsEdit,
                self::TimetableChangeOwn,
                self::RewardsRequest,
            ],
            // Grandparents and the like: they join in, but neither run the
            // household nor manage its tasks.
            PersonRole::Guest => [
                self::CalendarEditOwn,
                self::TasksCompleteOwn,
                self::ListsEdit,
                self::PointsAward,
                self::PhotosUpload,
            ],
        };
    }

    /**
     * Exactly what the wall could do without the PIN before permissions
     * existed. Everything else needs the PIN, which grants all.
     *
     * @return list<self>
     */
    public static function deviceDefaults(): array
    {
        return [
            self::CalendarEditAll,
            self::TasksCompleteAll,
            self::ListsEdit,
            self::MealsEdit,
            self::TimetableChangeAll,
            self::RewardsRequest,
        ];
    }

    /**
     * Stored values back to cases; unknown ones (a removed permission) are
     * dropped instead of failing.
     *
     * @param  array<int, mixed>  $values
     * @return list<self>
     */
    public static function fromStored(array $values): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $value): ?self => is_string($value) ? self::tryFrom($value) : null,
            $values,
        )));
    }
}
