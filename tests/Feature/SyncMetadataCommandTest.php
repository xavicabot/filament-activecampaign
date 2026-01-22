<?php

declare(strict_types=1);

namespace XaviCabot\FilamentActiveCampaign\Tests\Feature;

use Illuminate\Support\Facades\Http;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignField;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignList;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignTag;
use XaviCabot\FilamentActiveCampaign\Tests\TestCase;

class SyncMetadataCommandTest extends TestCase
{
    protected function fakeActiveCampaignApi(): void
    {
        Http::fake([
            '*/api/3/lists*' => Http::response([
                'lists' => [
                    ['id' => '1', 'name' => 'Main list', 'stringid' => 'main-list', 'status' => 1],
                    ['id' => '2', 'name' => 'VIP list', 'stringid' => 'vip-list', 'status' => 1],
                ],
            ], 200),
            '*/api/3/tags*' => Http::response([
                'tags' => [
                    ['id' => '10', 'tag' => 'customer', 'tagType' => 'contact', 'description' => 'Customer tag'],
                    ['id' => '11', 'tag' => 'vip', 'tagType' => 'contact', 'description' => 'VIP tag'],
                ],
            ], 200),
            '*/api/3/fields*' => Http::response([
                'fields' => [
                    ['id' => '100', 'title' => 'FIRST_PURCHASE_AT', 'type' => 'date', 'fieldType' => 'text', 'isrequired' => 0],
                    ['id' => '101', 'title' => 'LTV', 'type' => 'text', 'fieldType' => 'text', 'isrequired' => 0],
                ],
            ], 200),
        ]);
    }

    public function test_it_runs_the_command_without_errors(): void
    {
        $this->fakeActiveCampaignApi();

        $this->artisan('activecampaign:sync-metadata')
            ->assertExitCode(0);
    }

    public function test_it_syncs_lists(): void
    {
        $this->fakeActiveCampaignApi();

        $this->artisan('activecampaign:sync-metadata --lists')
            ->assertSuccessful();

        $this->assertDatabaseHas('activecampaign_lists', [
            'ac_id' => '1',
            'name' => 'Main list',
        ]);

        $this->assertDatabaseHas('activecampaign_lists', [
            'ac_id' => '2',
            'name' => 'VIP list',
        ]);

        $this->assertEquals(2, ActiveCampaignList::count());
    }

    public function test_it_syncs_tags(): void
    {
        $this->fakeActiveCampaignApi();

        $this->artisan('activecampaign:sync-metadata --tags')
            ->assertSuccessful();

        $this->assertDatabaseHas('activecampaign_tags', [
            'ac_id' => '10',
            'name' => 'customer',
        ]);

        $this->assertDatabaseHas('activecampaign_tags', [
            'ac_id' => '11',
            'name' => 'vip',
        ]);

        $this->assertEquals(2, ActiveCampaignTag::count());
    }

    public function test_it_syncs_fields(): void
    {
        $this->fakeActiveCampaignApi();

        $this->artisan('activecampaign:sync-metadata --fields')
            ->assertSuccessful();

        $this->assertDatabaseHas('activecampaign_fields', [
            'ac_id' => '100',
            'name' => 'FIRST_PURCHASE_AT',
        ]);

        $this->assertDatabaseHas('activecampaign_fields', [
            'ac_id' => '101',
            'name' => 'LTV',
        ]);

        $this->assertEquals(2, ActiveCampaignField::count());
    }

    public function test_it_syncs_all_metadata_by_default(): void
    {
        $this->fakeActiveCampaignApi();

        $this->artisan('activecampaign:sync-metadata')
            ->assertSuccessful();

        $this->assertEquals(2, ActiveCampaignList::count());
        $this->assertEquals(2, ActiveCampaignTag::count());
        $this->assertEquals(2, ActiveCampaignField::count());
    }
}
