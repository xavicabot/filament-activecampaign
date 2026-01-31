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
 * @method static \XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignTag createTag(string $name, ?string $description = null)
 * @method static int    syncLists()
 * @method static int    syncTags()
 * @method static int    syncFields()
 */
class ActiveCampaign extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ActiveCampaignService::class;
    }
}
