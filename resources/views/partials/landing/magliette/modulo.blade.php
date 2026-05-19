<section id="modulo" class="py-100 md:py-120 lg:py-150 w-full bg-black px-24">
    <div class="max-w-7xl mx-auto flex flex-col items-center">
        <h1 class="h1-headline text-white">Vuoi vedere le tue t-shirt pronte?</h1>
        <p class="body1-text mt-24 text-white">Mandaci logo e colore. Al resto pensiamo noi.</p>

        <button class="button-cta bg-bullstar mt-36" button-cta data-event-page="landing_magliette" data-event-area="modulo" data-event-cta="modulo_whatsapp">Ricevi il tuo mockup su Whatsapp</button>

        <span class="text-12 mt-12 font-semibold text-white">Senza impegno • Risposta immediata</span>
        <a href="tel:+{{ config('services.whatsapp.phone') }}" class="text-12 mt-12 font-semibold text-gray-500 hover:underline cursor-pointer" button-call data-event-page="landing_magliette" data-event-area="modulo" data-event-cta="modulo_call">Oppure chiama al +39 345 - 8007031</a>

        <p class="body1-text text-white mt-64">Preferisci via email? Ti scriviamo noi.</p>

        <livewire:landing.modulo-magliette />
    </div>

</section>