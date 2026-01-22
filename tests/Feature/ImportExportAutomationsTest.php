<?php

declare(strict_types=1);

namespace XaviCabot\FilamentActiveCampaign\Tests\Feature;

use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignAutomation;
use XaviCabot\FilamentActiveCampaign\Tests\TestCase;

class ImportExportAutomationsTest extends TestCase
{
    public function test_automation_can_be_exported_to_json_format(): void
    {
        $automation = ActiveCampaignAutomation::create([
            'name' => 'User Registration',
            'event' => 'user.registered',
            'is_active' => true,
            'list_ac_id' => '1',
            'tag_ac_ids' => ['5', '12'],
            'fields' => [
                ['field_ac_id' => '3', 'value_template' => '{user.name}'],
            ],
            'system_fields' => [
                'firstName' => '{user.name}',
                'phone' => '{user.phone}',
            ],
        ]);

        $exportData = [
            'name' => $automation->name,
            'event' => $automation->event,
            'is_active' => $automation->is_active,
            'list_ac_id' => $automation->list_ac_id,
            'tag_ac_ids' => $automation->tag_ac_ids,
            'fields' => $automation->fields,
            'system_fields' => $automation->system_fields,
        ];

        $this->assertEquals('User Registration', $exportData['name']);
        $this->assertEquals('user.registered', $exportData['event']);
        $this->assertTrue($exportData['is_active']);
        $this->assertEquals('1', $exportData['list_ac_id']);
        $this->assertEquals(['5', '12'], $exportData['tag_ac_ids']);
        $this->assertCount(1, $exportData['fields']);
        $this->assertEquals('3', $exportData['fields'][0]['field_ac_id']);
        $this->assertEquals('{user.name}', $exportData['system_fields']['firstName']);
    }

    public function test_automation_can_be_imported_from_json_data(): void
    {
        $importData = [
            'name' => 'Newsletter Signup',
            'event' => 'newsletter.signup',
            'is_active' => true,
            'list_ac_id' => '2',
            'tag_ac_ids' => ['10'],
            'fields' => [],
            'system_fields' => ['firstName' => '{ctx.name}'],
        ];

        $automation = ActiveCampaignAutomation::create($importData);

        $this->assertDatabaseHas('activecampaign_automations', [
            'name' => 'Newsletter Signup',
            'event' => 'newsletter.signup',
            'is_active' => true,
            'list_ac_id' => '2',
        ]);

        $this->assertEquals(['10'], $automation->fresh()->tag_ac_ids);
        $this->assertEquals(['firstName' => '{ctx.name}'], $automation->fresh()->system_fields);
    }

    public function test_import_skips_automations_with_existing_names(): void
    {
        ActiveCampaignAutomation::create([
            'name' => 'Existing Automation',
            'event' => 'user.registered',
            'is_active' => true,
        ]);

        $importData = [
            [
                'name' => 'Existing Automation',
                'event' => 'user.updated',
                'is_active' => false,
            ],
            [
                'name' => 'New Automation',
                'event' => 'user.deleted',
                'is_active' => true,
            ],
        ];

        $imported = 0;
        $skipped = 0;
        $existingNames = ActiveCampaignAutomation::pluck('name')->toArray();

        foreach ($importData as $data) {
            if (! isset($data['name'])) {
                $skipped++;
                continue;
            }

            if (in_array($data['name'], $existingNames)) {
                $skipped++;
                continue;
            }

            ActiveCampaignAutomation::create($data);
            $existingNames[] = $data['name'];
            $imported++;
        }

        $this->assertEquals(1, $imported);
        $this->assertEquals(1, $skipped);

        $this->assertDatabaseHas('activecampaign_automations', [
            'name' => 'Existing Automation',
            'event' => 'user.registered',
        ]);

        $this->assertDatabaseHas('activecampaign_automations', [
            'name' => 'New Automation',
            'event' => 'user.deleted',
        ]);

        $this->assertEquals(2, ActiveCampaignAutomation::count());
    }

    public function test_import_skips_entries_without_name(): void
    {
        $importData = [
            [
                'event' => 'user.registered',
                'is_active' => true,
            ],
            [
                'name' => 'Valid Automation',
                'event' => 'user.updated',
                'is_active' => true,
            ],
        ];

        $imported = 0;
        $skipped = 0;
        $existingNames = ActiveCampaignAutomation::pluck('name')->toArray();

        foreach ($importData as $data) {
            if (! isset($data['name'])) {
                $skipped++;
                continue;
            }

            if (in_array($data['name'], $existingNames)) {
                $skipped++;
                continue;
            }

            ActiveCampaignAutomation::create($data);
            $existingNames[] = $data['name'];
            $imported++;
        }

        $this->assertEquals(1, $imported);
        $this->assertEquals(1, $skipped);
        $this->assertEquals(1, ActiveCampaignAutomation::count());
    }

    public function test_export_json_structure_is_valid(): void
    {
        ActiveCampaignAutomation::create([
            'name' => 'Test 1',
            'event' => 'event.one',
            'is_active' => true,
            'list_ac_id' => '1',
            'tag_ac_ids' => ['1', '2'],
            'fields' => [['field_ac_id' => '1', 'value_template' => '{user.id}']],
            'system_fields' => ['firstName' => '{user.name}'],
        ]);

        ActiveCampaignAutomation::create([
            'name' => 'Test 2',
            'event' => 'event.two',
            'is_active' => false,
        ]);

        $automations = ActiveCampaignAutomation::all()
            ->map(fn ($automation) => [
                'name' => $automation->name,
                'event' => $automation->event,
                'is_active' => $automation->is_active,
                'list_ac_id' => $automation->list_ac_id,
                'tag_ac_ids' => $automation->tag_ac_ids,
                'fields' => $automation->fields,
                'system_fields' => $automation->system_fields,
            ])
            ->toArray();

        $json = json_encode($automations, JSON_PRETTY_PRINT);

        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertCount(2, $decoded);
        $this->assertEquals('Test 1', $decoded[0]['name']);
        $this->assertEquals('Test 2', $decoded[1]['name']);
    }

    public function test_import_handles_optional_fields_with_defaults(): void
    {
        $importData = [
            'name' => 'Minimal Automation',
            'event' => 'minimal.event',
        ];

        $automation = ActiveCampaignAutomation::create([
            'name' => $importData['name'],
            'event' => $importData['event'] ?? null,
            'is_active' => $importData['is_active'] ?? false,
            'list_ac_id' => $importData['list_ac_id'] ?? null,
            'tag_ac_ids' => $importData['tag_ac_ids'] ?? [],
            'fields' => $importData['fields'] ?? [],
            'system_fields' => $importData['system_fields'] ?? [],
        ]);

        $this->assertDatabaseHas('activecampaign_automations', [
            'name' => 'Minimal Automation',
            'event' => 'minimal.event',
            'is_active' => false,
            'list_ac_id' => null,
        ]);

        $automation = $automation->fresh();
        $this->assertEquals([], $automation->tag_ac_ids);
        $this->assertEquals([], $automation->fields);
        $this->assertEquals([], $automation->system_fields);
    }
}
