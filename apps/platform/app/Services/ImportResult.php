<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Household;

/** What a move brought, and what the family has to do again. */
final readonly class ImportResult
{
    /**
     * @param  list<string>  $reconnect  connections to set up again (area: provider)
     * @param  list<string>  $skipped  tables this server does not have (a plugin not installed here)
     */
    public function __construct(
        public Household $household,
        public array $reconnect,
        public array $skipped,
    ) {}
}
