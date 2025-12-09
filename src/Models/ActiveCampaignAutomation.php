<?php

namespace XaviCabot\FilamentActiveCampaign\Models;

use Illuminate\Database\Eloquent\Model;

class ActiveCampaignAutomation extends Model
{
    protected $table = 'activecampaign_automations';

    protected $guarded = [];

    protected $casts = [
        'is_active'  => 'boolean',
        'tag_ac_ids' => 'array',
        'fields'     => 'array',
        'system_fields' => 'array',
    ];
}
