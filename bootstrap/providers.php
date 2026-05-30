<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\AgentPanelProvider;
use App\Providers\Filament\LgaPanelProvider;
use App\Providers\Filament\StatePanelProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    StatePanelProvider::class,
    LgaPanelProvider::class,
    AgentPanelProvider::class,
    FortifyServiceProvider::class,
];
