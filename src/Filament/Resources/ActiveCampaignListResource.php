<?php

namespace XaviCabot\FilamentActiveCampaign\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use XaviCabot\FilamentActiveCampaign\Filament\Resources\ActiveCampaignListResource\Pages;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignList;

class ActiveCampaignListResource extends Resource
{
    protected static ?string $model = ActiveCampaignList::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationLabel(): string
    {
        return __('AC Lists');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ActiveCampaign');
    }

    public static function getLabel(): ?string
    {
        return __('AC Lists');
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
                    ->sortable()
                    ->copyable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label(__('Active')),
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
            'index' => Pages\ListActiveCampaignLists::route('/'),
        ];
    }
}
