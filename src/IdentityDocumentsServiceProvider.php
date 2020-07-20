<?php

namespace werk365\IdentityDocuments;

use Illuminate\Support\ServiceProvider;

class IdentityDocumentsServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'werk365');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'werk365');
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/identitydocuments.php', 'identitydocuments');

        // Register the service the package provides.
        $this->app->singleton('identitydocuments', function ($app) {
            return new IdentityDocuments;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['identitydocuments'];
    }

    /**
     * Console-specific booting.
     *
     * @return void
     */
    protected function bootForConsole()
    {
        // Publishing the configuration file.
        $this->publishes([
            __DIR__.'/../config/identitydocuments.php' => config_path('identitydocuments.php'),
        ], 'identitydocuments.config');

        // Publishing the views.
        /*$this->publishes([
            __DIR__.'/../resources/views' => base_path('resources/views/vendor/werk365'),
        ], 'identitydocuments.views');*/

        // Publishing assets.
        /*$this->publishes([
            __DIR__.'/../resources/assets' => public_path('vendor/werk365'),
        ], 'identitydocuments.views');*/

        // Publishing the translation files.
        /*$this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/werk365'),
        ], 'identitydocuments.views');*/

        // Registering package commands.
        // $this->commands([]);
    }
}
