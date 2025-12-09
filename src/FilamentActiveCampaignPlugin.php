<?php

namespace XaviCabot\FilamentActiveCampaign;

use Filament\Contracts\Plugin;
use Filament\Panel;
use XaviCabot\FilamentActiveCampaign\Filament\Pages\ActiveCampaignSettingsPage;
use XaviCabot\FilamentActiveCampaign\Filament\Resources\ActiveCampaignAutomationLogResource;
use XaviCabot\FilamentActiveCampaign\Filament\Resources\ActiveCampaignAutomationResource;
use XaviCabot\FilamentActiveCampaign\Filament\Resources\ActiveCampaignFieldResource;
use XaviCabot\FilamentActiveCampaign\Filament\Resources\ActiveCampaignListResource;
use XaviCabot\FilamentActiveCampaign\Filament\Resources\ActiveCampaignTagResource;

class FilamentActiveCampaignPlugin implements Plugin
{
    public static function make(): static
    {
        return new static();
    }

    public function getId(): string
    {
        return 'filament-activecampaign';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages([
                ActiveCampaignSettingsPage::class,
            ])
            ->resources([
                ActiveCampaignListResource::class,
                ActiveCampaignTagResource::class,
                ActiveCampaignFieldResource::class,
                ActiveCampaignAutomationResource::class,
                ActiveCampaignAutomationLogResource::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
