<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\OutboundHttp;
use App\Support\PublicAddressResolver;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeResolver;
use Tests\TestCase;

/**
 * Addresses a household types in never lead into the server's own network.
 */
final class OutboundHttpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(PublicAddressResolver::class, new FakeResolver([
            'public.example.test' => ['93.184.216.34'],
            'internal.example.test' => ['10.0.0.5'],
            'mixed.example.test' => ['93.184.216.34', '192.168.1.10'],
            'tailnet.example.test' => ['100.101.102.103'],
        ]));
    }

    /** @return array<string, array{string}> */
    public static function forbidden(): array
    {
        return [
            'loopback' => ['http://127.0.0.1/x.ics'],
            'docker service name' => ['http://db:5432/'],
            'cloud metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'private network' => ['https://internal.example.test/x.ics'],
            'one private address among several' => ['https://mixed.example.test/x.ics'],
            'tailnet' => ['https://tailnet.example.test/x.ics'],
            'ipv6 loopback' => ['http://[::1]/x.ics'],
            'file' => ['file:///etc/passwd'],
            'no scheme' => ['public.example.test/x.ics'],
        ];
    }

    #[DataProvider('forbidden')]
    public function test_internal_targets_are_refused(string $url): void
    {
        Http::fake();

        $this->assertFalse(app(OutboundHttp::class)->isPublic($url));
        $this->assertNull(app(OutboundHttp::class)->get($url));
        $this->assertNull(app(OutboundHttp::class)->send('POST', $url, ['body' => '{}']));
        Http::assertNothingSent();
    }

    public function test_any_method_goes_out_without_following_a_redirect(): void
    {
        Http::fake([
            'public.example.test/hook' => Http::response('', 307, ['Location' => 'http://127.0.0.1/']),
        ]);

        $response = app(OutboundHttp::class)->send('POST', 'https://public.example.test/hook', ['body' => '{"a":1}']);

        $this->assertSame(307, $response?->status());
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request->method() === 'POST' && $request->body() === '{"a":1}');
    }

    public function test_a_public_address_is_fetched(): void
    {
        Http::fake(['public.example.test/*' => Http::response('BEGIN:VCALENDAR')]);

        $this->assertSame('BEGIN:VCALENDAR', app(OutboundHttp::class)->get('https://public.example.test/x.ics')?->body());
    }

    public function test_a_redirect_into_the_private_network_is_not_followed(): void
    {
        Http::fake([
            'public.example.test/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
            '169.254.169.254/*' => Http::response('secret'),
        ]);

        $this->assertNull(app(OutboundHttp::class)->get('https://public.example.test/x.ics'));
        Http::assertSentCount(1);
    }

    public function test_a_public_redirect_is_followed(): void
    {
        Http::fake([
            'public.example.test/old.ics' => Http::response('', 301, ['Location' => '/new.ics']),
            'public.example.test/new.ics' => Http::response('BEGIN:VCALENDAR'),
        ]);

        $this->assertSame('BEGIN:VCALENDAR', app(OutboundHttp::class)->get('https://public.example.test/old.ics')?->body());
    }
}
