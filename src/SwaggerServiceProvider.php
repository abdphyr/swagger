<?php

namespace Abdphyr\Swagger;

use Abdphyr\Swagger\Console\Commands\DebugAction;
use Abdphyr\Swagger\Console\Commands\MakeApiDoc;
use Illuminate\Support\ServiceProvider;

class SwaggerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        if ($this->app->runningInConsole()) {
            
            $this->commands([
                DebugAction::class,
                MakeApiDoc::class
            ]);

            $this->publishes([
                __DIR__ . '/../config/swagger.php' => config_path('swagger.php'),
            ], 'public');

            $this->publishes([
                __DIR__ . '/../resources/js/swagger.js' => resource_path('js/swagger.js'),
            ], 'public');
            
            $this->publishes([
                __DIR__ . '/../resources/views/swagger.blade.php' => resource_path('views/swagger.blade.php'),
            ], 'public');
        }
    }

    public function register() {}
}
