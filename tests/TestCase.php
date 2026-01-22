<?php

declare(strict_types=1);

namespace XaviCabot\FilamentActiveCampaign\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase as Orchestra;
use XaviCabot\FilamentActiveCampaign\ActiveCampaignServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            ActiveCampaignServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        Config::set('activecampaign.base_url', 'https://example.test/api/3');
        Config::set('activecampaign.api_key', 'fake-key');
        Config::set('activecampaign.cache_ttl', 60);
        Config::set('database.default', 'testing');
        Config::set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
