<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Gender;
use App\Models\Person;
use Carbon\CarbonImmutable;

/**
 * How much of a portion a person eats, relative to an adult man (1.0).
 *
 * From the reference values for energy intake of DGE/ÖGE/SGE at a low
 * physical activity level (PAL 1.4) — the one that fits a family dinner, not
 * an athlete. A recipe "for 4" means four adult portions; a household of two
 * adults and two small children needs about three.
 *
 * Without a gender the mean of both; without a birthday the adult mean.
 */
final class PortionFactor
{
    /** kcal per day: [from age, male, female], ascending. */
    private const ENERGY = [
        [0, 650, 600],
        [1, 1200, 1100],
        [4, 1400, 1300],
        [7, 1700, 1500],
        [10, 1900, 1700],
        [13, 2300, 1900],
        [15, 2600, 2000],
        [19, 2400, 1900],
        [25, 2300, 1800],
        [51, 2200, 1700],
        [65, 2100, 1700],
    ];

    /** The adult man of 25 to 50 everything is measured against. */
    private const REFERENCE = 2300;

    private const ADULT = 25;

    public function for(Person $person, ?CarbonImmutable $on = null): float
    {
        $age = $person->birthday === null
            ? self::ADULT
            : (int) $person->birthday->diffInYears($on ?? CarbonImmutable::now(), absolute: true);

        return self::factor($age, $person->gender);
    }

    /**
     * Portions for a group of people, one decimal — never less than one.
     *
     * @param  iterable<Person>  $people
     */
    public function servings(iterable $people, ?CarbonImmutable $on = null): float
    {
        $sum = 0.0;

        foreach ($people as $person) {
            $sum += $this->for($person, $on);
        }

        return max(1.0, round($sum, 1));
    }

    public static function factor(int $age, ?Gender $gender): float
    {
        $row = self::ENERGY[0];

        foreach (self::ENERGY as $band) {
            if ($age >= $band[0]) {
                $row = $band;
            }
        }

        $energy = match ($gender) {
            Gender::Male => $row[1],
            Gender::Female => $row[2],
            null => ($row[1] + $row[2]) / 2,
        };

        return round($energy / self::REFERENCE, 2);
    }
}
