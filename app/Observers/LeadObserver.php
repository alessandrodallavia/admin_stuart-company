<?php

namespace App\Observers;

use App\Models\Lead;
use App\Services\AdminNotificationService;

class LeadObserver
{
    public function created(Lead $lead): void
    {
        if ($lead->is_training) {
            return;
        }

        app(AdminNotificationService::class)->notifyNewLead($lead);
    }
}
