<?php

declare(strict_types=1);

namespace Tagwerk\PluginApi\Tasks;

use Tagwerk\PluginApi\Connections\ConnectedAccount;
use Tagwerk\PluginApi\Lists\RemoteList;

/**
 * Capability: the provider keeps several task lists.
 *
 * Separate from importing, because not every service has them — and one
 * with a single list should not offer a choice that is none.
 *
 * Uses RemoteList from the lists area: a second class with the same two
 * fields would be a distinction without a difference.
 */
interface ListsTaskLists
{
    /** @return array<int, RemoteList> */
    public function taskLists(ConnectedAccount $account): array;
}
