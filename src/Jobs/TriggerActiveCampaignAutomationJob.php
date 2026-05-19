<?php

namespace XaviCabot\FilamentActiveCampaign\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use XaviCabot\FilamentActiveCampaign\Services\ActiveCampaignAutomationRunner;

class TriggerActiveCampaignAutomationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public function __construct(
        public readonly string $event,
        public readonly ?int $userId,
        public readonly ?string $userClass,
        public readonly string $email,
        public readonly array $contactData,
        public readonly array $context = [],
    ) {
        $this->tries = (int) config('activecampaign.async_tries', 3);
        $this->onQueue(config('activecampaign.queue', 'default'));
    }

    public function backoff(): array
    {
        return config('activecampaign.async_backoff', [10, 60]);
    }

    public function handle(ActiveCampaignAutomationRunner $runner): void
    {
        $user = null;

        if ($this->userId !== null && $this->userClass !== null) {
            $user = $this->userClass::find($this->userId);

            if ($user === null) {
                return;
            }
        }

        $runner->runForEventGeneric($this->event, $user, $this->contactData, $this->context);
    }
}
