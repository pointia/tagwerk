<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\User;
use App\Support\Abilities;
use App\Support\Preferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The preferences of whoever asks.
 *
 * As with the theme, one route for wall and phone: the setting lives on the
 * authenticated model — on the device for the wall, on the user for the
 * phone. A tablet in the kitchen may be set up differently from one in the
 * hallway.
 */
final class PreferenceController extends Controller
{
    public function __construct(private readonly Abilities $abilities) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->payload($this->owner($request)));
    }

    public function update(Request $request): JsonResponse
    {
        $owner = $this->owner($request);
        $data = $request->validate(Preferences::rules($owner instanceof User));

        // Otherwise a child re-sorts or hides the wall's menu in passing.
        if ($owner instanceof Device && array_intersect(array_keys($data), Preferences::WALL_MENU) !== []) {
            $this->abilities->authorize($owner, Permission::DashboardArrange);
        }

        // Merged instead of replaced: the wall only sends what changed —
        // otherwise one toggle would reset everything else.
        if ($owner instanceof Device) {
            $owner->update(['preferences' => [...Preferences::merged($owner->preferences), ...$data]]);

            return response()->json($this->payload($owner));
        }

        $own = array_flip(Preferences::PER_HOUSEHOLD);
        $owner->update(['preferences' => [...($owner->preferences ?? []), ...array_diff_key($data, $own)]]);

        $membership = $owner->membership();
        $membership?->update(['preferences' => [...($membership->preferences ?? []), ...array_intersect_key($data, $own)]]);

        return response()->json($this->payload($owner));
    }

    /** @return array<string, mixed> */
    private function payload(Device|User $owner): array
    {
        return [
            'preferences' => Preferences::merged($this->stored($owner), $owner instanceof User),
            'options' => Preferences::allowed(),
        ];
    }

    /**
     * An account's own preferences, with those of the household it looks at.
     *
     * @return array<string, mixed>|null
     */
    private function stored(Device|User $owner): ?array
    {
        if ($owner instanceof Device) {
            return $owner->preferences;
        }

        $own = array_flip(Preferences::PER_HOUSEHOLD);

        return [
            ...array_diff_key($owner->preferences ?? [], $own),
            ...array_intersect_key($owner->membership()?->preferences ?? [], $own),
        ];
    }

    private function owner(Request $request): Device|User
    {
        $owner = $request->user();

        abort_unless($owner instanceof Device || $owner instanceof User, 401);

        return $owner;
    }
}
