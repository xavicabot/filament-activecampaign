<?php

namespace XaviCabot\FilamentActiveCampaign\Filament\Resources\ActiveCampaignAutomationResource\Pages;

use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use XaviCabot\FilamentActiveCampaign\Filament\Resources\ActiveCampaignAutomationResource;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignAutomation;
use XaviCabot\FilamentActiveCampaign\Services\ActiveCampaignService;

class ListActiveCampaignAutomations extends ListRecords
{
    protected static string $resource = ActiveCampaignAutomationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export')
                ->label(__('Export'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (): StreamedResponse {
                    $automations = ActiveCampaignAutomation::all()
                        ->map(fn ($automation) => [
                            'name' => $automation->name,
                            'event' => $automation->event,
                            'is_active' => $automation->is_active,
                            'list_ac_id' => $automation->list_ac_id,
                            'tag_ac_ids' => $automation->tag_ac_ids,
                            'fields' => $automation->fields,
                            'system_fields' => $automation->system_fields,
                        ])
                        ->toArray();

                    $filename = 'activecampaign-automations-' . date('Y-m-d') . '.json';

                    return response()->streamDownload(function () use ($automations) {
                        echo json_encode($automations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    }, $filename, [
                        'Content-Type' => 'application/json',
                    ]);
                }),

            Actions\Action::make('import')
                ->label(__('Import'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->form([
                    FileUpload::make('file')
                        ->label(__('JSON File'))
                        ->acceptedFileTypes(['application/json'])
                        ->required()
                        ->disk('local')
                        ->directory('activecampaign-imports')
                        ->visibility('private'),
                ])
                ->action(function (array $data): void {
                    $filePath = Storage::disk('local')->path($data['file']);
                    $content = file_get_contents($filePath);
                    Storage::disk('local')->delete($data['file']);

                    $automations = json_decode($content, true);

                    if (! is_array($automations)) {
                        Notification::make()
                            ->title(__('Import failed'))
                            ->body(__('Invalid JSON format. Expected an array of automations.'))
                            ->danger()
                            ->send();

                        return;
                    }

                    $imported = 0;
                    $skipped = 0;
                    $existingNames = ActiveCampaignAutomation::pluck('name')->toArray();

                    foreach ($automations as $automation) {
                        if (! isset($automation['name'])) {
                            $skipped++;
                            continue;
                        }

                        if (in_array($automation['name'], $existingNames)) {
                            $skipped++;
                            continue;
                        }

                        ActiveCampaignAutomation::create([
                            'name' => $automation['name'],
                            'event' => $automation['event'] ?? null,
                            'is_active' => $automation['is_active'] ?? false,
                            'list_ac_id' => $automation['list_ac_id'] ?? null,
                            'tag_ac_ids' => $automation['tag_ac_ids'] ?? [],
                            'fields' => $automation['fields'] ?? [],
                            'system_fields' => $automation['system_fields'] ?? [],
                        ]);

                        $existingNames[] = $automation['name'];
                        $imported++;
                    }

                    Notification::make()
                        ->title(__('Import completed'))
                        ->body(__(':imported imported, :skipped skipped', [
                            'imported' => $imported,
                            'skipped' => $skipped,
                        ]))
                        ->success()
                        ->send();
                }),

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
