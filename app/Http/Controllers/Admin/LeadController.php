<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\WhatsappConversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function index(Request $request, ?Lead $lead = null): View
    {
        $status = $request->string('status')->toString();
        $search = trim($request->string('q')->toString());

        $leadsQuery = Lead::query()
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('club', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            })
            ->latest();

        $leads = $leadsQuery->paginate(14)->withQueryString();

        $selectedLead = $lead
            ? $lead->fresh()
            : $leads->first();

        $selectedConversation = $selectedLead
            ? WhatsappConversation::query()
                ->where(function ($query) use ($selectedLead) {
                    $query
                        ->where('lead_id', $selectedLead->id)
                        ->orWhere('id', $selectedLead->whatsapp_conversation_id);
                })
                ->latest('last_message_at')
                ->first()
            : null;

        $statuses = $this->statuses();

        return view('admin.leads.index', [
            'leads' => $leads,
            'selectedLead' => $selectedLead,
            'selectedConversation' => $selectedConversation,
            'statuses' => $statuses,
            'stats' => $this->stats($statuses),
            'currentStatus' => $status,
            'search' => $search,
        ]);
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $statuses = array_keys($this->statuses());

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in($statuses)],
            'quote_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'payment_link' => ['nullable', 'url', 'max:2048'],
            'payment_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        if ($data['status'] === 'quote_sent' && empty($data['quote_amount'])) {
            return back()
                ->withErrors(['quote_amount' => 'Inserisci l’importo del preventivo prima di segnare il lead come preventivo inviato.'])
                ->withInput();
        }

        if ($data['status'] === 'link_sent' && (empty($data['payment_link']) || empty($data['payment_amount']))) {
            return back()
                ->withErrors(['payment_link' => 'Inserisci link e importo pagamento prima di segnare il lead come link inviato.'])
                ->withInput();
        }

        $lead->fill([
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'],
            'quote_amount' => $data['quote_amount'] ?? $lead->quote_amount,
            'payment_link' => $data['payment_link'] ?? $lead->payment_link,
            'payment_amount' => $data['payment_amount'] ?? $lead->payment_amount,
        ])->save();

        return redirect()
            ->route('admin.leads.index', ['lead' => $lead])
            ->with('status', 'Lead aggiornato. Brevo riceverà il cambio pipeline se il deal è collegato.');
    }

    private function stats(array $statuses): array
    {
        $counts = Lead::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total' => Lead::count(),
            'open' => Lead::whereNotIn('status', ['order_completed'])->count(),
            'with_brevo' => Lead::whereNotNull('pipeline_lead_id')->count(),
            'paid' => (int) ($counts['order_completed'] ?? 0),
            'by_status' => collect($statuses)
                ->mapWithKeys(fn ($label, $key) => [$key => (int) ($counts[$key] ?? 0)])
                ->all(),
        ];
    }

    private function statuses(): array
    {
        return [
            'pre' => 'Pre lead',
            'confirmed' => 'Confermato',
            'completed' => 'Da lavorare',
            'quote_sent' => 'Preventivo inviato',
            'link_sent' => 'Link pagamento inviato',
            'order_completed' => 'Ordine completato',
        ];
    }
}
