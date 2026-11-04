<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Every text in the interface has a German version, and none is left German
 * in the code.
 *
 * English is the key and needs no file; a missing German entry would not fail
 * anywhere else, it would just show English in a German household.
 */
final class TranslationsTest extends TestCase
{
    /** Files whose German literals are data, not interface text. */
    private const GERMAN_DATA = [
        // Unit spellings of German recipes — data the parser reads, not text anyone is shown.
        'app/Support/Recipes/Ingredient.php',
    ];

    public function test_every_key_has_a_german_entry(): void
    {
        $german = json_decode((string) file_get_contents($this->root().'/lang/de.json'), true, flags: JSON_THROW_ON_ERROR);
        $missing = [];

        foreach ($this->files() as $file) {
            foreach ($this->keys((string) file_get_contents($file)) as $key) {
                if (! array_key_exists($key, $german)) {
                    $missing[] = $this->relative($file).': '.$key;
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)));
    }

    public function test_no_german_is_left_in_the_code(): void
    {
        $found = [];

        foreach ($this->files() as $file) {
            $relative = $this->relative($file);

            if (in_array($relative, self::GERMAN_DATA, true) || str_ends_with($file, '.blade.php')) {
                continue;
            }

            $tokens = array_values(array_filter(
                token_get_all((string) file_get_contents($file)),
                fn (array|string $token): bool => ! is_array($token) || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
            ));

            foreach ($tokens as $index => $token) {
                // The English key itself may carry a proper name like "Baden-Württemberg".
                $isKey = ($tokens[$index - 1] ?? null) === '(' && in_array($tokens[$index - 2][1] ?? null, ['__', 'trans', 'trans_choice'], true);

                if (is_array($token) && ! $isKey
                    && in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML], true)
                    && preg_match('/[äöüÄÖÜß„]/u', $token[1]) === 1) {
                    $found[] = $relative.':'.$token[2].': '.$token[1];
                }
            }
        }

        $this->assertSame([], $found);
    }

    public function test_blade_views_carry_no_german(): void
    {
        $found = [];

        foreach ($this->files() as $file) {
            if (! str_ends_with($file, '.blade.php')) {
                continue;
            }

            // Only what is outside the translation calls.
            $stripped = (string) preg_replace('/__\(\s*([\'"]).*?(?<!\\\\)\1/su', '', (string) file_get_contents($file));

            if (preg_match('/[äöüÄÖÜß„]/u', $stripped) === 1) {
                $found[] = $this->relative($file);
            }
        }

        $this->assertSame([], $found);
    }

    /** @return list<string> */
    private function keys(string $source): array
    {
        preg_match_all('/(?:__|trans|trans_choice|@lang)\(\s*(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\$]|\\\\.)*")/s', $source, $matches);

        $keys = [];

        foreach ($matches[1] as $literal) {
            $key = stripcslashes(substr($literal, 1, -1));

            // File keys (validation.in, filament-panels::…) live elsewhere.
            if (str_contains($key, '::') || preg_match('/^[a-z_]+\.[a-z_.]+$/', $key) === 1) {
                continue;
            }

            $keys[] = $key;
        }

        return $keys;
    }

    /** @return list<string> */
    private function files(): array
    {
        $files = [];

        foreach (['app', 'resources/views', 'plugins/bring/src', 'plugins/two-factor/src', 'plugins/kochwiki/src', 'routes'] as $dir) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root().'/'.$dir, RecursiveDirectoryIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if (str_ends_with((string) $file, '.php')) {
                    $files[] = (string) $file;
                }
            }
        }

        sort($files);

        return $files;
    }

    private function relative(string $file): string
    {
        return substr($file, strlen($this->root()) + 1);
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
