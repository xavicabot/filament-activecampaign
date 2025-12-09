<?php

namespace XaviCabot\FilamentActiveCampaign\Facades;

use Illuminate\Support\Facades\Facade;
use XaviCabot\FilamentActiveCampaign\Services\ActiveCampaignService;

/**
 * @method static array  syncContact(array $data)
 * @method static string getOrCreateContactIdByEmail(array $contactData)
 * @method static void   addContactToList(string $contactId, string $listId)
 * @method static void   addTagToContact(string $contactId, string $tagName)
 * @method static void   setFieldValueForContact(string $contactId, string $fieldName, string $value)
 */
class ActiveCampaign extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ActiveCampaignService::class;
    }
}
