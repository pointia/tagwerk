<?php

declare(strict_types=1);

namespace App\Services\Calendar;

final readonly class SyncResult
{
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public int $deleted = 0,
        public int $skipped = 0,
        public bool $fullResync = false,
    ) {}

    public function asFullResync(): self
    {
        return new self(
            $this->created,
            $this->updated,
            $this->deleted,
            $this->skipped,
            fullResync: true,
        );
    }

    public function touched(): int
    {
        return $this->created + $this->updated + $this->deleted;
    }
}
