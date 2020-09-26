<?php

namespace Aws;

use Aws\Console\Commands\TokensSync;
use Illuminate\Support\ServiceProvider;

class AwsProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->publishes(
            [
            __DIR__.'/../publishes/config/aws.php' => config_path('aws.php'),
            ], 'config'
        );

        // View::composer(
        //     'kanban', 'App\Http\ViewComposers\KanbanComposer'
        // );
        // View::share('key', 'value');
        // Validator::extend('aws', function ($attribute, $value, $parameters, $validator) {
        // });
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations/');
        $this->publishes(
            [
            __DIR__.'/../database/migrations/' => database_path('migrations')
            ], 'migrations'
        );
        
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'aws');
        $this->publishes(
            [
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/aws'),
            ]
        );

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'aws');
        $this->publishes(
            [
            __DIR__.'/../resources/views' => resource_path('views/vendor/aws'),
            ]
        );


        if ($this->app->runningInConsole()) {
            $this->commands(
                [
                TokensSync::class,
                ]
            );
        }

        // Assets

        $this->publishes(
            [
            __DIR__.'/../publishes/assets' => public_path('vendor/aws'),
            ], 'public'
        );
    }

    /**
     * Register bindings in the container.
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../publishes/config/aws.php', 'aws'
        );
        $this->app->singleton(
            AdminLte::class, function (Container $app) {
                return new AdminLte(
                    $app['config']['adminlte.filters'],
                    $app['events'],
                    $app
                );
            }
        );
    }
    
    /**
     * Get the services provided by the provider.
     *
     * @return string[]
     *
     * @psalm-return array{0: string}
     */
    public function provides()
    {
        return ['aws'];
    }
}
