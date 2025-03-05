<?php

namespace Abdphyr\Swagger;

use Abdphyr\Swagger\Console\Commands\DebugAction;
use Abdphyr\Swagger\Console\Commands\MakeApiDoc;
use Illuminate\Support\ServiceProvider;

class SwaggerServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        if ($this->app->runningInConsole() && (app()->environment() != 'production')) {

            $this->commands([
                DebugAction::class,
                MakeApiDoc::class
            ]);

            $this->publishes([
                __DIR__ . '/../config/swagger.php' => config_path('swagger.php'),
            ], 'public');
        }
    }

    public function register() {}
}
