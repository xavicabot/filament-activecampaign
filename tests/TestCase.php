<?php
declare(strict_types=1);

namespace XaviCabot\FilamentActiveCampaign\Tests;

use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase as Orchestra;
use XaviCabot\FilamentActiveCampaign\ActiveCampaignServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ActiveCampaignServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        Config::set('activecampaign.url', 'https://example.test');
        Config::set('activecampaign.key', 'fake-key');
        Config::set('database.default', 'testing');
    }
}
