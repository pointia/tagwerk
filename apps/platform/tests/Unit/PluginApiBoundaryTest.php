<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The plugin API is MIT and the core is not: one reference to `App\` would
 * make every plugin built on the API depend on the core's licence.
 */
final class PluginApiBoundaryTest extends TestCase
{
    public function test_the_plugin_api_never_references_the_core(): void
    {
        $this->assertNoCoreReference(dirname(__DIR__, 2).'/packages/plugin-api');
    }

    /** The example is MIT too and the template third parties copy. */
    public function test_the_example_plugin_uses_only_the_api(): void
    {
        $this->assertNoCoreReference(dirname(__DIR__, 2).'/plugins/example-area');
    }

    /** Every package carries the text of the licence its composer.json names. */
    public function test_every_plugin_ships_its_licence(): void
    {
        $root = dirname(__DIR__, 2);
        $shield = (string) file_get_contents(dirname($root, 2).'/LICENSE');

        foreach ([...glob($root.'/plugins/*', GLOB_ONLYDIR), $root.'/packages/plugin-api'] as $package) {
            $license = json_decode((string) file_get_contents($package.'/composer.json'), true)['license'];
            $this->assertFileExists($package.'/LICENSE');
            $text = (string) file_get_contents($package.'/LICENSE');

            match ($license) {
                'MIT' => $this->assertStringStartsWith('MIT License', $text, $package),
                'PolyForm-Shield-1.0.0' => $this->assertSame($shield, $text, $package),
                default => $this->fail("{$package} names {$license}."),
            };
        }
    }

    private function assertNoCoreReference(string $directory): void
    {
        $this->assertDirectoryExists($directory);

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (str_contains($file->getPathname(), '/vendor/') || str_contains($file->getPathname(), '/node_modules/')) {
                continue;
            }

            $this->assertSame(0, preg_match('/\bApp\\\\/', (string) file_get_contents($file->getPathname())), "{$file->getPathname()} references App\\.");
        }
    }
}
