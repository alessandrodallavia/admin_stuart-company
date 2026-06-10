<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailConversation;
use App\Models\EmailMessage;
use App\Models\Lead;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TrainingController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user('admin')->training_mode_enabled, 403);

        return view('admin.training.index', [
            'trainingLeadsCount' => Lead::withoutGlobalScope('training')
                ->where('is_training', true)
                ->where('training_owner_id', $request->user('admin')->id)
                ->count(),
            'trainingLeads' => Lead::query()
                ->when(! $request->user('admin')->training_mode_active, fn ($query) => $query->whereRaw('1 = 0'))
                ->with(['whatsappConversation', 'emailConversations'])
                ->latest()
                ->get(),
            'isTrainingActive' => $request->user('admin')->training_mode_active,
            'whatsappPhone' => '+'.ltrim((string) config('services.whatsapp.phone_api'), '+'),
        ]);
    }

    public function toggle(Request $request): RedirectResponse
    {
        $user = $request->user('admin');
        abort_unless($user->training_mode_enabled, 403);
        $wasActive = $user->training_mode_active;

        $user->forceFill([
            'training_mode_active' => ! $wasActive,
        ])->save();

        if ($wasActive) {
            $this->deleteTrainingData($user->id);
        }

        return redirect()
            ->route($user->training_mode_active ? 'admin.training.index' : 'admin.dashboard')
            ->with('status', $user->training_mode_active
                ? 'Modalità formazione attivata. Nessun messaggio verrà inviato realmente.'
                : 'Modalità formazione disattivata. Dati formativi e ID richiesta sono stati eliminati.');
    }

    public function createScenario(Request $request): RedirectResponse
    {
        $user = $request->user('admin');
        abort_unless($user->training_mode_enabled && $user->training_mode_active, 403);

        $data = $request->validate([
            'scenario' => ['required', 'in:whatsapp,email'],
            'contact_email' => ['nullable', 'required_if:scenario,email', 'email:rfc', 'max:255'],
        ]);
        $scenario = $data['scenario'];

        if ($scenario === 'email' && ! $user->emailAccount) {
            return back()->with('error', 'Configura prima la casella email dell’operatore per usare questo scenario.');
        }

        $leadData = [
            'uuid' => strtoupper(Str::random(6)),
            'status' => 'confirmed',
            'name' => null,
            'email' => null,
            'phone' => null,
            'club' => null,
            'city' => null,
            'message' => 'Vorrei realizzare 50 magliette personalizzate per un evento.',
            'privacy_consent' => true,
            'utm_source' => 'formazione',
            'utm_medium' => 'scenario',
            'utm_campaign' => 'percorso_operatore',
            'is_training' => true,
            'training_owner_id' => $user->id,
            'training_scenario' => $scenario,
        ];

        if ($scenario === 'email') {
            $leadData = [
                ...$leadData,
                'email' => $data['contact_email'],
            ];
        }

        $lead = Lead::withoutGlobalScope('training')->create($leadData);

        if ($scenario === 'email') {
            $account = $user->emailAccount;

            if ($account) {
                $conversation = EmailConversation::withoutGlobalScope('training')->create([
                    'email_account_id' => $account->id,
                    'lead_id' => $lead->id,
                    'assigned_user_id' => $user->id,
                    'subject' => 'Richiesta magliette evento primavera',
                    'contact_email' => $lead->email,
                    'contact_name' => $lead->name,
                    'status' => 'open',
                    'is_seen' => false,
                    'last_message_at' => now(),
                    'is_training' => true,
                    'training_owner_id' => $user->id,
                    'training_scenario' => $scenario,
                ]);

                EmailMessage::create([
                    'email_conversation_id' => $conversation->id,
                    'message_id' => 'training-'.Str::uuid(),
                    'direction' => 'inbound',
                    'status' => 'received',
                    'from_email' => $lead->email,
                    'from_name' => $lead->name,
                    'to' => [$account->email],
                    'subject' => $conversation->subject,
                    'body_text' => "Buongiorno,\nvorrei ricevere una proposta per 50 magliette personalizzate per il nostro evento di primavera.\n\nGrazie",
                    'received_at' => now(),
                ]);
            }
        }

        return redirect()
            ->route($scenario === 'whatsapp' ? 'admin.training.index' : 'admin.leads.index', $scenario === 'whatsapp' ? [] : ['lead' => $lead])
            ->with('status', $scenario === 'whatsapp'
                ? 'Lead WhatsApp formativo creato. Invia ora un messaggio reale usando il riferimento indicato.'
                : 'Scenario email formativo creato. Le comunicazioni restano nel pannello.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $user = $request->user('admin');
        abort_unless($user->training_mode_enabled, 403);

        $this->deleteTrainingData($user->id);

        return redirect()
            ->route('admin.training.index')
            ->with('status', 'Dati formativi eliminati.');
    }

    private function deleteTrainingData(int $ownerId): void
    {
        EmailConversation::withoutGlobalScope('training')
            ->where('is_training', true)
            ->where('training_owner_id', $ownerId)
            ->delete();

        WhatsappConversation::withoutGlobalScope('training')
            ->where('is_training', true)
            ->where('training_owner_id', $ownerId)
            ->delete();

        Lead::withoutGlobalScope('training')
            ->where('is_training', true)
            ->where('training_owner_id', $ownerId)
            ->delete();
    }

    public function customerReply(Request $request, Lead $lead): RedirectResponse
    {
        abort_unless($lead->is_training && $lead->training_owner_id === $request->user('admin')->id, 404);

        $data = $request->validate([
            'channel' => ['required', 'in:whatsapp,email'],
            'reply' => ['required', 'in:interested,quote_change,bank_transfer,thanks'],
        ]);

        $body = match ($data['reply']) {
            'interested' => 'Perfetto, mi interessa. Come possiamo procedere?',
            'quote_change' => 'Grazie per il preventivo. Possiamo modificare quantità e colore?',
            'bank_transfer' => 'Preferisco pagare tramite bonifico bancario. Potete inviarmi la proforma?',
            'thanks' => 'Grazie, è tutto chiaro. Attendo i prossimi aggiornamenti.',
        };

        if ($data['channel'] === 'whatsapp') {
            $conversation = $lead->whatsappConversation;
            abort_unless($conversation, 404);

            WhatsappMessage::create([
                'whatsapp_conversation_id' => $conversation->id,
                'provider_message_id' => 'training-'.Str::uuid(),
                'direction' => 'inbound',
                'source' => 'training',
                'type' => 'text',
                'status' => 'received',
                'from_phone' => $lead->phone,
                'to_phone' => config('services.whatsapp.phone_number_id'),
                'body' => $body,
                'received_at' => now(),
            ]);

            $conversation->forceFill([
                'needs_human' => true,
                'last_message_at' => now(),
            ])->save();

            return redirect()->route('admin.conversations.show', $conversation)
                ->with('status', 'Risposta cliente simulata ricevuta.');
        }

        $conversation = $lead->emailConversations()->latest()->firstOrFail();

        EmailMessage::create([
            'email_conversation_id' => $conversation->id,
            'message_id' => 'training-'.Str::uuid(),
            'direction' => 'inbound',
            'status' => 'received',
            'from_email' => $lead->email,
            'from_name' => $lead->name,
            'to' => [$conversation->account->email],
            'subject' => 'Re: '.$conversation->subject,
            'body_text' => $body,
            'received_at' => now(),
        ]);

        $conversation->forceFill([
            'is_seen' => false,
            'last_message_at' => now(),
        ])->save();

        return redirect()->route('admin.email.conversations.show', $conversation)
            ->with('status', 'Risposta cliente simulata ricevuta.');
    }

    public function completePayment(Request $request, Lead $lead): RedirectResponse
    {
        abort_unless(
            $request->user('admin')->training_mode_active
            && $lead->is_training
            && $lead->training_owner_id === $request->user('admin')->id,
            404
        );

        $lead->forceFill([
            'status' => 'order_completed',
            'payment_amount' => $lead->payment_amount ?: $lead->quote_amount ?: 250,
        ])->save();

        return redirect()
            ->route('admin.leads.index', ['lead' => $lead])
            ->with('status', 'Pagamento simulato completato. Nessun webhook o evento esterno è partito.');
    }
}
