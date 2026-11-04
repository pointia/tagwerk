<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Jobs\AnnounceHouseholdUpdateJob;
use App\Notifications\HouseholdMessage;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

/**
 * Laravel's database channel, plus the household the message is about —
 * an account in two households reads each one's while looking at it —
 * and an announcement, so an open phone shows it at once.
 */
final class HouseholdDatabaseChannel extends DatabaseChannel
{
    /** @return mixed */
    public function send($notifiable, Notification $notification)
    {
        $stored = parent::send($notifiable, $notification);

        if ($notification instanceof HouseholdMessage) {
            AnnounceHouseholdUpdateJob::dispatch($notification->householdId, 'notifications')->delay(2);
        }

        return $stored;
    }

    /** @return array<string, mixed> */
    protected function buildPayload($notifiable, Notification $notification)
    {
        return [
            ...parent::buildPayload($notifiable, $notification),
            'household_id' => $notification instanceof HouseholdMessage ? $notification->householdId : null,
        ];
    }
}
