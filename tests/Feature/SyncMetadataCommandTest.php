<?php

declare(strict_types=1);

namespace XaviCabot\FilamentActiveCampaign\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use XaviCabot\FilamentActiveCampaign\Tests\TestCase;

class SyncMetadataCommandTest extends TestCase
{
    public function test_it_runs_the_command_without_errors(): void
    {
        Http::fake([
            'https://example.test/api/3/lists*' => Http::response([
                'lists' => [
                    ['id' => 1, 'name' => 'Main list'],
                    ['id' => 2, 'name' => 'VIP list'],
                ],
            ], 200),
            'https://example.test/api/3/tags*' => Http::response([
                'tags' => [
                    ['id' => 10, 'tag' => 'customer'],
                    ['id' => 11, 'tag' => 'vip'],
                ],
            ], 200),
            'https://example.test/api/3/fields*' => Http::response([
                'fields' => [
                    ['id' => 100, 'title' => 'FIRST_PURCHASE_AT'],
                    ['id' => 101, 'title' => 'LTV'],
                ],
            ], 200),
        ]);

        $this->artisan('filament-activecampaign:sync-metadata')
            ->assertExitCode(0);
    }

    public function test_it_persists_lists_tags_and_fields_in_the_database(): void
    {
        if (! Schema::hasTable('activecampaign_lists')) {
            $this->markTestSkipped('activecampaign_lists table does not exist yet.');
        }

        Http::fake([
            'https://example.test/api/3/lists*' => Http::response([
                'lists' => [
                    ['id' => 1, 'name' => 'Main list'],
                    ['id' => 2, 'name' => 'VIP list'],
                ],
            ], 200),
            'https://example.test/api/3/tags*' => Http::response([
                'tags' => [
                    ['id' => 10, 'tag' => 'customer'],
                    ['id' => 11, 'tag' => 'vip'],
                ],
            ], 200),
            'https://example.test/api/3/fields*' => Http::response([
                'fields' => [
                    ['id' => 100, 'title' => 'FIRST_PURCHASE_AT'],
                    ['id' => 101, 'title' => 'LTV'],
                ],
            ], 200),
        ]);

        $this->artisan('filament-activecampaign:sync-metadata')
            ->assertSuccessful();

        $this->assertDatabaseHas('activecampaign_lists', [
            'remote_id' => 1,
            'name'      => 'Main list',
        ]);

        $this->assertDatabaseHas('activecampaign_tags', [
            'remote_id' => 10,
            'name'      => 'customer',
        ]);

        $this->assertDatabaseHas('activecampaign_fields', [
            'remote_id' => 100,
            'name'      => 'FIRST_PURCHASE_AT',
        ]);
    }
}
