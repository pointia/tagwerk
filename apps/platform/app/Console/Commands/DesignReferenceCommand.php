<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\DesignReference;
use Illuminate\Console\Command;
use Tagwerk\PluginApi\Themes\Tokens;

/**
 * Writes the design tokens themes may set into `tagwerk/plugin-api`. Run
 * it after adding or changing a token in `tagwerk.css`.
 */
final class DesignReferenceCommand extends Command
{
    protected $signature = 'tagwerk:design-reference';

    protected $description = 'Writes the design tokens themes may set into the plugin API package';

    public function handle(): int
    {
        file_put_contents(Tokens::path(), DesignReference::build());

        $this->info('Design reference written to '.Tokens::path().'.');

        return self::SUCCESS;
    }
}
