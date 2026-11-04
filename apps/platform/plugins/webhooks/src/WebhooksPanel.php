<?php

declare(strict_types=1);

namespace Tagwerk\Webhooks;

use Filament\Panel;
use Tagwerk\PluginApi\Plugins\ConfiguresPanel;
use Tagwerk\Webhooks\Filament\WebhookResource;

final class WebhooksPanel implements ConfiguresPanel
{
    public function configurePanel(Panel $panel): void
    {
        $panel->resources([WebhookResource::class]);
    }
}
