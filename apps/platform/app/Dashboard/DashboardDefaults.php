<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Models\DashboardWidget;
use App\Models\Device;
use App\Models\Household;
use App\Models\User;

/**
 * The first layout of a dashboard.
 *
 * An empty grid saying "your tile could be here" would be the worst first
 * impression: nobody sets up a wall tablet to fill it afterwards. We place
 * what makes sense without further input — tiles of disabled areas stay out.
 */
final class DashboardDefaults
{
    /** @var array<int, array{type: string, x: int, y: int, w: int, h: int}> */
    private const LAYOUT = [
        ['type' => 'calendar', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 6],
        ['type' => 'weather', 'x' => 6, 'y' => 0, 'w' => 3, 'h' => 2],
        ['type' => 'clock', 'x' => 9, 'y' => 0, 'w' => 3, 'h' => 2],
        ['type' => 'tasks', 'x' => 6, 'y' => 2, 'w' => 3, 'h' => 4],
        ['type' => 'stars', 'x' => 9, 'y' => 2, 'w' => 3, 'h' => 4],
    ];

    public function __construct(private readonly WidgetRegistry $registry) {}

    /** @return array<int, DashboardWidget> */
    public function place(Household $household, Device|User $owner): array
    {
        $allowed = $this->registry->forHousehold($household);
        $placed = [];

        foreach (self::LAYOUT as $position => $tile) {
            if (! isset($allowed[$tile['type']])) {
                continue;
            }

            $placed[] = DashboardWidget::create([
                'household_id' => $household->id,
                ...self::owner($owner),
                'position' => $position,
                ...$tile,
            ]);
        }

        return $placed;
    }

    /** @return array{device_id: int|null, user_id: int|null} */
    public static function owner(Device|User $owner): array
    {
        return [
            'device_id' => $owner instanceof Device ? (int) $owner->getKey() : null,
            'user_id' => $owner instanceof User ? (int) $owner->getKey() : null,
        ];
    }
}
