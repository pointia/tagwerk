<?php

declare(strict_types=1);

namespace App\Integrations;

use Tagwerk\PluginApi\Calendar\CalendarProvider;
use Tagwerk\PluginApi\Geocoding\Geocoder;
use Tagwerk\PluginApi\Holidays\HolidaySource;
use Tagwerk\PluginApi\Lists\ListProvider;
use Tagwerk\PluginApi\Photos\PhotoSource;
use Tagwerk\PluginApi\Recipes\RecipeProvider;
use Tagwerk\PluginApi\SchoolHolidays\SchoolHolidaySource;
use Tagwerk\PluginApi\Tasks\TaskProvider;
use Tagwerk\PluginApi\Weather\WeatherSource;

/**
 * Which contract a provider must fulfil per area.
 *
 * One place for the catalog, which checks plugins when they are offered, and
 * for the contract tests, which check every registered provider. A new area
 * without an entry here stands out in those tests.
 */
final class ProviderContracts
{
    /** @var array<string, class-string> */
    public const BASE = [
        'calendar' => CalendarProvider::class,
        'lists' => ListProvider::class,
        'tasks' => TaskProvider::class,
        'recipes' => RecipeProvider::class,
        'photos' => PhotoSource::class,
        'geocoding' => Geocoder::class,
        'weather' => WeatherSource::class,
        'holidays' => HolidaySource::class,
        'school_holidays' => SchoolHolidaySource::class,
    ];
}
