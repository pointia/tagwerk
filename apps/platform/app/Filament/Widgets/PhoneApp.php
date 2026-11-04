<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Support\HouseholdContext;
use App\Support\QrCode;
use Filament\Widgets\Widget;

/**
 * Points to the app, for the phone. The admin panel is no app of its own;
 * whoever looks for one here finds the right one to install.
 */
final class PhoneApp extends Widget
{
    protected static ?int $sort = 2;

    // Nothing to fetch: rendered with the page instead of a second request.
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.phone-app';

    public static function canView(): bool
    {
        return app(HouseholdContext::class)->hasId();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $url = url('/');

        return ['url' => $url, 'qr' => QrCode::svg($url)];
    }
}
