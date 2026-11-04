<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Household;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Deleting one's own account. Where it is the last manager, the household
 * goes with it — nobody would be left to run it; elsewhere only the
 * membership ends. Each such household is confirmed by its typed name.
 */
final class AccountDeletion
{
    public function __construct(
        private readonly Memberships $memberships,
        private readonly HouseholdDeletion $households,
    ) {}

    /**
     * The households deleting this account deletes too.
     *
     * @return list<Household>
     */
    public function householdsLost(User $user): array
    {
        return $user->memberships()
            ->with('household')
            ->get()
            ->filter(fn (Membership $membership): bool => $membership->household !== null && $this->memberships->isLastManager($membership))
            ->map(fn (Membership $membership): Household => $membership->household)
            ->values()
            ->all();
    }

    /** @param list<string> $confirmed the household names typed */
    public function delete(User $user, string $password, array $confirmed): void
    {
        if ($user->is_operator === true) {
            throw ValidationException::withMessages(['password' => __('An operator account cannot delete itself.')]);
        }

        if (! Hash::check($password, (string) $user->password)) {
            throw ValidationException::withMessages(['password' => __('The password is wrong.')]);
        }

        $lost = $this->householdsLost($user);

        foreach ($lost as $household) {
            if (! in_array($household->name, $confirmed, true)) {
                throw ValidationException::withMessages(['confirm' => __('Type the name of :name to confirm.', ['name' => $household->name])]);
            }
        }

        foreach ($lost as $household) {
            $this->households->delete($household);
        }

        foreach ($user->memberships()->get() as $membership) {
            $this->memberships->remove($membership);
        }

        User::query()->whereKey($user->id)->first()?->delete();
    }
}
