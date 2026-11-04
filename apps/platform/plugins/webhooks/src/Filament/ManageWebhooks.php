<?php

declare(strict_types=1);

namespace Tagwerk\Webhooks\Filament;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Tagwerk\Webhooks\Webhook;

class ManageWebhooks extends ManageRecords
{
    protected static string $resource = WebhookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->after(fn (Webhook $record) => WebhookResource::announce($record->renewSecret())),
        ];
    }
}
