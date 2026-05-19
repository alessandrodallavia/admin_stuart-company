<style>
    .swiper-button-prev3,
    .swiper-button-next3 {
        cursor: pointer;
    }
    .swiper-button-prev3.swiper-button-disabled,
    .swiper-button-next3.swiper-button-disabled {
        opacity: 0.35;
        cursor: auto;
        pointer-events: none;
    }
</style>

<section class="w-full py-90 md:py-100 lg:py-130">
    <div class="px-24 md:px-48">
        <h1 class="h1-headline text-bullstar">Ecco cosa possiamo creare per il tuo gruppo</h1>
        <p class="body1-text mt-16 mb-80 md:mb-120 font-medium">Ogni progetto è diverso, partiamo dal tuo e <span class="font-bold">ti mostriamo il risultato</span>.</p>
    </div>

    <div class="swiper mySwiper3">
        <div class="swiper-wrapper">
            @php
                $stepsG = [
                    [
                        'img' => 'stuart-landing-magliette-prodotti-gallery-2'
                    ],
                    [
                        'img' => 'stuart-landing-magliette-prodotti-gallery-3'
                    ],
                    [
                        'img' => 'stuart-landing-magliette-prodotti-gallery-1'
                    ],
                ];
            @endphp

            @foreach ($stepsG as $index => $stepG)
                <div class="md:px-48 swiper-slide w-full">
                    <div class="w-full aspect-3/4 md:h-[800px] md:max-h-[800px] md:aspect-auto">
                        <link 
                            rel="preload" 
                            as="image"
                            href="{{ asset('assets/images/landing/' . $stepG['img'] . '-1024.webp') }}"
                            imagesrcset="
                                {{ asset('assets/images/landing/' . $stepG['img'] . '-7680.webp') }} 768w,
                                {{ asset('assets/images/landing/' . $stepG['img'] . '-1024.webp') }} 1024w,
                                {{ asset('assets/images/landing/' . $stepG['img'] . '-1440.webp') }} 1440w,
                                {{ asset('assets/images/landing/' . $stepG['img'] . '-1920.webp') }} 1920w
                            "
                            imagesizes="100vw"
                            fetchpriority="high"
                        />

                        <img
                            src="{{ asset('assets/images/landing/' . $stepG['img'] . '-1024.webp') }}"
                            srcset="
                                {{ asset('assets/images/landing/' . $stepG['img'] . '-7680.webp') }} 768w,
                                {{ asset('assets/images/landing/' . $stepG['img'] . '-1024.webp') }} 1024w,
                                {{ asset('assets/images/landing/' . $stepG['img'] . '-1440.webp') }} 1440w,
                                {{ asset('assets/images/landing/' . $stepG['img'] . '-1920.webp') }} 1920w
                            "
                            sizes="100vw"
                            alt="Foto"
                            class="w-full h-full object-cover"
                            width="1920"
                            height="960"
                            decoding="async"
                            fetchpriority="high"
                        />
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 gap-x-12 gap-y-32 md:grid-cols-4 mt-50 md:mt-60 lg:mt-70 px-24 md:px-48">

        @php
            $steps = [
                [
                    'title' => 'Eventi e festival',
                    'text' => 'Perfette per staff, crew e gruppi organizzati.',
                    'img' => 'stuart-landing-magliette-prodotti-eventi'
                ],
                [
                    'title' => 'Scuole e università',
                    'text' => 'Ideali per classi, maturità e campus.',
                    'img' => 'stuart-landing-magliette-prodotti-scuole'
                ],
                [
                    'title' => 'Sport, team e tornei',
                    'text' => 'Per squadre, tornei e gruppi sportivi.',
                    'img' => 'stuart-landing-magliette-prodotti-sport'
                ],
                [
                    'title' => 'Aziende e community',
                    'text' => 'Per aziende, eventi e brand.',
                    'img' => 'stuart-landing-magliette-prodotti-aziende'
                ],
            ];
        @endphp

        @foreach ($steps as $index => $step)
            <div class="col-span-1 flex flex-col">
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
                <div class="mt-24 md:mt-48 w-3/4 md:w-4/5 xl:w-2/3">
                    <h3 class="h3-prodotti">{{ $step['title'] }}</h3>
                    <p class="body2-text mt-12">{{ $step['text'] }}</p>
                </div>
            </div>
        @endforeach
    </div>
</section>