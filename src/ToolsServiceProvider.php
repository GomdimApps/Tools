<?php

namespace GomdimApps\Tools;

use Illuminate\Support\ServiceProvider;

class ToolsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/tools.php', 'tools');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/tools.php' => config_path('tools.php'),
            ], 'tools-config');
        }
    }
}
