<?php

declare(strict_types=1);

namespace XaviCabot\FilamentActiveCampaign\Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use XaviCabot\FilamentActiveCampaign\Jobs\TriggerActiveCampaignAutomationJob;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignAutomation;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignAutomationLog;
use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignList;
use XaviCabot\FilamentActiveCampaign\Services\ActiveCampaignAutomationRunner;
use XaviCabot\FilamentActiveCampaign\Tests\TestCase;

class AsyncTriggerTest extends TestCase
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
                'tags' => [['id' => '10', 'tag' => 'first-deposit']],
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

    // ─── trigger() with async config ───────────────────────────────

    public function test_trigger_runs_synchronously_when_async_is_false(): void
    {
        $this->fakeActiveCampaignApi();
        config(['activecampaign.async' => false]);

        ActiveCampaignList::create(['ac_id' => '1', 'name' => 'Main List']);
        ActiveCampaignAutomation::create([
            'name' => 'Welcome',
            'event' => 'user.registered',
            'is_active' => true,
            'list_ac_id' => '1',
        ]);

        Bus::fake();

        $runner = app(ActiveCampaignAutomationRunner::class);
        $runner->trigger('user.registered', $this->createMockUser());

        Bus::assertNotDispatched(TriggerActiveCampaignAutomationJob::class);
    }

    public function test_trigger_dispatches_job_when_async_config_is_true(): void
    {
        config(['activecampaign.async' => true]);
        Bus::fake();

        $user = $this->createMockUser();
        $runner = app(ActiveCampaignAutomationRunner::class);
        $runner->trigger('user.registered', $user, ['plan' => 'pro']);

        Bus::assertDispatched(TriggerActiveCampaignAutomationJob::class, function ($job) {
            return $job->event === 'user.registered'
                && $job->userId === 1
                && $job->context === ['plan' => 'pro']
                && $job->email === 'john@example.com'
                && $job->contactData === ['email' => 'john@example.com', 'firstName' => 'John Doe'];
        });
    }

    public function test_trigger_dispatches_to_configured_queue(): void
    {
        config([
            'activecampaign.async' => true,
            'activecampaign.queue' => 'activecampaign',
        ]);
        Bus::fake();

        $runner = app(ActiveCampaignAutomationRunner::class);
        $runner->trigger('user.registered', $this->createMockUser());

        Bus::assertDispatched(TriggerActiveCampaignAutomationJob::class, function ($job) {
            return $job->queue === 'activecampaign';
        });
    }

    // ─── triggerAsync() ────────────────────────────────────────────

    public function test_trigger_async_always_dispatches_job_regardless_of_config(): void
    {
        config(['activecampaign.async' => false]);
        Bus::fake();

        $user = $this->createMockUser();
        $runner = app(ActiveCampaignAutomationRunner::class);
        $runner->triggerAsync('user.registered', $user, ['plan' => 'pro']);

        Bus::assertDispatched(TriggerActiveCampaignAutomationJob::class, function ($job) {
            return $job->event === 'user.registered'
                && $job->userId === 1
                && $job->context === ['plan' => 'pro'];
        });
    }

    // ─── triggerWithEmail() with async config ──────────────────────

    public function test_trigger_with_email_runs_synchronously_when_async_is_false(): void
    {
        $this->fakeActiveCampaignApi();
        config(['activecampaign.async' => false]);

        ActiveCampaignAutomation::create([
            'name' => 'Newsletter',
            'event' => 'newsletter.signup',
            'is_active' => true,
        ]);

        Bus::fake();

        $runner = app(ActiveCampaignAutomationRunner::class);
        $runner->triggerWithEmail('newsletter.signup', 'jane@example.com', ['firstName' => 'Jane']);

        Bus::assertNotDispatched(TriggerActiveCampaignAutomationJob::class);
    }

    public function test_trigger_with_email_dispatches_job_when_async_config_is_true(): void
    {
        config(['activecampaign.async' => true]);
        Bus::fake();

        $runner = app(ActiveCampaignAutomationRunner::class);
        $runner->triggerWithEmail('newsletter.signup', 'jane@example.com', ['firstName' => 'Jane'], ['source' => 'landing']);

        Bus::assertDispatched(TriggerActiveCampaignAutomationJob::class, function ($job) {
            return $job->event === 'newsletter.signup'
                && $job->userId === null
                && $job->email === 'jane@example.com'
                && $job->contactData === ['email' => 'jane@example.com', 'firstName' => 'Jane']
                && $job->context === ['source' => 'landing'];
        });
    }

    // ─── triggerWithEmailAsync() ───────────────────────────────────

    public function test_trigger_with_email_async_always_dispatches_job(): void
    {
        config(['activecampaign.async' => false]);
        Bus::fake();

        $runner = app(ActiveCampaignAutomationRunner::class);
        $runner->triggerWithEmailAsync('newsletter.signup', 'jane@example.com', ['firstName' => 'Jane']);

        Bus::assertDispatched(TriggerActiveCampaignAutomationJob::class, function ($job) {
            return $job->event === 'newsletter.signup'
                && $job->email === 'jane@example.com'
                && $job->userId === null;
        });
    }

    // ─── Job execution ─────────────────────────────────────────────

    public function test_job_executes_automation_successfully(): void
    {
        $this->fakeActiveCampaignApi();

        ActiveCampaignList::create(['ac_id' => '1', 'name' => 'Main List']);
        ActiveCampaignAutomation::create([
            'name' => 'Welcome',
            'event' => 'user.registered',
            'is_active' => true,
            'list_ac_id' => '1',
        ]);

        $job = new TriggerActiveCampaignAutomationJob(
            event: 'user.registered',
            userId: null,
            userClass: null,
            email: 'john@example.com',
            contactData: ['email' => 'john@example.com', 'firstName' => 'John'],
            context: ['plan' => 'pro'],
        );

        $job->handle(app(ActiveCampaignAutomationRunner::class));

        $this->assertDatabaseHas('activecampaign_automation_logs', [
            'event' => 'user.registered',
            'success' => true,
        ]);
    }

    public function test_job_resolves_user_from_id_when_provided(): void
    {
        $this->fakeActiveCampaignApi();

        // Create a users table and user for this test
        $this->app['db']->connection()->getSchemaBuilder()->create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        $this->app['db']->connection()->table('users')->insert([
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        config(['auth.providers.users.model' => \Illuminate\Foundation\Auth\User::class]);

        ActiveCampaignAutomation::create([
            'name' => 'Welcome',
            'event' => 'user.registered',
            'is_active' => true,
        ]);

        $job = new TriggerActiveCampaignAutomationJob(
            event: 'user.registered',
            userId: 1,
            userClass: \Illuminate\Foundation\Auth\User::class,
            email: 'john@example.com',
            contactData: ['email' => 'john@example.com', 'firstName' => 'John Doe'],
            context: [],
        );

        $job->handle(app(ActiveCampaignAutomationRunner::class));

        $this->assertDatabaseHas('activecampaign_automation_logs', [
            'event' => 'user.registered',
            'user_id' => 1,
            'success' => true,
        ]);
    }

    public function test_job_exits_gracefully_when_user_no_longer_exists(): void
    {
        // Create a users table but don't insert the user
        $this->app['db']->connection()->getSchemaBuilder()->create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        config(['auth.providers.users.model' => \Illuminate\Foundation\Auth\User::class]);

        ActiveCampaignAutomation::create([
            'name' => 'Welcome',
            'event' => 'user.registered',
            'is_active' => true,
        ]);

        $job = new TriggerActiveCampaignAutomationJob(
            event: 'user.registered',
            userId: 999,
            userClass: \Illuminate\Foundation\Auth\User::class,
            email: 'deleted@example.com',
            contactData: ['email' => 'deleted@example.com', 'firstName' => 'Deleted'],
            context: [],
        );

        // Should not throw
        $job->handle(app(ActiveCampaignAutomationRunner::class));

        // No logs should be created since we exited early
        $this->assertDatabaseMissing('activecampaign_automation_logs', [
            'event' => 'user.registered',
        ]);
    }

    public function test_job_uses_configured_tries_and_backoff(): void
    {
        config([
            'activecampaign.async_tries' => 5,
            'activecampaign.async_backoff' => [15, 60, 120],
        ]);

        $job = new TriggerActiveCampaignAutomationJob(
            event: 'test',
            userId: null,
            userClass: null,
            email: 'test@example.com',
            contactData: ['email' => 'test@example.com'],
            context: [],
        );

        $this->assertEquals(5, $job->tries);
        $this->assertEquals([15, 60, 120], $job->backoff());
    }

    public function test_job_without_user_executes_with_email_only(): void
    {
        $this->fakeActiveCampaignApi();

        ActiveCampaignAutomation::create([
            'name' => 'Newsletter',
            'event' => 'newsletter.signup',
            'is_active' => true,
        ]);

        $job = new TriggerActiveCampaignAutomationJob(
            event: 'newsletter.signup',
            userId: null,
            userClass: null,
            email: 'jane@example.com',
            contactData: ['email' => 'jane@example.com', 'firstName' => 'Jane'],
            context: ['source' => 'landing'],
        );

        $job->handle(app(ActiveCampaignAutomationRunner::class));

        $this->assertDatabaseHas('activecampaign_automation_logs', [
            'event' => 'newsletter.signup',
            'user_id' => null,
            'success' => true,
        ]);
    }

    // ─── Retrocompatibility ────────────────────────────────────────

    public function test_default_config_has_async_disabled(): void
    {
        // Reset to package defaults
        $this->assertFalse(config('activecampaign.async'));
    }

    public function test_sync_execution_still_works_with_default_config(): void
    {
        $this->fakeActiveCampaignApi();

        ActiveCampaignAutomation::create([
            'name' => 'Welcome',
            'event' => 'user.registered',
            'is_active' => true,
        ]);

        $runner = app(ActiveCampaignAutomationRunner::class);
        $runner->trigger('user.registered', $this->createMockUser(), ['plan' => 'pro']);

        $this->assertDatabaseHas('activecampaign_automation_logs', [
            'event' => 'user.registered',
            'success' => true,
        ]);
    }
}
