<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Push\WebPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends a notification to a household's phones.
 *
 * A job, not part of the request: sending talks to several push services
 * and must not hold up the response to the wall.
 *
 * Failures are caught and logged, not rethrown. With the `sync` driver —
 * common in tests and small installations — a push failure would otherwise
 * break the response, and a reward request would fail although it is long
 * saved.
 */
final class SendPushNotificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, array<string, mixed>>  $payloads  language → payload, see Locale::inEvery()
     * @param  bool  $adultsOnly  Limits delivery to the parents' phones.
     */
    public function __construct(
        private readonly int $householdId,
        private readonly array $payloads,
        private readonly bool $adultsOnly = false,
    ) {}

    public function handle(WebPushService $push): void
    {
        try {
            $delivered = $this->adultsOnly
                ? $push->sendToAdults($this->householdId, $this->payloads)
                : $push->sendToHousehold($this->householdId, $this->payloads);

            // Otherwise a household without subscribed phones waits for
            // notifications that never come, and nothing says why.
            if ($delivered === 0) {
                Log::info('Notification reached no phone.', [
                    'household_id' => $this->householdId,
                    'adults_only' => $this->adultsOnly,
                    'configured' => $push->isConfigured(),
                    'phones' => count($push->recipients($this->householdId, $this->adultsOnly)),
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('Could not send notification.', [
                'household_id' => $this->householdId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
