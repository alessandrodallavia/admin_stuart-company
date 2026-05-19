<style>
    .swiper-button-prev5,
    .swiper-button-next5 {
        cursor: pointer;
    }
    .swiper-button-prev5.swiper-button-disabled,
    .swiper-button-next5.swiper-button-disabled {
        opacity: 0.35;
        cursor: auto;
        pointer-events: none;
    }
</style>

<section class="py-75 px-24 md:px-48 w-full bg-bullstar">
    <div class="max-w-5xl mx-auto">
        <h1 class="h1-headline text-white">I capi fanno la differenza</h1>
        <p class="body1-text mt-24 text-white">Ogni prodotto è scelto per garantire resa, durata e qualità di stampa.</p>
    </div>

    <div class="w-full h-48 hidden justify-end gap-12">
        <button type="button"
            class="swiper-button-prev5 static w-48 h-48 flex items-center justify-center rounded-full overflow-hidden
                    bg-gray-200 hover:bg-gray-300 transition after:content-[''] after:hidden mt-0">
            <span class="flex items-center justify-center w-24 h-24 shrink-0">
                <svg viewBox="0 0 24 24" class="w-full h-full block text-black" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </span>
        </button>

        <button type="button"
            class="swiper-button-next5 static w-48 h-48 flex items-center justify-center rounded-full overflow-hidden
                    bg-gray-200 hover:bg-gray-300 transition after:content-[''] after:hidden mt-0">
            <span class="flex items-center justify-center w-24 h-24 shrink-0">
                <svg viewBox="0 0 24 24" class="w-full h-full block text-black" fill="none" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </span>
        </button>
    </div>

    <div class="swiper mySwiper5 mt-24">
        <div class="swiper-wrapper items-center">

            @php
                $partners = [
                    [
                        'image' => '1-bellacanvas.png'
                    ],
                    [
                        'image' => '2-buildyourbrand.png'
                    ],
                    [
                        'image' => '3-bs.png'
                    ],
                    [
                        'image' => '4-justhoods.png'
                    ],
                    [
                        'image' => '5-kariban.png'
                    ],
                    [
                        'image' => '6-anthem.png'
                    ],
                    [
                        'image' => '7-stanleystella.png'
                    ],
                    [
                        'image' => '8-teejays.png'
                    ],
                ];
            @endphp

            @foreach ($partners as $index => $partner)
                <div class="swiper-slide w-full flex items-center">
                    <img src="{{ asset('/assets/images/landing/') . '/' . $partner['image'] }}" alt="logo" class="w-2/3 max-h-[250px] object-contain">
                </div>
            @endforeach

        </div>
    </div>
</section>