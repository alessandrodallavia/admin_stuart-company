<section class="relative w-full">
    <div class="w-full relative h-[100svh] md:h-[600px] overflow-hidden">
        {{-- Immagine hero (LCP) responsive e ottimizzata --}}
        <link 
            rel="preload" 
            as="image"
            href="{{ asset('assets/images/landing/stuart-landing-magliette-hero-1024.webp') }}"
            imagesrcset="
                {{ asset('assets/images/landing/stuart-landing-magliette-hero-768.webp') }} 768w,
                {{ asset('assets/images/landing/stuart-landing-magliette-hero-1024.webp') }} 1024w,
                {{ asset('assets/images/landing/stuart-landing-magliette-hero-1440.webp') }} 1440w,
                {{ asset('assets/images/landing/stuart-landing-magliette-hero-1920.webp') }} 1920w
            "
            imagesizes="100vw"
            fetchpriority="high"
        />

        <img
            src="{{ asset('assets/images/landing/stuart-landing-magliette-hero-1024.webp') }}"
            srcset="
                {{ asset('assets/images/landing/stuart-landing-magliette-hero-768.webp') }} 768w,
                {{ asset('assets/images/landing/stuart-landing-magliette-hero-1024.webp') }} 1024w,
                {{ asset('assets/images/landing/stuart-landing-magliette-hero-1440.webp') }} 1440w,
                {{ asset('assets/images/landing/stuart-landing-magliette-hero-1920.webp') }} 1920w
            "
            sizes="100vw"
            alt="Foto hero"
            class="absolute inset-0 w-full h-full object-cover"
            width="1920"
            height="960"
            decoding="async"
            fetchpriority="high"
        />

        <div class="absolute inset-0 bg-black/40"></div>

        <div class="absolute left-1/2 top-1/2 w-full -translate-x-1/2 -translate-y-1/2 flex flex-col items-center text-white text-center px-12 py-2 md:px-24 lg:p-6">
            <h1 class="h1-headline">{{ $landing['hero']['h1'] }}</h1>
            <h2 class="body1-text text-center px-8">{{ $landing['hero']['h2'] }}</h2>

            <button class="button-cta bg-bullstar hover:bg-bullstar-hover mt-20" button-cta data-event-page="landing_magliette" data-event-area="hero" data-event-cta="hero_whatsapp">Ricevi il tuo mockup su Whatsapp</button>

            <span class="text-12 mt-12 font-semibold">Senza impegno • Risposta immediata</span>
            <a href="tel:+{{ config('services.whatsapp.phone') }}" class="text-12 mt-12 font-semibold text-gray-200 hover:underline cursor-pointer" button-call data-event-page="landing_magliette" data-event-area="hero" data-event-cta="hero_call">Oppure chiama al +39 345 - 8007031</a>
        </div>
    </div>
    <div class="w-full h-80 bg-bullstar text-white flex items-center justify-center px-12 md:px-2">
        <h4 class="h4-strap translate-y-[0.05em] text-center">Mockup in giornata • Preventivo subito • Spedizione veloce</h4>
    </div>
</section>
