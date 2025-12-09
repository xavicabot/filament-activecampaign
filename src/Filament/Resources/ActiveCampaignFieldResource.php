<?php

namespace XaviCabot\FilamentActiveCampaign\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use XaviCabot\FilamentActiveCampaign\Filament\Resources\ActiveCampaignFieldResource\Pages;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignField;

class ActiveCampaignFieldResource extends Resource
{
    protected static ?string $model = ActiveCampaignField::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    public static function getNavigationLabel(): string
    {
        return __('AC Fields');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ActiveCampaign');
    }

    public static function getLabel(): ?string
    {
        return __('AC Fields');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ac_id')
                    ->label(__('AC ID'))
                    ->copyable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('Type'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('field_type')
                    ->label(__('Field type'))
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_required')
                    ->boolean()
                    ->label(__('Required')),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->label(__('Updated at'))
                    ->sortable(),
            ])
            ->filters([])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActiveCampaignFields::route('/'),
        ];
    }
}
