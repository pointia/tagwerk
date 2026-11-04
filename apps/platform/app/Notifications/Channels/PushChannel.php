<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Enums\DeviceKind;
use App\Models\Device;
use App\Models\User;
use App\Notifications\HouseholdMessage;
use App\Services\Push\WebPushService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Web push to the phones an account subscribed in the message's
 * household. Laravel renders the message in the account's language
 * already, so there is one payload.
 *
 * Failures are logged, never thrown: with the `sync` queue a push service
 * outage would otherwise fail the request that caused the message.
 */
final class PushChannel
{
    public function __construct(private readonly WebPushService $push) {}

    public function send(object $notifiable, HouseholdMessage $message): void
    {
        $payload = $message->toPush($notifiable);

        if (! $notifiable instanceof User || $payload === null) {
            return;
        }

        try {
            $phones = Device::withoutHouseholdScope()
                ->where('household_id', $message->householdId)
                ->where('user_id', $notifiable->getKey())
                ->where('kind', DeviceKind::Mobile->value)
                ->whereNotNull('push_endpoint')
                ->with('user')
                ->get()
                ->all();

            $this->push->send($phones, [app()->getLocale() => $payload]);
        } catch (Throwable $exception) {
            Log::warning('Could not send notification.', [
                'household_id' => $message->householdId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
