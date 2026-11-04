<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Checklist;
use App\Services\Lists\ListSyncService;
use App\Support\HouseholdContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SyncListJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $checklistId,

        /** Right after linking; see `ListSyncService::pull()`. */
        public readonly bool $first = false,
    ) {}

    public function handle(ListSyncService $sync, HouseholdContext $context): void
    {
        $checklist = Checklist::withoutHouseholdScope()->find($this->checklistId);

        if ($checklist === null) {
            return;
        }

        /*
         * Caught, not thrown: the run repeats every few minutes, and a
         * provider outage would otherwise fill the failed jobs with the same
         * entry over and over.
         */
        try {
            $context->forId($checklist->household_id, fn () => $sync->pull($checklist, $this->first));
        } catch (Throwable $exception) {
            Log::warning('Could not pull the list from its provider.', [
                'list' => $this->checklistId,
                'reason' => $exception->getMessage(),
            ]);
        }
    }
}
