<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Migrations\Migrator;

/**
 * Released migrations are sealed: their hash is recorded in
 * `database/migrations.sha256`, and a sealed one must neither change nor
 * disappear.
 *
 * Migrations run in the new container while the previous release still
 * serves, and a changed one would never run again on a server that has run
 * it already. Fixes therefore go into a new migration. Plugins' migrations
 * count as well — they share the table — and so does the schema dump that
 * replaced the history (`database/schema`).
 */
final class MigrationSeal
{
    public const FILE = 'database/migrations.sha256';

    public function __construct(private readonly Migrator $migrator) {}

    /** @return array<string, string> relative path → sha256 of what is there now */
    public function current(): array
    {
        $paths = [database_path('migrations'), ...$this->migrator->paths()];
        $files = [];

        foreach (array_unique(array_map('realpath', $paths)) as $path) {
            foreach (glob($path.'/*.php') ?: [] as $file) {
                $files[$this->relative($file)] = hash_file('sha256', $file);
            }
        }

        // The squashed history: where a fresh database starts.
        foreach (glob(database_path('schema').'/*.sql') ?: [] as $file) {
            $files[$this->relative($file)] = hash_file('sha256', $file);
        }

        ksort($files);

        return $files;
    }

    /** @return array<string, string> */
    public function sealed(): array
    {
        $file = base_path(self::FILE);
        $sealed = [];

        foreach (is_file($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] : [] as $line) {
            [$hash, $path] = explode('  ', $line, 2);
            $sealed[$path] = $hash;
        }

        return $sealed;
    }

    /** Seals what is not sealed yet; returns how many were added. */
    public function sealNew(): int
    {
        $sealed = $this->sealed();
        $new = array_diff_key($this->current(), $sealed);
        $all = [...$sealed, ...$new];
        ksort($all);

        file_put_contents(base_path(self::FILE), implode('', array_map(
            static fn (string $path, string $hash): string => "{$hash}  {$path}\n",
            array_keys($all),
            $all,
        )));

        return count($new);
    }

    private function relative(string $file): string
    {
        $base = realpath(base_path()).'/';
        $real = (string) realpath($file);

        return str_starts_with($real, $base) ? substr($real, strlen($base)) : $real;
    }
}
