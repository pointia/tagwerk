<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Resolves a host name to its addresses.
 *
 * A class of its own so tests can answer without DNS.
 */
class PublicAddressResolver
{
    /**
     * IPv4 only for names: the request is pinned to one of these, so an IPv6
     * record never comes into play.
     *
     * @return array<int, string> empty when unknown
     */
    public function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        return gethostbynamel($host) ?: [];
    }
}
