<?php

namespace App\Observers;

use App\Models\Lead;
use App\Services\AdminNotificationService;

class LeadObserver
{
    public function created(Lead $lead): void
    {
        app(AdminNotificationService::class)->notifyNewLead($lead);
    }
}
