<?php

namespace XaviCabot\FilamentActiveCampaign;

use Illuminate\Support\ServiceProvider;
use XaviCabot\FilamentActiveCampaign\Console\InstallActiveCampaignCommand;
use XaviCabot\FilamentActiveCampaign\Console\SyncActiveCampaignMetadataCommand;
use XaviCabot\FilamentActiveCampaign\Services\ActiveCampaignAutomationRunner;
use XaviCabot\FilamentActiveCampaign\Services\ActiveCampaignClient;
use XaviCabot\FilamentActiveCampaign\Services\ActiveCampaignService;

class ActiveCampaignServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/activecampaign.php', 'activecampaign');

        $this->app->singleton(ActiveCampaignClient::class, function () {
            return new ActiveCampaignClient(
                config('activecampaign.base_url'),
                config('activecampaign.api_key'),
            );
        });

        $this->app->alias(ActiveCampaignClient::class, 'activecampaign.client');

        $this->app->singleton(ActiveCampaignService::class, function ($app) {
            return new ActiveCampaignService(
                $app->make(ActiveCampaignClient::class)
            );
        });
    }

    public function boot(): void
    {
        // Config
        $this->publishes([
            __DIR__ . '/../config/activecampaign.php' => config_path('activecampaign.php'),
        ], 'activecampaign-config');

        // Migraciones
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'activecampaign-migrations');

        // Opcional: cargar migraciones directamente desde el paquete
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Vistas del paquete
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'filament-activecampaign');

        // Traducciones JSON del paquete
        $this->loadJsonTranslationsFrom(__DIR__ . '/../resources/lang');

        // Publicar vistas
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/filament-activecampaign'),
        ], 'filament-activecampaign-views');

        // Publicar traducciones
        $this->publishes([
            __DIR__ . '/../resources/lang' => resource_path('lang/vendor/filament-activecampaign'),
        ], 'filament-activecampaign-lang');
        //

        $this->app->singleton(ActiveCampaignAutomationRunner::class, function ($app) {
            return new ActiveCampaignAutomationRunner(
                $app->make(ActiveCampaignService::class)
            );
        });

        // Comandos
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallActiveCampaignCommand::class,
                SyncActiveCampaignMetadataCommand::class,
            ]);
        }
    }
}
