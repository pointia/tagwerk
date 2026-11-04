<?php

declare(strict_types=1);

namespace Tagwerk\ExampleLanguage;

use Tagwerk\PluginApi\Languages\LanguagePack;

/**
 * French for the screens a new tablet or phone shows first. Everything
 * else falls back to English — Operations → Plugins lists what.
 */
final class French implements LanguagePack
{
    public function code(): string
    {
        return 'fr';
    }

    public function name(): string
    {
        return 'Français';
    }

    public function intlTag(): string
    {
        return 'fr-FR';
    }

    public function backendPath(): string
    {
        return dirname(__DIR__).'/lang';
    }

    public function frontendMessages(): string
    {
        return dirname(__DIR__).'/frontend/fr.json';
    }
}
