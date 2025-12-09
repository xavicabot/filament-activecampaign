<?php

namespace XaviCabot\FilamentActiveCampaign\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use XaviCabot\FilamentActiveCampaign\Filament\Resources\ActiveCampaignAutomationResource\Pages;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignAutomation;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignField;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignList;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignTag;

class ActiveCampaignAutomationResource extends Resource
{
    protected static ?string $model = ActiveCampaignAutomation::class;

    protected static ?string $navigationIcon  = 'heroicon-o-bolt';

    public static function getNavigationLabel(): string
    {
        return __('Automations');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ActiveCampaign');
    }

    public static function getLabel(): ?string
    {
        return __('Automations');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('event')
                    ->label(__('Event key'))
                    ->required()
                    ->datalist([
                        'user.registered',
                        'user.logged_in',
                        'wallet.first_deposit',
                        'billing.invoice_paid',
                    ])
                    ->helperText(__('Free event identifier, e.g.: user.registered, wallet.first_deposit')),

                Forms\Components\Toggle::make('is_active')
                    ->label(__('Active'))
                    ->default(true),

                Forms\Components\Fieldset::make(__('Actions'))
                    ->schema([
                        Forms\Components\Select::make('list_ac_id')
                            ->label(__('Subscribe to list'))
                            ->options(
                                ActiveCampaignList::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'ac_id')
                            )
                            ->searchable()
                            ->placeholder(__('No list')),

                        Forms\Components\MultiSelect::make('tag_ac_ids')
                            ->label(__('Add tags'))
                            ->options(
                                ActiveCampaignTag::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'ac_id')
                            )
                            ->searchable()
                            ->placeholder(__('No tags')),

                        Forms\Components\Repeater::make('fields')
                            ->label(__('Set fields'))
                            ->schema([
                                Forms\Components\Select::make('field_ac_id')
                                    ->label(__('Field'))
                                    ->options(
                                        ActiveCampaignField::query()
                                            ->orderBy('name')
                                            ->pluck('name', 'ac_id')
                                    )
                                    ->searchable()
                                    ->required(),

                                Forms\Components\TextInput::make('value_template')
                                    ->label(__('Value template'))
                                    ->helperText(__('Placeholders: {now}, {now_date}, {user.email}, {user.name}, {user.id}'))
                                    ->required(),
                            ])
                            ->columns(2)
                            ->minItems(0),
                        Forms\Components\KeyValue::make('system_fields')
                            ->label(__('System fields'))
                            ->helperText(__('Native contact fields: firstName, lastName, phone, etc. Eg: phone → {user.phone}'))
                            ->keyLabel(__('Field name'))
                            ->valueLabel(__('Value template'))
                            ->nullable(),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('event')
                    ->label(__('Event'))
                    ->badge()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label(__('Active')),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Updated at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                ->label(__('Create automation')),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActiveCampaignAutomations::route('/'),
            'create' => Pages\CreateActiveCampaignAutomation::route('/create'),
            'edit' => Pages\EditActiveCampaignAutomation::route('/{record}/edit'),
        ];
    }
}
