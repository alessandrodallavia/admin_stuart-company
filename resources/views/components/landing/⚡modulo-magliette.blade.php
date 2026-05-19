<?php

use Livewire\Component;
use Illuminate\Support\Facades\Log;
use App\Models\Lead;
use App\Services\BrevoEmailService;

new class extends Component
{
    public $name;
    public $email;
    public $phone;
    public $club;
    public $city;
    public $message;
    public $privacy = false;
    public $marketing = false;
    public $website;

    protected $rules = [
        'name' => 'nullable|string|min:2|max:100',
        'email' => 'required|email:rfc,dns|max:255',
        'phone' => 'required|string|min:6|max:30',
        'club' => 'required|string|min:2|max:150',
        'city' => 'required|string|min:2|max:150',
        'message' => 'required|string|min:10|max:2000',
        'privacy' => 'accepted',
    ];

    protected $messages = [
        'name.required' => 'Inserisci il tuo nome',
        'name.min' => 'Il nome deve avere almeno 2 caratteri',

        'email.required' => 'Inserisci la tua email',
        'email.email' => 'Inserisci una email valida',

        'phone.required' => 'Inserisci il telefono',
        'phone.min' => 'Inserisci un telefono valido',

        'club.required' => 'Inserisci il nome del club o della squadra',

        'city.required' => 'Inserisci la città',

        'message.required' => 'Inserisci il messaggio',
        'message.min' => 'Il messaggio deve avere almeno 10 caratteri',

        'privacy.accepted' => 'Devi accettare l’informativa privacy',
    ];

    public function mount()
    {
        if (!app()->runningInConsole()) {

            if (!session()->has('landing_page')) {
                session(['landing_page' => url()->current()]);
            }

            if (!session()->has('entry_page')) {
                session(['entry_page' => url()->current()]);
            }
        }

        if(request()->has('utm_source')) {
            session([
                'utm_source' => request('utm_source'),
                'utm_medium' => request('utm_medium'),
                'utm_campaign' => request('utm_campaign'),
                'utm_term' => request('utm_term'),
                'utm_content' => request('utm_content'),
            ]);
        }

        if(request()->has('gclid')) {
            session(['gclid' => request('gclid')]);
        }

        if(request()->has('fbclid')) {
            session(['fbclid' => request('fbclid')]);
        }
    }

    public function updated($property)
    {
        $this->validateOnly($property);
    }

    public function sendEmail(BrevoEmailService $emailService)
    {
        $validated = $this->validate();

        if (!empty($this->website)) {
            return;
        }

        $lead = Lead::create([
            'status' => 'confirmed',
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'club' => $this->club,
            'city' => $this->city,
            'message' => $this->message,

            'privacy_consent' => $this->privacy,
            'marketing_consent' => $this->marketing,

            'utm_source' => session('utm_source'),
            'utm_medium' => session('utm_medium'),
            'utm_campaign' => session('utm_campaign'),
            'utm_term' => session('utm_term'),
            'utm_content' => session('utm_content'),

            'gclid' => session('gclid'),
            'fbclid' => session('fbclid'),

            'landing_page' => session('landing_page'),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'device' => str_contains(strtolower(request()->userAgent()), 'mobile') ? 'mobile' : 'desktop',
            'referrer' => request()->headers->get('referer'),
            'entry_page' => session('entry_page'),
        ]);

        $listIds = $this->marketing ? [2] : [];

        $response = $emailService->createContact([
            'email' => $this->email,
            'attributes' => [
                'firstname' => $this->name,
                'phone' => $this->phone,
                'club' => $this->club,
                'city' => $this->city,

                'utm_source' => session('utm_source'),
                'utm_medium' => session('utm_medium'),
                'utm_campaign' => session('utm_campaign'),
                'utm_term' => session('utm_term'),

                'gclid' => session('gclid'),
                'landing_page' => session('landing_page'),
            ],
            'listIds' => $listIds,
            'updateEnabled' => true,
            'emailBlacklisted' => empty($listIds)
        ]);

        if ($response && method_exists($response, 'getId')) {

            $contactId = $response->getId();

            $pipelineId = config('services.brevo.pipeline_landing', '668657f531a3338b033a8e99');
            $stageId = config('services.brevo.lead_stages.confirmed', '1bfe1c91-f4a0-4a2e-8961-d74c7eba68f3');

            $attributes = array_filter([
                'phone' => $this->phone,
                'city' => $this->city,
                'club' => $this->club,
                'message' => $this->message,
                'amount' => $lead->payment_amount ?: $lead->quote_amount,
                'payment_link' => $lead->payment_link,

                'landing_page' => session('landing_page'),

                'utm_source' => session('utm_source'),
                'utm_medium' => session('utm_medium'),
                'utm_campaign' => session('utm_campaign'),
                'utm_term' => session('utm_term'),
                'utm_content' => session('utm_content'),

                'gclid' => session('gclid'),
                'fbclid' => session('fbclid'),

                'device' => str_contains(strtolower(request()->userAgent()), 'mobile') ? 'mobile' : 'desktop',
            ], fn ($value) => $value !== null && $value !== '');

            Log::info('attributes', [$attributes]);

            $responseDeal = $emailService->createDeal([
                'name' => trim($this->club . ' - ' . ($this->name ?: $this->phone ?: $this->email), ' -'),
                'pipelineId' => $pipelineId,
                'dealStageId' => $stageId,
                'linkedContactsIds' => [$contactId],
                'dealAttributes' => $attributes
            ]);


            Log::info('responseDeal', [$responseDeal]);

            if (! empty($responseDeal['id'])) {
                $lead->forceFill([
                    'pipeline_lead_id' => $responseDeal['id'],
                ])->save();
            }

        }
        
        $this->dispatch('lead-submitted');
        session()->flash('success', true);
        // dd($response);

        // Invio mail di conferma
        // try {
        //     $response = $emailService->sendEmail(
        //         [
        //             'name' => $this->name,
        //             'email' => $this->email
        //         ],
        //         ['lead_divise'],
        //         [
        //             'nome' => $this->name,
        //             'email' => $this->email,
        //             'telefono' => $this->phone,
        //             'club' => $this->club,
        //             'citta' => $this->city,
        //             'messaggio' => $this->message,
        //         ]
        //     );

        //     session()->flash('success', true);
        //     $this->reset();
        // } catch (Exception $e) {
        //     Log::error('Error sending email', ['message' => $e->getMessage()]);
        //     // return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        // }
    }
}
?>

<div>
    <form wire:submit="sendEmail" class="mt-24 w-full max-w-3xl">
        <input
            type="text"
            wire:model="website"
            class="hidden"
            tabindex="-1"
            autocomplete="off"
        />

        <div class="mt-12 w-full flex gap-x-12">

            <div class="w-full">
                <div class="relative">

                    <input 
                        type="text"
                        placeholder=" "
                        wire:model.blur="email"
                        @class([
                            'peer w-full h-64 rounded-2xl border bg-transparent text-white px-8 pt-5 pb-2 focus:outline-none',
                            'border-white' => !$errors->has('email'),
                            'border-red-500' => $errors->has('email'),
                        ])
                    >

                    <label 
                        @class([
                            'absolute left-8 top-1/2 -translate-y-1/2 transition-all duration-200 pointer-events-none
                            peer-focus:top-2 peer-focus:text-xs peer-focus:translate-y-0
                            peer-[&:not(:placeholder-shown)]:top-2 peer-[&:not(:placeholder-shown)]:text-xs peer-[&:not(:placeholder-shown)]:translate-y-0',
                            'text-white' => !$errors->has('email'),
                            'text-red-500' => $errors->has('email'),
                        ])
                    >
                        Inserisci la tua email
                    </label>

                </div>
                @error('email')
                    <span class="text-red-500 text-sm mt-2 block">{{ $message }}</span>
                @enderror
            </div>

        </div>

        <div class="mt-24 flex items-start gap-x-12">

            <label class="flex items-start gap-x-12 cursor-pointer">

                <input 
                    type="checkbox"
                    wire:model="privacy"
                    class="peer hidden"
                >

                <div class="w-16 h-16 rounded-full border border-white hover:bg-gray-400 flex items-center justify-center transition peer-checked:bg-white">
                    <svg class="w-16 h-16 text-black opacity-0 peer-checked:opacity-100 transition" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-7.071 7.071a1 1 0 01-1.414 0L3.293 8.85a1 1 0 111.414-1.414l4.222 4.222 6.364-6.364a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                </div>

                <span class="text-white text-14 font-medium leading-none">
                    Ho letto l’<a href="{{ route('privacy-policy') }}" target="_blank" class="text-white underline">informativa privacy</a> e acconsento al trattamento dei dati.
                </span>

            </label>

        </div>

        @error('privacy')
        <span class="text-red-500 text-12 mt-8 block">{{ $message }}</span>
        @enderror

        <div class="mt-12 flex items-start gap-x-12">

            <label class="flex items-start gap-x-12 cursor-pointer">

                <input 
                    type="checkbox"
                    wire:model="marketing"
                    class="peer hidden"
                >

                <div class="w-16 h-16 rounded-full border border-white hover:bg-gray-400 flex items-center justify-center transition peer-checked:bg-white">
                    <svg class="w-16 h-16 text-black opacity-0 peer-checked:opacity-100 transition" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-7.071 7.071a1 1 0 01-1.414 0L3.293 8.85a1 1 0 111.414-1.414l4.222 4.222 6.364-6.364a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                </div>

                <span class="text-white text-14 font-medium leading-none">
                    Voglio ricevere aggiornamenti e offerte (facoltativo).
                </span>

            </label>

        </div>

        <button class="button-cta bg-transparent border border-2 border-bullstar hover:border-white hover:bg-white text-white hover:text-black mx-auto mt-48" type="submit">Ti scriviamo noi</button>
        <span class="text-12 mt-12 font-semibold text-white block text-center">Ti scriviamo subito via email</span>

    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {

            let formStarted = false;

            const form = document.querySelector('form[wire\\:submit="sendEmail"]');

            if(!form) return;

            form.querySelectorAll('input, textarea').forEach(input => {

                input.addEventListener('focus', () => {

                    if(formStarted) return;
                    formStarted = true;

                    if(typeof gtag !== 'undefined') {
                        gtag('event', 'form_start', {
                            form_name: 'preventivo',
                            location: 'form_section'
                        });
                    }

                    if(typeof fbq !== 'undefined') {
                        fbq('trackCustom', 'FormStart');
                    }

                });

            });

        });
    </script>

    <script>
        document.addEventListener('livewire:init', () => {

            Livewire.on('lead-submitted', () => {

                console.log("gtag: " + gtag);

                if (typeof gtag !== 'undefined') {
                    gtag('event', 'generate_lead', {
                        form_name: 'preventivo_divise',
                        location: 'form_section'
                    });
                }
    
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'conversion', {
                        'send_to': 'AW-XXXXXXXXX/XXXXXXXX'
                    });
                }

                if (typeof fbq !== 'undefined') {
                    fbq('track', 'Lead');
                }

            });

        });
    </script>
</div>
