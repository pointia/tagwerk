<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

final class HouseholdMovedIn extends HouseholdMessage
{
    /** @param list<string> $reconnect */
    public function __construct(int $householdId, public readonly string $household, public readonly array $reconnect)
    {
        parent::__construct($householdId);
    }

    public function title(): string
    {
        return __(':household has moved in', ['household' => $this->household]);
    }

    public function body(): string
    {
        $body = __('Everything arrived. Pair your wall tablets again; accounts of other family members got an invitation where their address was known here.');

        return $this->reconnect === []
            ? $body
            : $body.' '.__('Connect these again under Integrations → Connections: :list', ['list' => implode(', ', $this->reconnect)]);
    }

    public function link(): string
    {
        return '/admin';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->line($this->body())
            ->action(__('Open the admin'), url($this->link()));
    }
}
