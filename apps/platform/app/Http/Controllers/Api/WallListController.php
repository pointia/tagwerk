<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\ListItemChange;
use App\Http\Controllers\Controller;
use App\Jobs\MirrorListItemJob;
use App\Jobs\PushListItemJob;
use App\Models\Checklist;
use App\Models\ListItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Lists on the tablet.
 *
 * No PIN: per the access model anyone may write to lists, children included.
 * That is exactly why the device hangs in the hallway.
 */
final class WallListController extends Controller
{
    public function index(): JsonResponse
    {
        $lists = Checklist::query()
            ->with(['items' => fn ($query) => $query->orderByRaw('done_at IS NOT NULL')->orderBy('position')->orderBy('id')])
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(fn (Checklist $list): array => [
                'id' => $list->id,
                'name' => $list->name,
                'kind' => $list->kind->value,
                'color' => $list->color,
                'open_count' => $list->items->whereNull('done_at')->count(),
                'items' => $list->items->map(fn (ListItem $item): array => $this->item($item))->all(),
            ])
            ->all();

        return response()->json(['lists' => $lists]);
    }

    public function storeItem(Request $request, Checklist $checklist): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'quantity' => ['nullable', 'string', 'max:64'],
        ]);

        $item = $checklist->items()->create([
            'title' => $data['title'],
            'quantity' => $data['quantity'] ?? null,

            // New items go to the bottom, not the top: when shopping you read
            // top to bottom, and a jumping list loses you.
            'position' => (int) $checklist->items()->max('position') + 1,
        ]);

        $this->push($checklist, $item);

        return response()->json($this->item($item), 201);
    }

    public function updateItem(Request $request, ListItem $listItem): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'quantity' => ['nullable', 'string', 'max:64'],
            'done' => ['sometimes', 'boolean'],
        ]);

        $wasDone = $listItem->isDone();
        [$oldTitle, $oldQuantity] = [$listItem->title, $listItem->quantity];

        if (array_key_exists('done', $data)) {
            $listItem->done_at = $data['done'] ? Carbon::now() : null;
        }

        $listItem->fill(array_intersect_key($data, array_flip(['title', 'quantity'])))->save();

        $checklist = $listItem->checklist;

        /*
         * A provider matching by name would bring the old name back. Mirrored
         * only, not pushed: a one-way provider would end up with both names.
         */
        if ($listItem->wasChanged('title')) {
            $this->mirror($checklist, $oldTitle, $oldQuantity, ListItemChange::Removed);

            if (! $listItem->isDone()) {
                $this->mirror($checklist, $listItem->title, $listItem->quantity, ListItemChange::Open);
            }
        } elseif ($wasDone !== $listItem->isDone()) {
            $this->mirror(
                $checklist,
                $listItem->title,
                $listItem->quantity,
                $listItem->isDone() ? ListItemChange::Done : ListItemChange::Open,
            );
        }

        return response()->json($this->item($listItem));
    }

    public function destroyItem(ListItem $listItem): JsonResponse
    {
        $listItem->delete();

        // Otherwise the next pull would bring it straight back.
        $this->mirror($listItem->checklist, $listItem->title, $listItem->quantity, ListItemChange::Removed);

        return response()->json(status: 204);
    }

    /** Tidies a list up without emptying it. */
    public function clearDone(Checklist $checklist): JsonResponse
    {
        $checklist->items()->whereNotNull('done_at')->delete();

        return response()->json(status: 204);
    }

    private function push(Checklist $checklist, ListItem $item): void
    {
        if ($checklist->list_provider !== null) {
            PushListItemJob::dispatch(
                $checklist->list_provider,
                $checklist->list_account_id,
                $item->title,
                $item->quantity,
                $checklist->remoteTarget(),
            );
        }
    }

    /** Only two-way providers act on it; the job asks, not the controller. */
    private function mirror(?Checklist $checklist, string $name, ?string $spec, ListItemChange $change): void
    {
        if ($checklist?->list_provider === null || $checklist->list_account_id === null) {
            return;
        }

        MirrorListItemJob::dispatch(
            $checklist->list_provider,
            $checklist->list_account_id,
            $checklist->remoteTarget(),
            $name,
            $spec,
            $change,
        );
    }

    /** @return array<string, mixed> */
    private function item(ListItem $item): array
    {
        return [
            'id' => $item->id,
            'list_id' => $item->list_id,
            'title' => $item->title,
            'quantity' => $item->quantity,
            'done' => $item->isDone(),
            'position' => $item->position,
        ];
    }
}
