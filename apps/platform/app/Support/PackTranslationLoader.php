<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Translation\Loader;

/**
 * Laravel's loader plus the language packs.
 *
 * A pack's files count for its own code only: a directory added as a plain
 * path would let a pack bring a `de.json` and rewrite the German. What
 * Laravel already finds for the code (a plugin area's own translations)
 * wins over the pack — the area knows its texts best.
 */
final class PackTranslationLoader implements Loader
{
    public function __construct(
        private readonly Loader $loader,
        private readonly Container $container,
    ) {}

    /** @return array<string, mixed> */
    public function load($locale, $group, $namespace = null): array
    {
        $loaded = $this->loader->load($locale, $group, $namespace);

        if ($namespace !== null && $namespace !== '*') {
            return $loaded;
        }

        $pack = $this->container->make(Languages::class)->pack($locale);

        if ($pack === null) {
            return $loaded;
        }

        return array_replace_recursive($this->container->make(PackTexts::class)->backend($pack, $group), $loaded);
    }

    public function addNamespace($namespace, $hint): void
    {
        $this->loader->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path): void
    {
        $this->loader->addJsonPath($path);
    }

    /** @return array<string, string> */
    public function namespaces(): array
    {
        return $this->loader->namespaces();
    }

    /** Anything else Laravel's file loader offers (`addPath`). */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->loader->{$method}(...$arguments);
    }
}
