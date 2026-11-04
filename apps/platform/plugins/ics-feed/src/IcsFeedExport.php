<?php

declare(strict_types=1);

namespace Tagwerk\IcsFeed;

use Tagwerk\PluginApi\Plugins\ExportRules;

/** A local copy must not carry a working subscription address. */
final class IcsFeedExport implements ExportRules
{
    public function systemTables(): array
    {
        return [];
    }

    public function scrubbed(): array
    {
        return ['ics_feeds' => ['token_hash']];
    }
}
