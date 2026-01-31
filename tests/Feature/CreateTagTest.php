<?php

declare(strict_types=1);

namespace XaviCabot\FilamentActiveCampaign\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use XaviCabot\FilamentActiveCampaign\Exceptions\ActiveCampaignException;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignTag;
use XaviCabot\FilamentActiveCampaign\Services\ActiveCampaignClient;
use XaviCabot\FilamentActiveCampaign\Services\ActiveCampaignService;
use XaviCabot\FilamentActiveCampaign\Tests\TestCase;

class CreateTagTest extends TestCase
{
    protected function fakeActiveCampaignCreateTagSuccess(
        string $name = 'Test Tag',
        ?string $description = null,
        string $id = '999'
    ): void {
        Http::fake([
            '*/api/3/tags' => Http::response([
                'tag' => [
                    'id' => $id,
                    'tag' => $name,
                    'tagType' => 'contact',
                    'description' => $description,
                ],
            ], 201),
        ]);
    }

    public function test_it_creates_tag_via_client(): void
    {
        $this->fakeActiveCampaignCreateTagSuccess('VIP Customer', 'High value customer');

        $client = new ActiveCampaignClient(
            baseUrl: config('activecampaign.base_url'),
            apiKey: config('activecampaign.api_key')
        );

        $response = $client->createTag('VIP Customer', 'contact', 'High value customer');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('tag', $response);
        $this->assertEquals('999', $response['tag']['id']);
        $this->assertEquals('VIP Customer', $response['tag']['tag']);
        $this->assertEquals('contact', $response['tag']['tagType']);
        $this->assertEquals('High value customer', $response['tag']['description']);
    }

    public function test_it_creates_tag_via_service(): void
    {
        $this->fakeActiveCampaignCreateTagSuccess('Premium User', 'Premium tier customers', '777');

        $service = app(ActiveCampaignService::class);
        $tag = $service->createTag('Premium User', 'Premium tier customers');

        $this->assertInstanceOf(ActiveCampaignTag::class, $tag);
        $this->assertEquals('777', $tag->ac_id);
        $this->assertEquals('Premium User', $tag->name);
        $this->assertEquals('contact', $tag->tag_type);
        $this->assertEquals('Premium tier customers', $tag->description);
    }

    public function test_it_stores_tag_in_database(): void
    {
        $this->fakeActiveCampaignCreateTagSuccess('New Tag', 'Test description', '888');

        $service = app(ActiveCampaignService::class);
        $tag = $service->createTag('New Tag', 'Test description');

        $this->assertDatabaseHas('activecampaign_tags', [
            'ac_id' => '888',
            'name' => 'New Tag',
            'tag_type' => 'contact',
            'description' => 'Test description',
        ]);

        $this->assertEquals($tag->id, ActiveCampaignTag::where('ac_id', '888')->first()->id);
    }

    public function test_it_invalidates_cache_after_creation(): void
    {
        $this->fakeActiveCampaignCreateTagSuccess('Cached Tag', null, '555');

        // Pre-populate cache with old value
        $cacheKey = 'activecampaign.tag_id.' . md5('Cached Tag');
        Cache::put($cacheKey, 'old-value', now()->addHour());

        $this->assertEquals('old-value', Cache::get($cacheKey));

        $service = app(ActiveCampaignService::class);
        $service->createTag('Cached Tag');

        // Cache should be cleared
        $this->assertNull(Cache::get($cacheKey));
    }

    public function test_it_throws_exception_on_missing_id_in_response(): void
    {
        Http::fake([
            '*/api/3/tags' => Http::response([
                'tag' => [
                    'tag' => 'Broken Tag',
                    'tagType' => 'contact',
                    // Missing 'id' field
                ],
            ], 201),
        ]);

        $service = app(ActiveCampaignService::class);

        $this->expectException(ActiveCampaignException::class);
        $this->expectExceptionMessage('Could not obtain tag id from ActiveCampaign response.');

        $service->createTag('Broken Tag');
    }

    public function test_it_throws_exception_on_api_error(): void
    {
        Http::fake([
            '*/api/3/tags' => Http::response([
                'message' => 'Tag already exists',
            ], 422),
        ]);

        $service = app(ActiveCampaignService::class);

        $this->expectException(ActiveCampaignException::class);

        $service->createTag('Duplicate Tag');
    }

    public function test_description_is_optional(): void
    {
        $this->fakeActiveCampaignCreateTagSuccess('Tag Without Description', null, '666');

        $service = app(ActiveCampaignService::class);
        $tag = $service->createTag('Tag Without Description');

        $this->assertInstanceOf(ActiveCampaignTag::class, $tag);
        $this->assertEquals('666', $tag->ac_id);
        $this->assertEquals('Tag Without Description', $tag->name);
        $this->assertNull($tag->description);
    }

    public function test_tag_type_is_always_contact(): void
    {
        $this->fakeActiveCampaignCreateTagSuccess('Contact Tag', 'Should always be contact type', '111');

        $service = app(ActiveCampaignService::class);
        $tag = $service->createTag('Contact Tag', 'Should always be contact type');

        $this->assertEquals('contact', $tag->tag_type);

        // Verify in database as well
        $this->assertDatabaseHas('activecampaign_tags', [
            'ac_id' => '111',
            'tag_type' => 'contact',
        ]);
    }

    // ============================================
    // Tests for getOrCreateTag
    // ============================================

    public function test_get_or_create_returns_existing_local_tag(): void
    {
        // Create tag in local database
        $existingTag = ActiveCampaignTag::create([
            'ac_id' => '123',
            'name' => 'Existing Tag',
            'tag_type' => 'contact',
            'description' => 'Already exists',
        ]);

        // No HTTP calls should be made
        Http::fake();

        $service = app(ActiveCampaignService::class);
        $tag = $service->getOrCreateTag('Existing Tag', 'New description');

        // Should return existing tag
        $this->assertEquals($existingTag->id, $tag->id);
        $this->assertEquals('123', $tag->ac_id);
        $this->assertEquals('Existing Tag', $tag->name);
        $this->assertEquals('Already exists', $tag->description);

        // Verify no HTTP calls were made
        Http::assertNothingSent();
    }

    public function test_get_or_create_is_case_insensitive(): void
    {
        // Create tag with lowercase
        $existingTag = ActiveCampaignTag::create([
            'ac_id' => '456',
            'name' => 'vip customer',
            'tag_type' => 'contact',
        ]);

        Http::fake();

        $service = app(ActiveCampaignService::class);

        // Search with different case
        $tag = $service->getOrCreateTag('VIP CUSTOMER');

        $this->assertEquals($existingTag->id, $tag->id);
        $this->assertEquals('vip customer', $tag->name);

        Http::assertNothingSent();
    }

    public function test_get_or_create_syncs_tag_from_activecampaign(): void
    {
        // Tag exists in AC but not in local DB
        Http::fake([
            '*/api/3/tags*' => Http::response([
                'tags' => [
                    [
                        'id' => '789',
                        'tag' => 'Remote Tag',
                        'tagType' => 'contact',
                        'description' => 'From AC',
                    ],
                ],
            ], 200),
        ]);

        $service = app(ActiveCampaignService::class);
        $tag = $service->getOrCreateTag('Remote Tag');

        // Should sync from AC to local DB
        $this->assertInstanceOf(ActiveCampaignTag::class, $tag);
        $this->assertEquals('789', $tag->ac_id);
        $this->assertEquals('Remote Tag', $tag->name);
        $this->assertEquals('From AC', $tag->description);

        // Verify it's in database
        $this->assertDatabaseHas('activecampaign_tags', [
            'ac_id' => '789',
            'name' => 'Remote Tag',
        ]);

        // Verify cache was updated
        $cacheKey = 'activecampaign.tag_id.' . md5('Remote Tag');
        $this->assertEquals('789', Cache::get($cacheKey));
    }

    public function test_get_or_create_creates_tag_when_not_found(): void
    {
        // Tag doesn't exist in AC
        Http::fake([
            '*/api/3/tags*' => Http::sequence()
                ->push(['tags' => []], 200) // listTags returns empty
                ->push([
                    'tag' => [
                        'id' => '999',
                        'tag' => 'Brand New Tag',
                        'tagType' => 'contact',
                        'description' => 'Created now',
                    ],
                ], 201), // createTag success
        ]);

        $service = app(ActiveCampaignService::class);
        $tag = $service->getOrCreateTag('Brand New Tag', 'Created now');

        $this->assertInstanceOf(ActiveCampaignTag::class, $tag);
        $this->assertEquals('999', $tag->ac_id);
        $this->assertEquals('Brand New Tag', $tag->name);
        $this->assertEquals('Created now', $tag->description);

        $this->assertDatabaseHas('activecampaign_tags', [
            'ac_id' => '999',
            'name' => 'Brand New Tag',
        ]);
    }

    public function test_get_or_create_ignores_description_for_existing_tags(): void
    {
        // Create existing tag
        ActiveCampaignTag::create([
            'ac_id' => '111',
            'name' => 'Old Tag',
            'tag_type' => 'contact',
            'description' => 'Original description',
        ]);

        Http::fake();

        $service = app(ActiveCampaignService::class);

        // Try to get with new description
        $tag = $service->getOrCreateTag('Old Tag', 'This description will be ignored');

        // Description should NOT change
        $this->assertEquals('Original description', $tag->description);
    }

    public function test_get_or_create_uses_description_only_when_creating(): void
    {
        // Tag doesn't exist anywhere
        Http::fake([
            '*/api/3/tags*' => Http::sequence()
                ->push(['tags' => []], 200)
                ->push([
                    'tag' => [
                        'id' => '222',
                        'tag' => 'New Tag',
                        'tagType' => 'contact',
                        'description' => 'Fresh description',
                    ],
                ], 201),
        ]);

        $service = app(ActiveCampaignService::class);
        $tag = $service->getOrCreateTag('New Tag', 'Fresh description');

        $this->assertEquals('Fresh description', $tag->description);
    }
}
