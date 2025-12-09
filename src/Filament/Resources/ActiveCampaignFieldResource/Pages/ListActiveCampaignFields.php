<?php

namespace XaviCabot\FilamentActiveCampaign\Filament\Resources\ActiveCampaignFieldResource\Pages;

use Filament\Resources\Pages\ListRecords;
use XaviCabot\FilamentActiveCampaign\Filament\Resources\ActiveCampaignFieldResource;

class ListActiveCampaignFields extends ListRecords
{
    protected static string $resource = ActiveCampaignFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
