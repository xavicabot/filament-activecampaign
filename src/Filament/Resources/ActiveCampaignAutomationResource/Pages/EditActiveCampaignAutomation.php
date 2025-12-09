<?php

namespace XaviCabot\FilamentActiveCampaign\Filament\Resources\ActiveCampaignAutomationResource\Pages;

use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use XaviCabot\FilamentActiveCampaign\Filament\Resources\ActiveCampaignAutomationResource;
use XaviCabot\FilamentActiveCampaign\Services\ActiveCampaignAutomationRunner;

class EditActiveCampaignAutomation extends EditRecord
{
    protected static string $resource = ActiveCampaignAutomationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('preview')
                ->label(__('Preview execution'))
                ->icon('heroicon-o-eye')
                ->modalHeading(__('Preview automation execution'))
                ->form([
                    Forms\Components\Select::make('user_id')
                        ->label(__('User'))
                        ->required()
                        ->searchable()
                        ->options(function () {
                            $userModel = config('auth.providers.users.model');

                            if (! $userModel) {
                                return [];
                            }

                            return $userModel::query()
                                ->orderByDesc('id')
                                ->limit(50)
                                ->get()
                                ->pluck('email', 'id');
                        })
                        ->helperText(__('Select a real user to preview the automation.')),

                    Forms\Components\Textarea::make('context_json')
                        ->label(__('Context (JSON)'))
                        ->rows(5)
                        ->helperText(__('Optional. E.g.: {"amount":120,"currency":"EUR"}')),
                ])
                ->action(function (array $data, ActiveCampaignAutomationRunner $runner) {
                    $userModel = config('auth.providers.users.model');

                    if (! $userModel) {
                        Notification::make()
                            ->danger()
                            ->title(__('User model not configured'))
                            ->body(__('Check config/auth.php (providers.users.model).'))
                            ->send();

                        return;
                    }

                    $user = $userModel::findOrFail($data['user_id']);

                    $context = [];
                    if (! empty($data['context_json'])) {
                        $decoded = json_decode($data['context_json'], true);
                        if (is_array($decoded)) {
                            $context = $decoded;
                        }
                    }

                    $plan   = $runner->buildExecutionPlan($this->record, $user, $context);
                    $pretty = json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

                    Notification::make()
                        ->info()
                        ->title(__('Execution plan'))
                        ->body($pretty)
                        ->send();

                }),

            Actions\DeleteAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('id')->label(__('ID')),
                TextEntry::make('name')->label(__('Name')),
                TextEntry::make('event')->label(__('Event')),
            ]);
    }
}
