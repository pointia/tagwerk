<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Support\HouseholdContext;
use App\Support\ReadOnlyAdmin;

/**
 * Family areas exist only inside a household. The operator without a chosen
 * household would otherwise see empty lists whose "create" fails for lack of
 * a household_id. A read-only household has them out of the navigation too
 * (`RedirectReadOnlyAdmin` keeps the pages closed).
 */
trait NeedsHousehold
{
    public static function canAccess(): bool
    {
        return app(HouseholdContext::class)->hasId()
            && ! app(ReadOnlyAdmin::class)->applies()
            && parent::canAccess();
    }
}
