<?php

declare(strict_types=1);

namespace XaviCabot\FilamentActiveCampaign\Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Http;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignAutomation;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignAutomationLog;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignList;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignTag;
use XaviCabot\FilamentActiveCampaign\Services\ActiveCampaignAutomationRunner;
use XaviCabot\FilamentActiveCampaign\Tests\TestCase;

class AutomationExecutionTest extends TestCase
{
    protected function fakeActiveCampaignApi(): void
    {
        Http::fake([
            '*/api/3/contact/sync' => Http::response([
                'contact' => ['id' => '123'],
            ], 200),
            '*/api/3/contactLists' => Http::response(['contactList' => []], 200),
            '*/api/3/contactTags' => Http::response(['contactTag' => []], 200),
            '*/api/3/fieldValues' => Http::response(['fieldValue' => []], 200),
            '*/api/3/tags*' => Http::response([
                'tags' => [
                    ['id' => '10', 'tag' => 'first-deposit'],
                ],
            ], 200),
            '*' => Http::response([], 200),
        ]);
    }

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

    public function test_trigger_executes_matching_automations(): void
    {
        $this->fakeActiveCampaignApi();

        ActiveCampaignList::create([
            'ac_id' => '1',
            'name' => 'Main List',
        ]);

        ActiveCampaignAutomation::create([
            'name' => 'Welcome Flow',
            'event' => 'user.registered',
            'is_active' => true,
            'list_ac_id' => '1',
        ]);

        $user = $this->createMockUser();

        $runner = app(ActiveCampaignAutomationRunner::class);
        $runner->trigger('user.registered', $user, ['plan' => 'pro']);

        $this->assertDatabaseHas('activecampaign_automation_logs', [
            'event' => 'user.registered',
            'user_id' => 1,
            'success' => true,
        ]);
    }

    public function test_trigger_with_email_works_without_user(): void
    {
        $this->fakeActiveCampaignApi();

        ActiveCampaignAutomation::create([
            'name' => 'Newsletter Signup',
            'event' => 'newsletter.signup',
            'is_active' => true,
        ]);

        $runner = app(ActiveCampaignAutomationRunner::class);
        $runner->triggerWithEmail('newsletter.signup', 'jane@example.com', [
            'firstName' => 'Jane',
        ]);

        $this->assertDatabaseHas('activecampaign_automation_logs', [
            'event' => 'newsletter.signup',
            'user_id' => null,
            'success' => true,
        ]);
    }

    public function test_inactive_automations_are_not_executed(): void
    {
        $this->fakeActiveCampaignApi();

        ActiveCampaignAutomation::create([
            'name' => 'Disabled Flow',
            'event' => 'user.registered',
            'is_active' => false,
        ]);

        $user = $this->createMockUser();

        $runner = app(ActiveCampaignAutomationRunner::class);
        $runner->trigger('user.registered', $user);

        $this->assertDatabaseMissing('activecampaign_automation_logs', [
            'event' => 'user.registered',
        ]);
    }

    public function test_logs_contain_context_and_payload(): void
    {
        $this->fakeActiveCampaignApi();

        ActiveCampaignList::create([
            'ac_id' => '5',
            'name' => 'VIP List',
        ]);

        ActiveCampaignTag::create([
            'ac_id' => '10',
            'name' => 'first-deposit',
        ]);

        ActiveCampaignAutomation::create([
            'name' => 'First Deposit',
            'event' => 'wallet.first_deposit',
            'is_active' => true,
            'list_ac_id' => '5',
            'tag_ac_ids' => ['10'],
        ]);

        $user = $this->createMockUser();

        $runner = app(ActiveCampaignAutomationRunner::class);
        $runner->trigger('wallet.first_deposit', $user, ['amount' => 100]);

        $log = ActiveCampaignAutomationLog::first();

        $this->assertNotNull($log);
        $this->assertTrue($log->success);
        $this->assertEquals(['amount' => 100], $log->context);
        $this->assertArrayHasKey('list_ac_id', $log->payload);
        $this->assertArrayHasKey('tags', $log->payload);
    }
}
