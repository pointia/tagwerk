<?php

declare(strict_types=1);

namespace Tagwerk\Mcp;

use App\Http\Middleware\ApiTokenScope;
use Laravel\Mcp\Facades\Mcp;
use Tagwerk\PluginApi\Plugins\Catalog;
use Tagwerk\PluginApi\Plugins\Plugin;
use Tagwerk\PluginApi\Plugins\PluginRoutes;

final class McpServiceProvider extends Plugin
{
    protected function registerPlugin(): void
    {
        $this->app->make(Catalog::class)->offer('areas', McpArea::KEY, McpArea::class);

        // In register: boot already loads the German texts (see example-area).
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');
    }

    public function bootPlugin(): void
    {
        $route = Mcp::web('/mcp', TagwerkServer::class)
            ->middleware([...PluginRoutes::MIDDLEWARE, 'feature:'.McpArea::KEY, RequireApiToken::class]);

        // Every MCP call is a POST; the tools tell reading from writing (ApiTool).
        $route->setAction([...$route->getAction(), ApiTokenScope::SELF => true]);
    }
}
