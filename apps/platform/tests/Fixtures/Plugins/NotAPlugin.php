<?php

declare(strict_types=1);

namespace Tests\Fixtures\Plugins;

use Illuminate\Support\ServiceProvider;

/** A provider written for Laravel's discovery, not for Tagwerk's loader. */
final class NotAPlugin extends ServiceProvider {}
