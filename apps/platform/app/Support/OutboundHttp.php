<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\IpUtils;
use Throwable;

/**
 * GET for addresses a household typed in — only ever into the public internet.
 *
 * Without this, a parent could make the server fetch `http://db:5432`, the
 * cloud metadata service or anything else in its private network. Every hop
 * of a redirect is checked, and the request goes to the very address that was
 * checked: a DNS answer that changes between check and fetch gains nothing.
 */
final class OutboundHttp
{
    private const MAX_REDIRECTS = 3;

    public function __construct(private readonly PublicAddressResolver $resolver) {}

    /** @param  array<string, string>  $headers */
    public function get(string $url, int $timeout = 10, array $headers = []): ?Response
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $pin = $this->pin($url);

            if ($pin === null) {
                return null;
            }

            try {
                $response = Http::timeout($timeout)
                    ->withHeaders($headers)
                    ->withoutRedirecting()
                    ->withOptions(['curl' => [CURLOPT_RESOLVE => [$pin]]])
                    ->get($url);
            } catch (Throwable) {
                return null;
            }

            if (! $response->redirect()) {
                return $response;
            }

            $location = (string) $response->header('Location');

            if ($location === '') {
                return null;
            }

            $url = str_starts_with($location, '/') ? $this->origin($url).$location : $location;
        }

        return null;
    }

    /**
     * Any method — a webhook's POST, a CalDAV REPORT — with the same check,
     * but without following redirects: a write that is sent somewhere else
     * would leave the checked address behind. Null when the address is
     * refused or unreachable; a response with an error status comes back.
     *
     * @param  array<string, mixed>  $options  Guzzle request options (headers, body, auth)
     */
    public function send(string $method, string $url, array $options = [], int $timeout = 10): ?Response
    {
        $pin = $this->pin($url);

        if ($pin === null) {
            return null;
        }

        try {
            return Http::timeout($timeout)
                ->withoutRedirecting()
                ->withOptions(['curl' => [CURLOPT_RESOLVE => [$pin]]])
                ->send($method, $url, $options);
        } catch (Throwable) {
            return null;
        }
    }

    /** Only http(s) with a host whose every address is public. */
    public function isPublic(string $url): bool
    {
        return $this->pin($url) !== null;
    }

    /** `host:port:address` for curl — null when the URL must not be fetched. */
    private function pin(string $url): ?string
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || $host === '') {
            return null;
        }

        $host = trim($host, '[]');
        $addresses = $this->resolver->resolve($host);

        if ($addresses === []) {
            return null;
        }

        // Loopback, RFC 1918, link-local (cloud metadata), CGNAT and the
        // IPv6 equivalents. All addresses, not just the pinned one.
        foreach ($addresses as $address) {
            if (IpUtils::isPrivateIp($address)) {
                return null;
            }
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $address = str_contains($addresses[0], ':') ? "[{$addresses[0]}]" : $addresses[0];

        return "{$host}:{$port}:{$address}";
    }

    private function origin(string $url): string
    {
        $parts = parse_url($url);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').$port;
    }
}
