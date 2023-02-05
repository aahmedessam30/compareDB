<?php

namespace Essam\CompareDB\Providers;

use Essam\CompareDB\Console\Commands\Compare;
use Illuminate\Support\ServiceProvider;
use Essam\CompareDB\Services\CompareDB;

class CompareDBServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        config(['database.connections.compareDB' => config('compareDB')]);

        $this->app->bind('compareDB', function () {
            return new CompareDB();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                Compare::class,
            ]);
        }
    }
}
