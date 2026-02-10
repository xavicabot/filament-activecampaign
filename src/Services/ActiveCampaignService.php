<?php

namespace XaviCabot\FilamentActiveCampaign\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use XaviCabot\FilamentActiveCampaign\Exceptions\ActiveCampaignException;
use XaviCabot\FilamentActiveCampaign\Exceptions\ActiveCampaignValidationException;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignField;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignList;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignTag;

class ActiveCampaignService
{
    public function __construct(
        protected ActiveCampaignClient $client
    ) {
    }

    public function syncContact(array $data): array
    {
        return $this->client->syncContact($data);
    }

    public function getOrCreateContactIdByEmail(array $contactData): ?string
    {
        try {
            $result = $this->client->syncContact($contactData);
        } catch (ActiveCampaignValidationException $e) {
            Log::warning('ActiveCampaign contact validation failed', [
                'email' => $contactData['email'] ?? 'unknown',
                'error' => $e->getMessage(),
                'validation_errors' => $e->getValidationErrors(),
            ]);

            return null;
        }

        if (! isset($result['contact']['id'])) {
            throw new ActiveCampaignException('Could not obtain contact id from ActiveCampaign response.');
        }

        return (string) $result['contact']['id'];
    }

    public function addContactToList(string $contactId, string $listId): void
    {
        $this->client->subscribeContactToList($contactId, $listId);
    }

    public function addTagToContact(string $contactId, string $tagName): void
    {
        $tagId = $this->getTagIdByName($tagName);

        $this->client->attachTagToContact($contactId, $tagId);
    }

    public function setFieldValueForContact(string $contactId, string $fieldName, string $value): void
    {
        $fieldId = $this->getFieldIdByName($fieldName);

        $this->client->setFieldValue($contactId, $fieldId, $value);
    }

    protected function getTagIdByName(string $name): string
    {
        $cacheKey = 'activecampaign.tag_id.' . md5($name);
        $ttl      = now()->addMinutes((int) config('activecampaign.cache_ttl', 60));

        return Cache::remember($cacheKey, $ttl, function () use ($name) {
            $data = $this->client->listTags(['search' => $name]);

            $tag = collect($data['tags'] ?? [])
                ->first(fn ($tag) => strcasecmp($tag['tag'] ?? '', $name) === 0);

            if (! $tag || ! isset($tag['id'])) {
                throw new ActiveCampaignException("Tag '{$name}' not found in ActiveCampaign.");
            }

            return (string) $tag['id'];
        });
    }

    protected function getFieldIdByName(string $name): string
    {
        $cacheKey = 'activecampaign.field_id.' . md5($name);
        $ttl      = now()->addMinutes((int) config('activecampaign.cache_ttl', 60));

        return Cache::remember($cacheKey, $ttl, function () use ($name) {
            $data = $this->client->listFields(['search' => $name]);

            $field = collect($data['fields'] ?? [])
                ->first(fn ($field) => strcasecmp($field['title'] ?? '', $name) === 0);

            if (! $field || ! isset($field['id'])) {
                throw new ActiveCampaignException("Field '{$name}' not found in ActiveCampaign.");
            }

            return (string) $field['id'];
        });
    }

    // 🔁 SYNC LISTS
    public function syncLists(): int
    {
        $limit  = 100;
        $offset = 0;
        $totalSynced = 0;

        do {
            $data = $this->client->listLists([
                'limit'  => $limit,
                'offset' => $offset,
            ]);

            $lists = $data['lists'] ?? [];

            foreach ($lists as $list) {
                ActiveCampaignList::updateOrCreate(
                    ['ac_id' => (string) ($list['id'] ?? '')],
                    [
                        'name'        => $list['name'] ?? '',
                        'description' => $list['stringid'] ?? null,
                        'is_active'   => (bool) ($list['status'] ?? 1),
                    ]
                );
                $totalSynced++;
            }

            $count  = count($lists);
            $offset += $limit;
        } while ($count === $limit);

        return $totalSynced;
    }

    // 🔁 SYNC TAGS
    public function syncTags(): int
    {
        $limit  = 100;
        $offset = 0;
        $totalSynced = 0;

        do {
            $data = $this->client->listTags([
                'limit'  => $limit,
                'offset' => $offset,
            ]);

            $tags = $data['tags'] ?? [];

            foreach ($tags as $tag) {
                ActiveCampaignTag::updateOrCreate(
                    ['ac_id' => (string) ($tag['id'] ?? '')],
                    [
                        'name'        => $tag['tag'] ?? '',
                        'tag_type'    => $tag['tagType'] ?? null,
                        'description' => $tag['description'] ?? null,
                    ]
                );
                $totalSynced++;
            }

            $count  = count($tags);
            $offset += $limit;
        } while ($count === $limit);

        return $totalSynced;
    }

    // 🔁 SYNC FIELDS
    public function syncFields(): int
    {
        $limit  = 100;
        $offset = 0;
        $totalSynced = 0;

        do {
            $data = $this->client->listFields([
                'limit'  => $limit,
                'offset' => $offset,
            ]);

            $fields = $data['fields'] ?? [];

            foreach ($fields as $field) {
                ActiveCampaignField::updateOrCreate(
                    ['ac_id' => (string) ($field['id'] ?? '')],
                    [
                        'name'        => $field['title'] ?? '',
                        'type'        => $field['type'] ?? null,
                        'field_type'  => $field['fieldType'] ?? null,
                        'is_required' => isset($field['isrequired']) ? (bool) $field['isrequired'] : false,
                    ]
                );
                $totalSynced++;
            }

            $count  = count($fields);
            $offset += $limit;
        } while ($count === $limit);

        return $totalSynced;
    }

    /**
     * Create a new tag in ActiveCampaign and store it locally
     *
     * @param string $name Tag name
     * @param string|null $description Optional description
     * @return ActiveCampaignTag Created tag model instance
     * @throws ActiveCampaignException
     */
    public function createTag(string $name, ?string $description = null): ActiveCampaignTag
    {
        // Call ActiveCampaign API (always use 'contact' type)
        $response = $this->client->createTag($name, 'contact', $description);

        // Validate response structure
        if (!isset($response['tag']['id'])) {
            throw new ActiveCampaignException('Could not obtain tag id from ActiveCampaign response.');
        }

        $tagData = $response['tag'];

        // Store in local database
        $tag = ActiveCampaignTag::create([
            'ac_id' => (string) $tagData['id'],
            'name' => $tagData['tag'] ?? $name,
            'tag_type' => $tagData['tagType'] ?? 'contact',
            'description' => $tagData['description'] ?? $description,
        ]);

        // Invalidate cache for this tag name to ensure fresh lookups
        $cacheKey = 'activecampaign.tag_id.' . md5($name);
        Cache::forget($cacheKey);

        return $tag;
    }

    /**
     * Get an existing tag or create it if it doesn't exist
     *
     * This method searches for a tag by name in the following order:
     * 1. Local database (case-insensitive)
     * 2. ActiveCampaign API
     * 3. Creates it in ActiveCampaign if not found
     *
     * @param string $name Tag name
     * @param string|null $description Optional description (only used if creating)
     * @return ActiveCampaignTag Existing or newly created tag model instance
     * @throws ActiveCampaignException
     */
    public function getOrCreateTag(string $name, ?string $description = null): ActiveCampaignTag
    {
        // 1. Check local database first (case-insensitive)
        $localTag = ActiveCampaignTag::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();

        if ($localTag) {
            return $localTag;
        }

        // 2. Search in ActiveCampaign API
        $data = $this->client->listTags(['search' => $name]);

        $existingTag = collect($data['tags'] ?? [])
            ->first(fn ($tag) => strcasecmp($tag['tag'] ?? '', $name) === 0);

        // 3. If found in AC but not in local DB, sync it locally
        if ($existingTag && isset($existingTag['id'])) {
            $tag = ActiveCampaignTag::create([
                'ac_id' => (string) $existingTag['id'],
                'name' => $existingTag['tag'] ?? $name,
                'tag_type' => $existingTag['tagType'] ?? 'contact',
                'description' => $existingTag['description'] ?? null,
            ]);

            // Update cache
            $cacheKey = 'activecampaign.tag_id.' . md5($name);
            Cache::put($cacheKey, $tag->ac_id, now()->addMinutes((int) config('activecampaign.cache_ttl', 60)));

            return $tag;
        }

        // 4. Tag doesn't exist anywhere, create it
        return $this->createTag($name, $description);
    }

}
