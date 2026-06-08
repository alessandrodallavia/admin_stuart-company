<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailAccount;
use App\Models\EmailAttachment;
use App\Models\EmailConversation;
use App\Models\Lead;
use App\Services\EmailMailboxService;
use App\Services\EmailMailboxSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class EmailController extends Controller
{
    public function index(Request $request, EmailMailboxSyncService $sync, ?EmailConversation $conversation = null): View
    {
        $adminUser = $request->user('admin');
        $account = EmailAccount::query()
            ->where('admin_user_id', $adminUser->id)
            ->latest('is_active')
            ->latest('id')
            ->first();

        $conversations = $account
            ? EmailConversation::query()
                ->where('email_account_id', $account->id)
                ->with(['latestMessage', 'lead'])
                ->latest('last_message_at')
                ->latest('id')
                ->paginate(18)
                ->withQueryString()
            : collect();

        $selectedConversation = null;

        if ($account && $conversation?->email_account_id === $account->id) {
            try {
                $sync->markConversationSeen($conversation);
            } catch (\Throwable) {
            }

            $selectedConversation = $conversation->load(['messages.attachments', 'lead']);
        } elseif ($account && method_exists($conversations, 'first')) {
            $selectedConversation = $conversations->first()?->load(['messages.attachments', 'lead']);
        }

        return view('admin.email.index', [
            'account' => $account,
            'conversations' => $conversations,
            'selectedConversation' => $selectedConversation,
            'leads' => Lead::query()
                ->whereNotNull('email')
                ->latest()
                ->limit(50)
                ->get(['id', 'name', 'email', 'club']),
        ]);
    }

    public function sync(Request $request, EmailMailboxSyncService $sync): RedirectResponse
    {
        $account = $this->accountFor($request);

        try {
            $imported = $sync->sync($account);

            return back()->with('status', "Sincronizzazione completata: {$imported} nuove email.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Sincronizzazione email fallita: '.$e->getMessage());
        }
    }

    public function storeConversation(Request $request, EmailMailboxService $mailbox): RedirectResponse
    {
        $account = $this->accountFor($request);
        $data = $request->validate([
            'to_email' => ['required', 'email:rfc', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'cc' => ['nullable', 'email:rfc', 'max:255'],
            'bcc' => ['nullable', 'email:rfc', 'max:255'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $conversation = EmailConversation::create([
            'email_account_id' => $account->id,
            'lead_id' => $data['lead_id'] ?? null,
            'assigned_user_id' => $request->user('admin')->id,
            'subject' => $data['subject'],
            'contact_email' => $data['to_email'],
            'contact_name' => $data['contact_name'] ?? null,
            'status' => 'open',
            'is_seen' => true,
            'last_message_at' => now(),
        ]);

        $message = $mailbox->send(
            $account,
            $conversation,
            $data['body'],
            $request->file('attachments', []),
            array_filter([$data['cc'] ?? null]),
            array_filter([$data['bcc'] ?? null]),
        );

        return redirect()
            ->route('admin.email.conversations.show', ['conversation' => $conversation])
            ->with($message->status === 'sent' ? 'status' : 'error', $message->status === 'sent' ? 'Email inviata.' : 'Invio email fallito: '.$message->error_message);
    }

    public function sendMessage(Request $request, EmailConversation $conversation, EmailMailboxService $mailbox): RedirectResponse
    {
        $account = $this->accountFor($request);
        abort_unless($conversation->email_account_id === $account->id, 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $message = $mailbox->send($account, $conversation, $data['body'], $request->file('attachments', []));

        return back()->with($message->status === 'sent' ? 'status' : 'error', $message->status === 'sent' ? 'Email inviata.' : 'Invio email fallito: '.$message->error_message);
    }

    public function downloadAttachment(Request $request, EmailAttachment $attachment)
    {
        $account = $this->accountFor($request);
        $attachment->load('message.conversation');

        abort_unless($attachment->message?->conversation?->email_account_id === $account->id, 404);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->filename);
    }

    private function accountFor(Request $request): EmailAccount
    {
        return EmailAccount::query()
            ->where('admin_user_id', $request->user('admin')->id)
            ->where('is_active', true)
            ->latest('id')
            ->firstOrFail();
    }
}
