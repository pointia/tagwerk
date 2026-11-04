<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Services\HouseholdNotices;
use App\Support\HouseholdContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The account's messages (events, stored) and closing a notice (a state,
 * computed). The same list as the admin's bell.
 */
final class NotificationController extends Controller
{
    public const SHOWN = 30;

    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()
                ->limit(self::SHOWN)
                ->get()
                ->map(fn (Notification $notification): array => [
                    'id' => $notification->id,
                    'title' => (string) ($notification->data['title'] ?? ''),
                    'body' => $notification->data['body'] ?? null,
                    'url' => $notification->data['actions'][0]['url'] ?? null,
                    'read' => $notification->read_at !== null,
                    'created_at' => $notification->created_at?->toIso8601String(),
                ])
                ->all(),
        ]);
    }

    /** Marks the given ones read, or all without `ids`. */
    public function read(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['sometimes', 'array', 'max:100'],
            'ids.*' => ['uuid'],
        ]);

        $this->user($request)
            ->unreadNotifications()
            ->when(isset($data['ids']), fn ($query) => $query->whereIn('id', $data['ids']))
            ->update(['read_at' => now()]);

        return response()->json(status: 204);
    }

    public function dismiss(Request $request, HouseholdNotices $notices): JsonResponse
    {
        $data = $request->validate(['key' => ['required', 'string', 'max:100']]);

        abort_unless($notices->dismiss($this->user($request), (int) app(HouseholdContext::class)->id(), $data['key']), 404);

        return response()->json(status: 204);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
