<?php

namespace XaviCabot\FilamentActiveCampaign\Filament\Resources\ActiveCampaignTagResource\Pages;

use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use XaviCabot\FilamentActiveCampaign\Exceptions\ActiveCampaignException;
use XaviCabot\FilamentActiveCampaign\Filament\Resources\ActiveCampaignTagResource;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignTag;
use XaviCabot\FilamentActiveCampaign\Services\ActiveCampaignService;

class ListActiveCampaignTags extends ListRecords
{
    protected static string $resource = ActiveCampaignTagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_tag')
                ->label(__('Create Tag'))
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->form([
                    TextInput::make('name')
                        ->label(__('Tag Name'))
                        ->required()
                        ->maxLength(255)
                        ->helperText(__('The name of the tag as it will appear in ActiveCampaign. If the tag already exists, it will be retrieved instead of creating a duplicate.'))
                        ->placeholder(__('e.g., VIP Customer')),

                    TextInput::make('description')
                        ->label(__('Description'))
                        ->maxLength(255)
                        ->helperText(__('Optional description for internal reference (only used when creating a new tag)')),
                ])
                ->action(function (array $data, ActiveCampaignService $service): void {
                    try {
                        // Get existing tag or create if it doesn't exist
                        $tag = $service->getOrCreateTag(
                            name: $data['name'],
                            description: $data['description'] ?? null
                        );

                        Notification::make()
                            ->title(__('Tag ready'))
                            ->body(__('Tag ":name" is now available (ID: :id)', [
                                'name' => $tag->name,
                                'id' => $tag->ac_id,
                            ]))
                            ->success()
                            ->send();

                        // Refresh the table to show the tag
                        $this->dispatch('$refresh');

                    } catch (ActiveCampaignException $e) {
                        Notification::make()
                            ->title(__('Failed to get or create tag'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title(__('Unexpected error'))
                            ->body(__('An error occurred: :error', ['error' => $e->getMessage()]))
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
