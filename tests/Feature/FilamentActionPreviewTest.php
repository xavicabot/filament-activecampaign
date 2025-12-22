<?php

declare(strict_types=1);

namespace XaviCabot\FilamentActiveCampaign\Tests\Feature;

use XaviCabot\FilamentActiveCampaign\Models\ActiveCampaignAutomation;
use XaviCabot\FilamentActiveCampaign\Services\AutomationPreviewService;
use XaviCabot\FilamentActiveCampaign\Tests\TestCase;

class FilamentActionPreviewTest extends TestCase
{
    public function test_it_builds_a_human_readable_preview_of_the_plan(): void
    {
        $automation = new ActiveCampaignAutomation([
            'name'   => 'First Deposit Flow',
            'event'  => 'wallet.first_deposit',
            'config' => [
                'conditions' => [
                    ['type' => 'amount_greater_than', 'value' => 2000],
                ],
                'actions' => [
                    ['type' => 'add_tag', 'value' => 'EVENT_FIRST_DEPOSIT'],
                    ['type' => 'set_field', 'field' => 'FIRST_DEPOSIT_AT', 'value' => '{ctx.date}'],
                    ['type' => 'subscribe_list', 'value' => 'Main list'],
                ],
            ],
        ]);

        $service = new AutomationPreviewService();

        $preview = $service->buildPreview($automation);

        $this->assertIsArray($preview);
        $this->assertNotEmpty($preview);
        $this->assertEquals('wallet.first_deposit', $preview['event']);
        $this->assertCount(3, $preview['steps']);
    }
}
