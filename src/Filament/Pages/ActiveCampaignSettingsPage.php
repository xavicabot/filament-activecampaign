<?php

namespace XaviCabot\FilamentActiveCampaign\Filament\Pages;

use Filament\Forms;
use Filament\Actions;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Filament\Pages\Page;
use XaviCabot\FilamentActiveCampaign\Services\ActiveCampaignService;


class ActiveCampaignSettingsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cloud';
    protected static ?string $navigationLabel = null;
    protected static ?string $navigationGroup = null;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'activecampaign-settings';
    protected static string $view = 'filament-activecampaign::pages.settings';

    public ?string $base_url = '';
    public ?string $api_key = '';

    public function mount(): void
    {
        $this->base_url = config('activecampaign.base_url');
        $this->api_key  = config('activecampaign.api_key');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('base_url')
                    ->label(__('Base URL'))
                    ->required()
                    ->helperText(__('e.g. https://youraccountname.api-us1.com')),

                Forms\Components\TextInput::make('api_key')
                    ->label(__('API Key'))
                    ->password()
                    ->revealable()
                    ->required(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        // Aquí puedes usar tu propio sistema para guardar en DB,
        // o simplemente mostrar que hay que usar .env.
        Notification::make()
            ->title(__('ActiveCampaign settings saved (configure .env manually).'))
            ->success()
            ->send();

        // Opcionalmente: recargar config / cache
        Artisan::call('config:clear');
    }

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

//    protected function getFormModel(): mixed
//    {
//        return $this;
//    }
}
