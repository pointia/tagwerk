<?php

declare(strict_types=1);

namespace App\Services\Push;

use App\Enums\DeviceKind;
use App\Enums\PersonRole;
use App\Models\Device;
use App\Models\Household;
use App\Support\Locale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Notifications to the phone PWA.
 *
 * Expired subscriptions are deleted, not retried: a browser that has dropped
 * its subscription never gets it back — every further delivery there would be
 * wasted work, and the table would fill up with dead entries.
 */
final class WebPushService
{
    public function isConfigured(): bool
    {
        return $this->vapid()['publicKey'] !== '' && $this->vapid()['privateKey'] !== '';
    }

    /**
     * @param  array<string, array<string, mixed>>  $payloads  language → payload
     * @return int number of delivered notifications
     */
    public function sendToHousehold(int $householdId, array $payloads): int
    {
        return $this->send($this->recipients($householdId), $payloads);
    }

    /**
     * Only to the adults' phones.
     *
     * For messages that concern parents — the daily summary, for instance. A
     * child would otherwise get an evening message about what they did
     * themselves, and when the parents ask about it the surprise would be
     * gone.
     *
     * @param  array<string, array<string, mixed>>  $payloads  language → payload
     */
    public function sendToAdults(int $householdId, array $payloads): int
    {
        return $this->send($this->recipients($householdId, adultsOnly: true), $payloads);
    }

    /**
     * The phones of a household to notify.
     *
     * Wall tablets are left out: they hang on the wall, nobody carries them,
     * and a notification there reaches nobody.
     *
     * @return array<int, Device>
     */
    public function recipients(int $householdId, bool $adultsOnly = false): array
    {
        $devices = Device::withoutHouseholdScope()
            ->where('household_id', $householdId)
            ->where('kind', DeviceKind::Mobile->value)
            ->whereNotNull('push_endpoint');

        // An adult here, whatever the account is in its other households.
        if ($adultsOnly) {
            $devices->whereHas('user.memberships', fn (Builder $membership) => $membership
                ->where('household_id', $householdId)
                ->whereHas('person', fn (Builder $person) => $person->where('role', PersonRole::Adult->value)));
        }

        return $devices->with('user')->get()->all();
    }

    /**
     * Each phone gets the payload in its account's language.
     *
     * @param  array<int, Device>  $devices
     * @param  array<string, array<string, mixed>>  $payloads  language → payload
     */
    public function send(array $devices, array $payloads): int
    {
        if ($devices === [] || $payloads === [] || ! $this->isConfigured()) {
            return 0;
        }

        try {
            $push = new WebPush(['VAPID' => $this->vapid()]);
        } catch (Throwable $exception) {
            Log::warning('Web push is not ready.', ['error' => $exception->getMessage()]);

            return 0;
        }

        /** @var array<string, Device> $byEndpoint */
        $byEndpoint = [];

        foreach ($devices as $device) {
            $byEndpoint[(string) $device->push_endpoint] = $device;

            $push->queueNotification(
                Subscription::create([
                    'endpoint' => (string) $device->push_endpoint,
                    'keys' => [
                        'p256dh' => (string) $device->push_p256dh,
                        'auth' => (string) $device->push_auth,
                    ],
                ]),
                json_encode($this->payloadFor($device, $payloads), JSON_THROW_ON_ERROR),
            );
        }

        $delivered = 0;

        foreach ($push->flush() as $report) {
            if ($report->isSuccess()) {
                $delivered++;

                continue;
            }

            // Every household's row of that phone: the endpoint is gone for all.
            if ($report->isSubscriptionExpired()) {
                Device::withoutHouseholdScope()
                    ->where('push_endpoint', $report->getEndpoint())
                    ->get()
                    ->each(fn (Device $device) => $device->forceFill([
                        'push_endpoint' => null,
                        'push_p256dh' => null,
                        'push_auth' => null,
                        'push_subscribed_at' => null,
                    ])->save());

                continue;
            }

            Log::info('Notification not delivered.', [
                'endpoint' => $report->getEndpoint(),
                'reason' => $report->getReason(),
            ]);
        }

        return $delivered;
    }

    /** @return array{subject: string, publicKey: string, privateKey: string} */
    private function vapid(): array
    {
        return [
            'subject' => (string) config('services.webpush.subject'),
            'publicKey' => (string) config('services.webpush.public_key'),
            'privateKey' => (string) config('services.webpush.private_key'),
        ];
    }

    /**
     * What one phone gets: its account's language, where a tap leads, and
     * for a phone in several households whose it is — "Müller · Reward
     * requested". A tap opens that household.
     *
     * @param  array<string, array<string, mixed>>  $payloads  language → payload
     * @return array<string, mixed>
     */
    public function payloadFor(Device $device, array $payloads): array
    {
        // Without a language of its own the account reads that household's.
        $reader = $device->user?->locale !== null ? $device->user : $device;
        $payload = $payloads[Locale::for($reader)] ?? reset($payloads);
        $householdId = (int) $device->household_id;
        $payload['household_id'] = $householdId;

        $url = (string) ($payload['url'] ?? '/');
        $payload['url'] = $url.(str_contains($url, '?') ? '&' : '?').'household='.$householdId;

        if ($device->user !== null && $device->user->memberships()->accepted()->count() > 1) {
            $name = Household::query()->whereKey($householdId)->value('name');

            if (is_string($name) && isset($payload['title'])) {
                $payload['title'] = $name.' · '.$payload['title'];
            }
        }

        return $payload;
    }
}
