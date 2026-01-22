<?php

declare(strict_types=1);

namespace XaviCabot\FilamentActiveCampaign\Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Http;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignAutomation;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignField;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignList;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignTag;
use XaviCabot\FilamentActiveCampaign\Services\ActiveCampaignAutomationRunner;
use XaviCabot\FilamentActiveCampaign\Tests\TestCase;

class FilamentActionPreviewTest extends TestCase
{
    protected function createMockUser(): Authenticatable
    {
        return new class implements Authenticatable {
            public int $id = 1;
            public string $email = 'john@example.com';
            public string $name = 'John Doe';

            public function getAuthIdentifierName(): string
            {
                return 'id';
            }

            public function getAuthIdentifier(): mixed
            {
                return $this->id;
            }

            public function getAuthPasswordName(): string
            {
                return 'password';
            }

            public function getAuthPassword(): string
            {
                return '';
            }

            public function getRememberToken(): string
            {
                return '';
            }

            public function setRememberToken($value): void
            {
            }

            public function getRememberTokenName(): string
            {
                return '';
            }
        };
    }

    public function test_it_builds_execution_plan_with_list_and_tags(): void
    {
        Http::fake();

        ActiveCampaignList::create([
            'ac_id' => '1',
            'name' => 'Main list',
        ]);

        ActiveCampaignTag::create([
            'ac_id' => '5',
            'name' => 'EVENT_FIRST_DEPOSIT',
        ]);

        ActiveCampaignTag::create([
            'ac_id' => '12',
            'name' => 'VIP',
        ]);

        $automation = ActiveCampaignAutomation::create([
            'name' => 'First Deposit Flow',
            'event' => 'wallet.first_deposit',
            'is_active' => true,
            'list_ac_id' => '1',
            'tag_ac_ids' => ['5', '12'],
        ]);

        $runner = app(ActiveCampaignAutomationRunner::class);
        $plan = $runner->buildExecutionPlan($automation, $this->createMockUser());

        $this->assertIsArray($plan);
        $this->assertEquals('1', $plan['list_ac_id']);
        $this->assertArrayHasKey('tags', $plan);
        $this->assertCount(2, $plan['tags']);
        $this->assertEquals('EVENT_FIRST_DEPOSIT', $plan['tags']['5']);
        $this->assertEquals('VIP', $plan['tags']['12']);
    }

    public function test_it_builds_execution_plan_with_custom_fields(): void
    {
        Http::fake();

        ActiveCampaignField::create([
            'ac_id' => '100',
            'name' => 'FIRST_DEPOSIT_AMOUNT',
            'type' => 'text',
        ]);

        $automation = ActiveCampaignAutomation::create([
            'name' => 'First Deposit Flow',
            'event' => 'wallet.first_deposit',
            'is_active' => true,
            'fields' => [
                ['field_ac_id' => '100', 'value_template' => '{ctx.amount}'],
            ],
        ]);

        $user = $this->createMockUser();
        $context = ['amount' => '5000'];

        $runner = app(ActiveCampaignAutomationRunner::class);
        $plan = $runner->buildExecutionPlan($automation, $user, $context);

        $this->assertIsArray($plan);
        $this->assertArrayHasKey('custom_fields', $plan);
        $this->assertCount(1, $plan['custom_fields']);
        $this->assertEquals('100', $plan['custom_fields'][0]['field_ac_id']);
        $this->assertEquals('FIRST_DEPOSIT_AMOUNT', $plan['custom_fields'][0]['field_name']);
        $this->assertEquals('5000', $plan['custom_fields'][0]['value']);
    }

    public function test_it_builds_execution_plan_with_system_fields(): void
    {
        Http::fake();

        $automation = ActiveCampaignAutomation::create([
            'name' => 'Update Contact',
            'event' => 'user.updated',
            'is_active' => true,
            'system_fields' => [
                'firstName' => '{user.name}',
                'phone' => '{ctx.phone}',
            ],
        ]);

        $user = $this->createMockUser();
        $context = ['phone' => '+1234567890'];

        $runner = app(ActiveCampaignAutomationRunner::class);
        $plan = $runner->buildExecutionPlan($automation, $user, $context);

        $this->assertIsArray($plan);
        $this->assertArrayHasKey('system_fields', $plan);
        $this->assertEquals('John Doe', $plan['system_fields']['firstName']);
        $this->assertEquals('+1234567890', $plan['system_fields']['phone']);
    }

    public function test_it_adds_warnings_for_missing_metadata(): void
    {
        Http::fake();

        $automation = ActiveCampaignAutomation::create([
            'name' => 'Broken Flow',
            'event' => 'user.registered',
            'is_active' => true,
            'list_ac_id' => '999',
            'tag_ac_ids' => ['888'],
        ]);

        $runner = app(ActiveCampaignAutomationRunner::class);
        $plan = $runner->buildExecutionPlan($automation, $this->createMockUser());

        $this->assertIsArray($plan);
        $this->assertArrayHasKey('warnings', $plan);
        $this->assertNotEmpty($plan['warnings']);

        $warningTypes = array_column($plan['warnings'], 'type');
        $this->assertContains('missing_list', $warningTypes);
        $this->assertContains('missing_tag', $warningTypes);
    }
}
