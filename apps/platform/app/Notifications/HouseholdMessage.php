<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Channels\HouseholdDatabaseChannel;
use App\Notifications\Channels\PushChannel;
use App\Support\OutgoingMail;
use Filament\Actions\Action;
use Filament\Notifications\Notification as PanelNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Something that happened in a household and reaches an account — an
 * event, stored, unlike the computed notices (`HouseholdNotices`).
 *
 * Every message lands in the account's list (the admin's bell, the app's
 * bell), in Filament's format so the panel draws it unchanged. A subclass
 * says whether it also pushes to the account's phones (`push()`) and
 * whether it mails (`toMail()`, sent only while the server mails).
 */
abstract class HouseholdMessage extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $householdId) {}

    abstract public function title(): string;

    public function body(): ?string
    {
        return null;
    }

    /** Where a tap leads: `https://` or a path on this server. */
    public function link(): ?string
    {
        return null;
    }

    /** Whether the account's phones hear about it, and under which tag (one per kind replaces the last). */
    public function push(): ?string
    {
        return null;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $via = [HouseholdDatabaseChannel::class];

        if ($this->push() !== null) {
            $via[] = PushChannel::class;
        }

        if (method_exists($this, 'toMail') && OutgoingMail::enabled()) {
            $via[] = 'mail';
        }

        return $via;
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $message = PanelNotification::make()
            ->title($this->title())
            ->body($this->body())
            ->icon('heroicon-o-bell');

        if ($this->link() !== null) {
            $message->actions([Action::make('open')->label(__('Open'))->url($this->link())->markAsRead()]);
        }

        return $message->getDatabaseMessage();
    }

    /** @return array{title: string, body: ?string, url: string, tag: string}|null */
    public function toPush(object $notifiable): ?array
    {
        $tag = $this->push();

        return $tag === null ? null : [
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->link() ?? '/',
            'tag' => $tag,
        ];
    }
}
