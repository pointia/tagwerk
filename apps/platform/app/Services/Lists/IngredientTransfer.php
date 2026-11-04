<?php

declare(strict_types=1);

namespace App\Services\Lists;

use App\Enums\ListKind;
use App\Jobs\PushListItemJob;
use App\Models\Checklist;
use Illuminate\Validation\ValidationException;

/**
 * Ingredients onto the shopping list.
 *
 * Used from two places — the meal plan and the recipe book — and both times
 * with the same rule: what is already open on the list does not go on a
 * second time.
 */
final class IngredientTransfer
{
    /**
     * @param  array<int, array{name: string, spec: string|null}>  $ingredients
     * @return array{list_id: int, added: int}
     */
    public function add(array $ingredients): array
    {
        if ($ingredients === []) {
            throw ValidationException::withMessages([
                'recipe' => __('The ingredients could not be loaded.'),
            ]);
        }

        $list = Checklist::query()
            ->where('kind', ListKind::Shopping->value)
            ->orderBy('position')
            ->first();

        if ($list === null) {
            throw ValidationException::withMessages(['recipe' => __('There is no shopping list.')]);
        }

        $existing = $list->items()
            ->whereNull('done_at')
            ->pluck('title')
            ->map(static fn (string $title): string => mb_strtolower($title))
            ->all();

        $position = (int) $list->items()->max('position');
        $added = 0;

        foreach ($ingredients as $ingredient) {
            if (in_array(mb_strtolower($ingredient['name']), $existing, true)) {
                continue;
            }

            $list->items()->create([
                'title' => $ingredient['name'],
                'quantity' => $ingredient['spec'],
                'position' => ++$position,
            ]);

            if ($list->list_provider !== null) {
                PushListItemJob::dispatch(
                    $list->list_provider,
                    $list->list_account_id,
                    $ingredient['name'],
                    $ingredient['spec'],
                    $list->remoteTarget(),
                );
            }

            $existing[] = mb_strtolower($ingredient['name']);
            $added++;
        }

        return ['list_id' => (int) $list->id, 'added' => $added];
    }
}
