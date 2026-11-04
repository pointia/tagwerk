<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Household;
use App\Models\User;
use App\Services\SupportAccessService;
use Filament\Facades\Filament;
use Illuminate\Container\Attributes\Scoped;

/**
 * Whether the admin shows the current household read-only. The operator
 * in support access is exempt: support is often why it is read-only.
 *
 * Asked by every navigation entry, so the answer is kept per request and
 * household.
 */
#[Scoped]
final class ReadOnlyAdmin
{
    /** @var array<int, bool> */
    private array $readOnly = [];

    public function __construct(
        private readonly HouseholdContext $context,
        private readonly SupportAccessService $support,
    ) {}

    public function applies(): bool
    {
        if (! $this->context->hasId()) {
            return false;
        }

        $user = Filament::auth()->user();

        if ($user instanceof User && $this->support->current($user) !== null) {
            return false;
        }

        $id = (int) $this->context->id();

        return $this->readOnly[$id] ??= Household::query()->whereKey($id)->whereNotNull('read_only_since')->exists();
    }
}
