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
                        ->unique(
                            table: ActiveCampaignTag::class,
                            column: 'name',
                            ignoreRecord: false
                        )
                        ->validationMessages([
                            'unique' => __('A tag with this name already exists.'),
                        ])
                        ->helperText(__('The name of the tag as it will appear in ActiveCampaign'))
                        ->placeholder(__('e.g., VIP Customer')),

                    TextInput::make('description')
                        ->label(__('Description'))
                        ->maxLength(255)
                        ->helperText(__('Optional description for internal reference')),
                ])
                ->action(function (array $data, ActiveCampaignService $service): void {
                    try {
                        // Create tag in ActiveCampaign and store locally
                        $tag = $service->createTag(
                            name: $data['name'],
                            description: $data['description'] ?? null
                        );

                        Notification::make()
                            ->title(__('Tag created successfully'))
                            ->body(__('Tag ":name" has been created in ActiveCampaign with ID: :id', [
                                'name' => $tag->name,
                                'id' => $tag->ac_id,
                            ]))
                            ->success()
                            ->send();

                        // Refresh the table to show the new tag
                        $this->dispatch('$refresh');

                    } catch (ActiveCampaignException $e) {
                        Notification::make()
                            ->title(__('Failed to create tag'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title(__('Unexpected error'))
                            ->body(__('An error occurred while creating the tag: :error', ['error' => $e->getMessage()]))
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
