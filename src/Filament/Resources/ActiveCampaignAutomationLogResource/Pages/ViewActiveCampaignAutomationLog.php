<?php

namespace XaviCabot\FilamentActiveCampaign\Filament\Resources\ActiveCampaignAutomationLogResource\Pages;

use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use XaviCabot\FilamentActiveCampaign\Filament\Resources\ActiveCampaignAutomationLogResource;

class ViewActiveCampaignAutomationLog extends ViewRecord
{
    protected static string $resource = ActiveCampaignAutomationLogResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('id')
                    ->label(__('ID')),

                TextEntry::make('automation.name')
                    ->label(__('Automation')),

                TextEntry::make('event')
                    ->label(__('Event')),

                TextEntry::make('success')
                    ->label(__('Success'))
                    ->formatStateUsing(fn (bool $state) => $state ? __('Yes') : __('No')),

                TextEntry::make('user_id')
                    ->label(__('User ID')),

                TextEntry::make('error_message')
                    ->label(__('Error'))
                    ->visible(fn ($record) => filled($record->error_message)),

                TextEntry::make('warnings')
                    ->label(__('Warnings'))
                    ->columnSpanFull()
                    ->state(function ($record) {
                        $payload = $record->payload ?? [];

                        if (is_string($payload)) {
                            $decoded = json_decode($payload, true);
                            if (is_array($decoded)) {
                                $payload = $decoded;
                            }
                        }

                        $warnings = $payload['warnings'] ?? [];

                        if (! is_array($warnings) || empty($warnings)) {
                            return __('(no warnings)');
                        }

                        return json_encode($warnings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    })
                    ->html()
                    ->formatStateUsing(fn ($state) => "
        <pre style='
            background:#1f2933;
            color:#e2e8f0;
            padding:12px;
            border-radius:6px;
            font-size:13px;
            overflow-x:auto;
            white-space:pre;
        '><code>{$state}</code></pre>
    ")
                    ->columnSpanFull(),

                TextEntry::make('context')
                    ->label(__('Context'))
                    ->columnSpanFull()
                    ->state(function ($record) {
                        $value = $record->context;

                        if (is_array($value)) {
                            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        }

                        if (is_string($value)) {
                            return $value;
                        }

                        return '{}';
                    })
                    ->html()
                    ->formatStateUsing(fn ($state) => "
        <pre style='
            background:#0f172a;
            color:#e2e8f0;
            padding:16px;
            border-radius:6px;
            font-size:14px;
            overflow-x:auto;
            white-space:pre;
        '><code>{$state}</code></pre>
    ")
                    ->visible(fn ($record) => true),

                TextEntry::make('payload')
                    ->label(__('Payload'))
                    ->columnSpanFull()
                    ->state(function ($record) {
                        $value = $record->payload;

                        if (is_array($value)) {
                            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        }

                        if (is_string($value)) {
                            return $value;
                        }

                        return '{}';
                    })
                    ->html()
                    ->formatStateUsing(fn ($state) => "
        <pre style='
            background:#0f172a;
            color:#e2e8f0;
            padding:16px;
            border-radius:6px;
            font-size:14px;
            overflow-x:auto;
            white-space:pre;
        '><code>{$state}</code></pre>
    ")
            ]);
    }
}
