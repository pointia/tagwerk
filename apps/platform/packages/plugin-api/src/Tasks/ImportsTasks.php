<?php

declare(strict_types=1);

namespace Tagwerk\PluginApi\Tasks;

use Tagwerk\PluginApi\Connections\ConnectedAccount;

/**
 * Capability: the provider can hand out its tasks.
 *
 * That brings Dad's tasks onto the wall, where the household can add what
 * only Tagwerk knows.
 */
interface ImportsTasks
{
    /** @return array<int, RemoteTask> */
    public function tasks(ConnectedAccount $account, ?string $list = null): array;
}
