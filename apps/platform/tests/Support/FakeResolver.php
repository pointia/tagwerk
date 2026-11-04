<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\PublicAddressResolver;

/** Answers host names from a table instead of DNS. */
final class FakeResolver extends PublicAddressResolver
{
    /** @param  array<string, array<int, string>>  $hosts */
    public function __construct(private readonly array $hosts) {}

    public function resolve(string $host): array
    {
        return $this->hosts[$host] ?? parent::resolve($host);
    }
}
