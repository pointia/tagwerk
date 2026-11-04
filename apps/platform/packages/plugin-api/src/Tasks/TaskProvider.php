<?php

declare(strict_types=1);

namespace Tagwerk\PluginApi\Tasks;

/**
 * What every task provider must be able to do: name itself.
 *
 * Everything else — importing, pushing out, reporting completions — is a
 * capability of its own. The built-in provider has only this contract:
 * Tagwerk's own tasks come from nowhere and go nowhere.
 */
interface TaskProvider
{
    /** Key in the database and in config/tagwerk.php. */
    public function key(): string;

    /** Name for the admin and the wall. */
    public function label(): string;
}
