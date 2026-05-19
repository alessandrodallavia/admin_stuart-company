<style>
    .swiper-button-prev4,
    .swiper-button-next4 {
        cursor: pointer;
    }
    .swiper-button-prev4.swiper-button-disabled,
    .swiper-button-next4.swiper-button-disabled {
        opacity: 0.35;
        cursor: auto;
        pointer-events: none;
    }
</style>

<section class="w-full py-100 md:py-120 lg:py-150 px-12 md:px-48 bg-gray-100">
    <div class="w-full mx-auto">
        <h1 class="h1-headline">Non hai il <span class="text-bullstar">file perfetto</span>? Ci pensiamo noi.</h1>
        <p class="body1-text mt-24 mx-auto">Se la grafica è semplice, la sistemiamo noi. Se è più complessa, ti guidiamo passo dopo passo.</p>
    </div>

    <div class="mt-100 md:mt-120 lg:mt-160 w-full">
        <h1 class="h1-headline break-words">Non devi sapere nulla di stampa.</h1>
        <p class="body1-text mt-24">Scegliamo noi la tecnica giusta per il tuo progetto.</p>
    </div>

    <div class="swiper mySwiper4 mt-48">
        <div class="swiper-wrapper">

            @php
                $steps = [
                    [
                        'title' => 'DTF',
                        'text' => 'Ideale per grafiche dettagliate, anche in piccole quantità.',
                        'img' => 'stuart-landing-magliette-progetto-dtf'
                    ],
                    [
                        'title' => 'Serigrafia',
                        'text' => 'Perfetta per grandi quantità, con colori pieni e resistenti.',
                        'img' => 'stuart-landing-magliette-progetto-serigrafia'
                    ],
                    [
                        'title' => 'Ricamo',
                        'text' => 'Effetto premium, perfetto per loghi e dettagli eleganti.',
                        'img' => 'stuart-landing-magliette-progetto-ricamo'
                    ],
                    [
                        'title' => 'Sublimazione',
                        'text' => 'Stampa totale su capi tecnici, senza limiti di colore.',
                        'img' => 'stuart-landing-magliette-progetto-sublimazione'
                    ],
                ];
            @endphp

            @foreach ($steps as $index => $step)
                <div class="swiper-slide w-full flex flex-col">
                    <div class="w-full">
                        <link 
                            rel="preload" 
                            as="image"
                            href="{{ asset('assets/images/landing/' . $step['img'] . '-1024.webp') }}"
                            imagesrcset="
                                {{ asset('assets/images/landing/' . $step['img'] . '-7680.webp') }} 768w,
                                {{ asset('assets/images/landing/' . $step['img'] . '-1024.webp') }} 1024w,
                                {{ asset('assets/images/landing/' . $step['img'] . '-1440.webp') }} 1440w,
                                {{ asset('assets/images/landing/' . $step['img'] . '-1920.webp') }} 1920w
                            "
                            imagesizes="100vw"
                            fetchpriority="high"
                        />

                        <img
                            src="{{ asset('assets/images/landing/' . $step['img'] . '-1024.webp') }}"
                            srcset="
                                {{ asset('assets/images/landing/' . $step['img'] . '-7680.webp') }} 768w,
                                {{ asset('assets/images/landing/' . $step['img'] . '-1024.webp') }} 1024w,
                                {{ asset('assets/images/landing/' . $step['img'] . '-1440.webp') }} 1440w,
                                {{ asset('assets/images/landing/' . $step['img'] . '-1920.webp') }} 1920w
                            "
                            sizes="100vw"
                            alt="Foto {{ $step['title'] }}"
                            class="w-full object-cover aspect-square"
                            width="1920"
                            height="960"
                            decoding="async"
                            fetchpriority="high"
                        />
                    </div>
                    <h5 class="mt-36 h5-grafico">{{ $step['title'] }}</h5>
                    <p class="body2-text mt-12">{{ $step['text'] }}</p>
                </div>
            @endforeach

        </div>
    </div>

    <div class="w-full mt-64 md:mt-120 lg:mt-160 flex flex-col items-center">
        <h2 class="h2-title mt-48">Mandaci la tua grafica e ti mostriamo subito il <span class="text-bullstar">risultato</span>.</h2>
        <button class="button-cta bg-bullstar hover:bg-bullstar-hover mt-36" button-cta data-event-page="landing_magliette" data-event-area="progetto_grafico" data-event-cta="progetto_whatsapp">Ricevi il tuo mockup su Whatsapp</button>

        <span class="text-12 mt-12 font-semibold">Senza impegno • Risposta immediata</span>
    </div>
</section>