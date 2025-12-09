<?php

namespace XaviCabot\FilamentActiveCampaign\Filament\Resources\ActiveCampaignListResource\Pages;

use Filament\Resources\Pages\ListRecords;
use XaviCabot\FilamentActiveCampaign\Filament\Resources\ActiveCampaignListResource;

class ListActiveCampaignLists extends ListRecords
{
    protected static string $resource = ActiveCampaignListResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
