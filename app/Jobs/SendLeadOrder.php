<?php

namespace App\Jobs;

use App\Models\EmailAccount;
use App\Models\EmailConversation;
use App\Models\LeadOrderDispatch;
use App\Services\EmailMailboxService;
use App\Services\LeadOrderPackageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class SendLeadOrder implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(
        public int $dispatchId,
        public int $emailAccountId,
    ) {
        $this->onQueue('admin');
    }

    public function handle(LeadOrderPackageService $packages, EmailMailboxService $mailbox): void
    {
        $dispatch = LeadOrderDispatch::query()->with('sheet.lead')->findOrFail($this->dispatchId);
        $sheet = $dispatch->sheet->load(['lead', 'items.prints', 'items.attachments']);
        $lead = $sheet->lead;
        $account = EmailAccount::query()->findOrFail($this->emailAccountId);
        $package = null;

        try {
            $package = $packages->create($sheet, $dispatch);

            if ($package['size'] > ((int) config('lead_orders.max_attachment_kb') * 1024)) {
                throw new RuntimeException('Lo ZIP supera il limite di 10 MB. Riduci i file grafici e riprova.');
            }

            $conversation = EmailConversation::create([
                'email_account_id' => $account->id,
                'lead_id' => $lead->id,
                'assigned_user_id' => $dispatch->admin_user_id,
                'subject' => 'Ordine Lead — '.$dispatch->order_name.' (v'.$dispatch->version.')',
                'contact_email' => $dispatch->to_email,
                'contact_name' => config('lead_orders.to.name'),
                'status' => 'open',
                'is_seen' => true,
                'last_message_at' => now(),
            ]);
            $html = view('lead-orders.email', compact('lead', 'sheet', 'dispatch'))->render();
            $message = $mailbox->send(
                $account,
                $conversation,
                "Ordine Lead: {$dispatch->order_name}\nIn allegato trovi lo ZIP completo dell'ordine.",
                [],
                [config('lead_orders.cc.email')],
                [],
                $html,
                [$package],
            );

            $dispatch->update([
                'email_message_id' => $message->id,
                'status' => $message->status,
                'error_message' => $message->error_message,
                'sent_at' => $message->sent_at,
            ]);

            if ($message->status !== 'sent') {
                throw new RuntimeException($message->error_message ?: 'Invio email non riuscito.');
            }
        } finally {
            if ($package) {
                Storage::disk($package['disk'])->delete($package['path']);
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        LeadOrderDispatch::query()->whereKey($this->dispatchId)->update([
            'status' => 'failed',
            'error_message' => $exception?->getMessage() ?: 'Invio non riuscito.',
        ]);
    }
}
