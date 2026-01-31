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
}
