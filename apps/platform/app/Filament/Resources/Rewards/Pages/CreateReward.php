<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rewards\Pages;

use App\Filament\Resources\Rewards\RewardResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReward extends CreateRecord
{
    protected static string $resource = RewardResource::class;
}
