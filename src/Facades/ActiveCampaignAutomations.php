<?php

namespace XaviCabot\FilamentActiveCampaign\Facades;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Facade;
use XaviCabot\FilamentActiveCampaign\Services\ActiveCampaignAutomationRunner;

/**
 * @method static void trigger(string $event, Authenticatable $user, array $context = [])
 * @method static void triggerWithEmail(string $event, string $email, array $contactData = [], array $context = [])
 */
class ActiveCampaignAutomations extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ActiveCampaignAutomationRunner::class;
    }
}
