<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Tagwerk\PluginApi\Households\CurrentHousehold;

Route::get('/greeting', fn (CurrentHousehold $household): array => [
    'household' => $household->name(),
]);
