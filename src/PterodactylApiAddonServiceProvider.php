<?php

namespace Xepare\PterodactylApiAddon;

use Illuminate\Support\ServiceProvider;

class PterodactylApiAddonServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
    }
}
