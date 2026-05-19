<section id="faq" aria-labelledby="faq-title" class="w-full">
    <div class="mx-auto max-w-5xl py-48 md:py-120 lg:py-150 px-12 md:px-48 mt-64">
        <h1 id="faq-title" class="h1-headline">Domande frequenti</h1>

        <div class="bg-white rounded-2xl divide-y divide-gray-200 py-48">
            @php
                $faqs = [
                    [
                        'q' => 'In quanto tempo ricevo il mockup?',
                        'a' => "Ricevi il mockup in giornata. Appena ci invii logo e dettagli, prepariamo subito l'anteprima con anche il preventivo incluso.",
                    ],
                    [
                        'q' => "Posso ordinare anche poche t-shirt?",
                        'a' => 'Si, puoi ordinare anche poche quantità. Ti indichiamo sempre la soluzione migliore in base al numero di pezzi e alla grafica.',
                    ],
                    [
                        'q' => 'Come funziona il processo?',
                        'a' => 'È molto semplice:
                        <ul class="my-4">
                            <li>1. ci invii logo e dettagli;</li>
                            <li>2. ti mostriamo il mockup con preventivo;</li>
                            <li>3. se ti piace confermi e partiamo.</li>
                        </ul>Senza impegno.',
                    ],
                    [
                        'q' => 'In quanto tempo ricevo le t-shirt?',
                        'a' => "Dipende dalla lavorazione:
                        <ul class='my-4'>
                            <li>DTF: circa 5 giorni lavorativi;</li>
                            <li>Serigrafia/Ricamo: circa 10/15 giorni;</li>
                            <li>Sublimazione: 30 giorni</li>
                        </ul>Ti indichiamo sempre i tempi precisi prima di partire.",
                    ],
                    [
                        'q' => 'Quali metodo di pagamento accettate?',
                        'a' => "Accettiamo bonifico bancario, carta di credito e PayPal. Tutti i dettagli ti vengono forniti insieme al preventivo.",
                    ],
                    [
                        'q' => 'Il mockup è davvero gratuito?',
                        'a' => "Si. Ti mostriamo l'anteprima senza impegno. Decidi tu se procedere dopo aver visto il risultato.",
                    ],
                ];
            @endphp

            @foreach ($faqs as $faq)
                <details class="group py-24 px-12 md:px-24 cursor-pointer select-none transition-all duration-300 ease-in-out">
                    <summary class="flex items-center justify-between gap-16">
                        <p class="body1-text text-left">{{ $faq['q'] }}</p>
                        <svg class="faq-chevron h-24 w-24 text-gray-500 transition-transform duration-300 ease-in-out shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/>
                        </svg>
                    </summary>
                    <div class="mt-16 text-gray-600 pr-0 group-open:animate-fadeIn">
                        <p>{!! $faq['a'] !!}</p>
                    </div>
                </details>
            @endforeach
        </div>
    </div>
</section>

<style>
    details summary::-webkit-details-marker { display: none; }
    details[open] .faq-chevron { transform: rotate(180deg); }

    /* Animazione dolce in apertura */
    @keyframes fadeIn {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
    }
    .animate-fadeIn { animation: fadeIn 0.3s ease-in-out; }

    /* Effetto fade-in della sezione */
    .fade-in { opacity: 0; transform: translateY(20px); transition: all 0.6s ease-out; }
    .fade-in.visible { opacity: 1; transform: none; }
    </style>

    <script defer>
    document.addEventListener("DOMContentLoaded", () => {
    // Fade-in della sezione FAQ
    const faqSection = document.querySelector('#faq');
    const observer = new IntersectionObserver(entries => {
        if (entries[0].isIntersecting) faqSection.classList.add('visible');
    }, { threshold: 0.2 });
    faqSection.classList.add('fade-in');
    observer.observe(faqSection);
    });
</script>