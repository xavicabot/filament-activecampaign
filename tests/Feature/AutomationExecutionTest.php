<?php

declare(strict_types=1);

namespace XaviCabot\FilamentActiveCampaign\Tests\Feature;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use XaviCabot\FilamentActiveCampaign\Jobs\RunActiveCampaignAutomationsJob;
use XaviCabot\FilamentActiveCampaign\Tests\TestCase;

class AutomationExecutionTest extends TestCase
{
    use WithFaker;

    public function test_it_dispatches_job_when_a_matching_event_is_fired(): void
    {
        Bus::fake();
        Event::fake();

        $user = new class {
            public int $id = 1;
            public string $email = 'john@example.com';
            public string $name = 'John Doe';
        };

        event('wallet.first_deposit', [
            'user' => $user,
            'ctx'  => ['amount' => 20000, 'currency' => 'EUR'],
        ]);

        Bus::assertDispatched(RunActiveCampaignAutomationsJob::class, function (RunActiveCampaignAutomationsJob $job) use ($user) {
            return $job->eventName === 'wallet.first_deposit'
                && $job->userId === $user->id;
        });
    }

    public function test_job_writes_log_entry_with_payload_and_context(): void
    {
        Bus::fake();

        $user = new class {
            public int $id = 1;
            public string $email = 'john@example.com';
            public string $name = 'John Doe';
        };

        $payload = [
            'user' => [
                'id'    => $user->id,
                'email' => $user->email,
                'name'  => $user->name,
            ],
            'ctx' => ['amount' => 4000, 'currency' => 'EUR'],
        ];

        $job = new RunActiveCampaignAutomationsJob(
            eventName: 'wallet.first_deposit',
            userId: $user->id,
            payload: $payload,
        );

        $job->handle();

        $this->assertDatabaseHas('activecampaign_automation_logs', [
            'event_name' => 'wallet.first_deposit',
            'user_id'    => 1,
        ]);
    }
}
