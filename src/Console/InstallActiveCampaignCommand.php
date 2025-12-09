<?php

namespace XaviCabot\FilamentActiveCampaign\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class InstallActiveCampaignCommand extends Command
{
    protected $signature = 'activecampaign:install 
        {--force : Overwrite any existing files}';

    protected $description = 'Install the Filament ActiveCampaign plugin (config, migrations, basic setup).';

    public function handle(): int
    {
        $this->info('Installing Filament ActiveCampaign plugin...');

        // Publicar config
        $this->callSilent('vendor:publish', [
            '--tag'   => 'activecampaign-config',
            '--force' => $this->option('force'),
        ]);
        $this->info('✔ Config file published: config/activecampaign.php');

        // Publicar migraciones
        $this->callSilent('vendor:publish', [
            '--tag'   => 'activecampaign-migrations',
            '--force' => $this->option('force'),
        ]);
        $this->info('✔ Migrations published to database/migrations');

        // Sugerir registro del plugin en Filament
        $this->line('');
        $this->info('Next steps:');
        $this->line('  1) Add the plugin to your Filament panel provider:');
        $this->line('     use XaviCabot\\FilamentActiveCampaign\\FilamentActiveCampaignPlugin;');
        $this->line('');
        $this->line('     public function panel(Panel $panel): Panel');
        $this->line('     {');
        $this->line('         return $panel');
        $this->line('             // ...');
        $this->line('             ->plugins([');
        $this->line('                 FilamentActiveCampaignPlugin::make(),');
        $this->line('             ]);');
        $this->line('     }');
        $this->line('');
        $this->info('  2) Set ACTIVECAMPAIGN_BASE_URL and ACTIVECAMPAIGN_API_KEY in your .env');
        $this->line('');
        $this->info('Done.');

        return self::SUCCESS;
    }
}
