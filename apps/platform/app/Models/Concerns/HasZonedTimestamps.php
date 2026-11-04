<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * Writes timestamps to the database including their zone offset.
 *
 * Laravel's default date format is "Y-m-d H:i:s" and drops the offset.
 * Carbon then formats in its own zone and PostgreSQL reads the result in the
 * session zone UTC — Vienna midnight becomes 02:00 Vienna time. The wall
 * clock time survives, the instant does not.
 *
 * That hits every write, not only the seeder: the Google sync builds its
 * instants in the calendar's zone and lost them just the same.
 *
 * Every model with timestampTz columns needs this trait. It does no harm on
 * created_at/updated_at: those columns have no zone, Laravel produces their
 * values in the app zone UTC, and PostgreSQL drops the offset when writing
 * to a column without zone.
 */
trait HasZonedTimestamps
{
    /**
     * Like Laravel's default, but with the zone offset (P).
     *
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:sP';
}
