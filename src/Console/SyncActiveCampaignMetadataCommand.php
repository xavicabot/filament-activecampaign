<?php

namespace XaviCabot\FilamentActiveCampaign\Console;

use Illuminate\Console\Command;
use XaviCabot\FilamentActiveCampaign\Services\ActiveCampaignService;

class SyncActiveCampaignMetadataCommand extends Command
{
    protected $signature = 'activecampaign:sync-metadata
        {--lists : Sync lists}
        {--tags : Sync tags}
        {--fields : Sync fields}';

    protected $description = 'Sync ActiveCampaign lists, tags and fields into local database.';

    public function handle(ActiveCampaignService $service): int
    {
        $syncLists  = $this->option('lists');
        $syncTags   = $this->option('tags');
        $syncFields = $this->option('fields');

        if (! $syncLists && ! $syncTags && ! $syncFields) {
            $syncLists  = $syncTags = $syncFields = true;
        }

        $this->info('Syncing ActiveCampaign metadata...');

        if ($syncLists) {
            $count = $service->syncLists();
            $this->info("✔ Synced lists: {$count}");
        }

        if ($syncTags) {
            $count = $service->syncTags();
            $this->info("✔ Synced tags: {$count}");
        }

        if ($syncFields) {
            $count = $service->syncFields();
            $this->info("✔ Synced fields: {$count}");
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
