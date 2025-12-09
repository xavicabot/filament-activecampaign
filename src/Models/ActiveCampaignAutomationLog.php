<?php

namespace XaviCabot\FilamentActiveCampaign\Models;

use Illuminate\Database\Eloquent\Model;

class ActiveCampaignAutomationLog extends Model
{
    protected $table = 'activecampaign_automation_logs';

    protected $guarded = [];

    protected $casts = [
        'success' => 'boolean',
        'context' => 'array',
        'payload' => 'array',
    ];

    public function automation()
    {
        return $this->belongsTo(ActiveCampaignAutomation::class, 'automation_id');
    }
}
