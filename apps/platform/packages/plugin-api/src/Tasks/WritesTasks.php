<?php

declare(strict_types=1);

namespace Tagwerk\PluginApi\Tasks;

use Tagwerk\PluginApi\Connections\ConnectedAccount;

/**
 * Capability: the provider accepts completions.
 *
 * Only the ticking goes out, not the task itself: whoever creates a task in
 * Tagwerk wants it on the wall — and a copy at the external service would be
 * a second truth about what is still open.
 */
interface WritesTasks
{
    /** `$externalId` is the task's id at the service, as `RemoteTask` delivered it. */
    public function markDone(ConnectedAccount $account, string $externalId, bool $done): void;
}
