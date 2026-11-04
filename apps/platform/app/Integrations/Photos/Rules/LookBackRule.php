<?php

declare(strict_types=1);

namespace App\Integrations\Photos\Rules;

/**
 * Mostly the last year, now and then something older.
 *
 * Four in five pictures from the last twelve months, the rest from the four
 * years before. A frame that only shows the newest gets boring; one that
 * shows everything evenly mostly shows the time before the youngest child.
 */
final class LookBackRule extends WeightedRule
{
    public const KEY = 'look-back';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return __('Look back');
    }

    public function description(): string
    {
        return __('80 % from the last twelve months, 20 % from the four years before — each at random. Where pictures are missing, the others step in.');
    }

    protected function periods(): array
    {
        return [
            [0, 1, 80],
            [1, 5, 20],
        ];
    }
}
