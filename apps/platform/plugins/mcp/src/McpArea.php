<?php

declare(strict_types=1);

namespace Tagwerk\Mcp;

use App\Areas\ApiArea;
use Tagwerk\PluginApi\Areas\Area;
use Tagwerk\PluginApi\Areas\OptIn;
use Tagwerk\PluginApi\Areas\RequiresArea;

final class McpArea implements Area, OptIn, RequiresArea
{
    public const KEY = 'tagwerk/mcp';

    public function label(): string
    {
        return __('AI assistants (MCP)');
    }

    public function shortLabel(): string
    {
        return __('MCP');
    }

    public function description(): string
    {
        return __('Lets an AI assistant read and change calendar, tasks, lists and meal plan with an API token.');
    }

    public function icon(): string
    {
        return 'link';
    }

    public function inMenu(): bool
    {
        return false;
    }

    /** An assistant signs in with an API token. */
    public function requires(): string
    {
        return ApiArea::KEY;
    }
}
