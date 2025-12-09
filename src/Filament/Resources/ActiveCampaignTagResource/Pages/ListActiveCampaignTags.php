<?php

namespace XaviCabot\FilamentActiveCampaign\Filament\Resources\ActiveCampaignTagResource\Pages;

use Filament\Resources\Pages\ListRecords;
use XaviCabot\FilamentActiveCampaign\Filament\Resources\ActiveCampaignTagResource;

class ListActiveCampaignTags extends ListRecords
{
    protected static string $resource = ActiveCampaignTagResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
