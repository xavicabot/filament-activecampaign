<?php

namespace XaviCabot\FilamentActiveCampaign\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use XaviCabot\FilamentActiveCampaign\Filament\Resources\ActiveCampaignAutomationLogResource\Pages;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignAutomationLog;

class ActiveCampaignAutomationLogResource extends Resource
{
    protected static ?string $model = ActiveCampaignAutomationLog::class;

    protected static ?string $navigationIcon  = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'ActiveCampaign';
    protected static ?string $navigationLabel = null;

    public static function getLabel(): ?string
    {
        return __('Automation Logs');
    }

    public static function getNavigationLabel(): string
    {
        return __('Automation Logs');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ActiveCampaign');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),

                Tables\Columns\TextColumn::make('automation.name')
                    ->label(__('Automation'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('event')
                    ->badge()
                    ->sortable(),

                Tables\Columns\IconColumn::make('success')
                    ->boolean()
                    ->label(__('OK')),

                Tables\Columns\IconColumn::make('has_warnings')
                    ->label(__('Warn'))
                    ->boolean()
                    ->getStateUsing(function ($record) {
                        $payload = $record->payload ?? [];

                        if (is_string($payload)) {
                            $decoded = json_decode($payload, true);
                            if (is_array($decoded)) {
                                $payload = $decoded;
                            }
                        }

                        $warnings = $payload['warnings'] ?? [];

                        return is_array($warnings) && count($warnings) > 0;
                    }),

                Tables\Columns\TextColumn::make('user_id')
                    ->label(__('User ID'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Executed at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading(__('Automation log details'))
                    ->form([
                        Tables\Columns\TextColumn::make('automation.name')
                            ->label(__('Automation')),
                        Tables\Columns\TextColumn::make('event')
                            ->label(__('Event')),
                    ]),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActiveCampaignAutomationLogs::route('/'),
            'view'  => Pages\ViewActiveCampaignAutomationLog::route('/{record}'),
        ];
    }
}
