<?php

namespace XaviCabot\FilamentActiveCampaign\Filament\Resources\ActiveCampaignAutomationResource\Pages;

use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use XaviCabot\FilamentActiveCampaign\Filament\Resources\ActiveCampaignAutomationResource;
use XaviCabot\FilamentActiveCampaign\Services\ActiveCampaignService;

class ListActiveCampaignAutomations extends ListRecords
{
    protected static string $resource = ActiveCampaignAutomationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sync_metadata')
                ->label(__('Sync metadata from ActiveCampaign'))
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->action(function (ActiveCampaignService $service) {
                    try {
                        // MISMA LÓGICA QUE EN EL COMANDO

                        $syncLists  = true;
                        $syncTags   = true;
                        $syncFields = true;

                        if ($syncLists) {
                            $listsCount = $service->syncLists();
                        }

                        if ($syncTags) {
                            $tagsCount = $service->syncTags();
                        }

                        if ($syncFields) {
                            $fieldsCount = $service->syncFields();
                        }

                        Notification::make()
                            ->title(__('Metadata synchronized'))
                            ->body(__("Lists, tags and fields have been refreshed from ActiveCampaign."))
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title(__('Sync failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
