<?php

namespace Xepare\PterodactylApiAddon;

use Illuminate\Support\ServiceProvider;
use Spatie\LaravelPackageTools\Package;

class PterodactylApiAddonServiceProvider extends ServiceProvider
{

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }

}
