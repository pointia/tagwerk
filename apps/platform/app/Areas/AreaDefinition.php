<?php

declare(strict_types=1);

namespace App\Areas;

/** One area as everything outside the registry sees it, built-in or plugin. */
final readonly class AreaDefinition
{
    public function __construct(
        public string $key,
        public string $label,
        public string $shortLabel,
        public string $description,
        public string $icon,
        public bool $inMenu,
        public ?string $requires = null,
        public bool $optIn = false,
    ) {}
}
