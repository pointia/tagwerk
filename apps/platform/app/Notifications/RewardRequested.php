<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\Feature;

/**
 * A child asked for a reward at the wall. The only message that has to
 * reach someone on its own: otherwise the request waits at the tablet
 * until a parent happens to walk by.
 */
final class RewardRequested extends HouseholdMessage
{
    public function __construct(int $householdId, public readonly string $person, public readonly string $reward, public readonly int $cost)
    {
        parent::__construct($householdId);
    }

    public function title(): string
    {
        return __('Reward requested');
    }

    public function body(): string
    {
        return trans_choice(':name would like to redeem “:reward” (:count star).|:name would like to redeem “:reward” (:count stars).', $this->cost, [
            'name' => $this->person,
            'reward' => $this->reward,
        ]);
    }

    /** Straight to the stars, where the request waits for approval. */
    public function link(): string
    {
        return '/?area='.Feature::Points->wallMode();
    }

    public function push(): string
    {
        return 'reward';
    }
}
