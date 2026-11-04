<?php

declare(strict_types=1);

namespace Tagwerk\ExampleTheme;

use Tagwerk\PluginApi\Themes\Theme;

final class Fjord implements Theme
{
    public function name(): string
    {
        return 'Fjord';
    }

    public function stylesheet(): string
    {
        return dirname(__DIR__).'/resources/fjord.css';
    }
}
