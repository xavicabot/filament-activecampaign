<?php

namespace XaviCabot\FilamentActiveCampaign\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use XaviCabot\FilamentActiveCampaign\Filament\Resources\ActiveCampaignTagResource\Pages;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignTag;

class ActiveCampaignTagResource extends Resource
{
    protected static ?string $model = ActiveCampaignTag::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    public static function getNavigationLabel(): string
    {
        return __('AC Tags');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ActiveCampaign');
    }

    public static function getLabel(): ?string
    {
        return __('AC Tags');
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
                Tables\Columns\TextColumn::make('tag_type')
                    ->label(__('Type'))
                    ->sortable(),
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
            'index' => Pages\ListActiveCampaignTags::route('/'),
        ];
    }
}
