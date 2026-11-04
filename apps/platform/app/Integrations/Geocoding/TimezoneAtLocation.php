<?php

declare(strict_types=1);

namespace App\Integrations\Geocoding;

use DateTimeZone;

/**
 * Which time zone applies at a place.
 *
 * No external service: PHP ships the time zone database, including the
 * country mapping and the coordinates of every zone. Most countries have
 * exactly one zone; where there are several, the nearest wins. That is rough,
 * but time zone borders run along countries and states anyway.
 */
final class TimezoneAtLocation
{
    public function forCountry(string $countryCode, float $latitude, float $longitude): ?string
    {
        $zones = DateTimeZone::listIdentifiers(
            DateTimeZone::PER_COUNTRY,
            mb_strtoupper($countryCode),
        );

        if ($zones === []) {
            return null;
        }

        if (count($zones) === 1) {
            return $zones[0];
        }

        $nearest = null;
        $shortest = INF;

        foreach ($zones as $zone) {
            $location = (new DateTimeZone($zone))->getLocation();

            if ($location === false) {
                continue;
            }

            /*
             * Squared distance in degrees is enough: only which zone is closer
             * gets compared. Taking the root would not change the order.
             */
            $distance = ($location['latitude'] - $latitude) ** 2
                + ($location['longitude'] - $longitude) ** 2;

            if ($distance < $shortest) {
                $shortest = $distance;
                $nearest = $zone;
            }
        }

        return $nearest;
    }
}
