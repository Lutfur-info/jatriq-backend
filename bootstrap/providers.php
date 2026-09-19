<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\RepositoryServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    RepositoryServiceProvider::class,
];
