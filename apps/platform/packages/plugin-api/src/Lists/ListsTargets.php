<?php

declare(strict_types=1);

namespace Tagwerk\PluginApi\Lists;

use Tagwerk\PluginApi\Connections\ConnectedAccount;

/**
 * Capability: the provider can tell which lists exist on the account.
 *
 * Separate from pushing: a service with a single list has nothing to
 * choose.
 */
interface ListsTargets
{
    /** @return array<int, RemoteList> */
    public function targets(ConnectedAccount $account): array;
}
