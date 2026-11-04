<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\PluginServiceProvider;

return [
    PluginServiceProvider::class,
    AppServiceProvider::class,
    AdminPanelProvider::class,
];
