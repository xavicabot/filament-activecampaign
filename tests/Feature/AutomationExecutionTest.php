<?php

declare(strict_types=1);

namespace XaviCabot\FilamentActiveCampaign\Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use XaviCabot\FilamentActiveCampaign\Exceptions\ActiveCampaignException;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignAutomation;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignAutomationLog;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignList;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignTag;
use XaviCabot\FilamentActiveCampaign\Services\ActiveCampaignAutomationRunner;
use XaviCabot\FilamentActiveCampaign\Services\ActiveCampaignService;
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

    public function test_email_invalid_does_not_throw_exception(): void
    {
        Http::fake([
            '*/api/3/contact/sync' => Http::response([
                'errors' => [
                    [
                        'title' => 'Email is not valid',
                        'code' => 'email_invalid',
                        'error' => 'must_be_valid_email_address',
                        'source' => ['pointer' => '/data/attributes/email'],
                    ],
                ],
            ], 422),
        ]);

        ActiveCampaignAutomation::create([
            'name' => 'Welcome Flow',
            'event' => 'user.registered',
            'is_active' => true,
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $message === 'ActiveCampaign contact validation failed'
                    && $context['email'] === 'invalid-email'
                    && ! empty($context['validation_errors']);
            });

        $runner = app(ActiveCampaignAutomationRunner::class);

        // Should NOT throw - must return gracefully
        $runner->triggerWithEmail('user.registered', 'invalid-email');

        // Should log as failed, not crash
        $this->assertDatabaseHas('activecampaign_automation_logs', [
            'event' => 'user.registered',
            'success' => false,
        ]);

        $log = ActiveCampaignAutomationLog::first();
        $this->assertStringContains("invalid-email", $log->error_message);
    }

    public function test_email_invalid_logs_error_with_email_in_context(): void
    {
        Http::fake([
            '*/api/3/contact/sync' => Http::response([
                'errors' => [
                    [
                        'title' => 'Email is not valid',
                        'code' => 'email_invalid',
                        'error' => 'must_be_valid_email_address',
                    ],
                ],
            ], 422),
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $context['email'] === 'bad@email'
                    && str_contains($context['error'], 'validation error');
            });

        $service = app(ActiveCampaignService::class);
        $result = $service->getOrCreateContactIdByEmail(['email' => 'bad@email']);

        $this->assertNull($result);
    }

    public function test_server_error_still_throws_exception(): void
    {
        Http::fake([
            '*/api/3/contact/sync' => Http::response('Internal Server Error', 500),
        ]);

        $service = app(ActiveCampaignService::class);

        $this->expectException(ActiveCampaignException::class);
        $this->expectExceptionMessage('ActiveCampaign API error:');

        $service->getOrCreateContactIdByEmail(['email' => 'test@example.com']);
    }

    public function test_auth_error_still_throws_exception(): void
    {
        Http::fake([
            '*/api/3/contact/sync' => Http::response('Unauthorized', 401),
        ]);

        $service = app(ActiveCampaignService::class);

        $this->expectException(ActiveCampaignException::class);

        $service->getOrCreateContactIdByEmail(['email' => 'test@example.com']);
    }

    public function test_multiple_automations_all_log_failure_on_invalid_email(): void
    {
        Http::fake([
            '*/api/3/contact/sync' => Http::response([
                'errors' => [['code' => 'email_invalid']],
            ], 422),
        ]);

        ActiveCampaignAutomation::create([
            'name' => 'Flow A',
            'event' => 'user.registered',
            'is_active' => true,
        ]);

        ActiveCampaignAutomation::create([
            'name' => 'Flow B',
            'event' => 'user.registered',
            'is_active' => true,
        ]);

        Log::shouldReceive('warning')->once();

        $runner = app(ActiveCampaignAutomationRunner::class);
        $runner->triggerWithEmail('user.registered', 'invalid');

        // Both automations should have failure logs
        $this->assertEquals(2, ActiveCampaignAutomationLog::where('success', false)->count());
    }

    protected function assertStringContains(string $needle, ?string $haystack): void
    {
        $this->assertNotNull($haystack);
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'."
        );
    }
}
