<style>
    .swiper-button-prev6,
    .swiper-button-next6 {
        cursor: pointer;
    }
    .swiper-button-prev6.swiper-button-disabled,
    .swiper-button-next6.swiper-button-disabled {
        opacity: 0.35;
        cursor: auto;
        pointer-events: none;
    }
</style>

<section class="w-full pt-100 pb-56 md:pt-120 md:pb-60 lg:pt-150 lg:pb-90 px-24 md:px-48">
    <h4 class="h4-title text-center mb-24">Clienti in tutta Italia</h4>
    <h1 class="h1-headline">Scelti da team, scuole ed eventi</h1>

    <div class="swiper mySwiper6 mt-36 lg:mt-48">
        <div class="swiper-wrapper">

            @php
                $customers = [
                    [
                        'image' => 'abano-calcio.jpg'
                    ],
                    [
                        'image' => 'aqs-borgo-veneto.jpg'
                    ],
                    [
                        'image' => 'bissuola-calcio.jpg'
                    ],
                    [
                        'image' => 'calcio-casalserugo.jpg'
                    ],
                    [
                        'image' => 'calcio-marcon.jpg'
                    ],
                    [
                        'image' => 'gregorense.jpg'
                    ],
                    [
                        'image' => 'modigliana.jpg'
                    ],
                    [
                        'image' => 'pettorazza-san-martino.jpg'
                    ],
                    [
                        'image' => 'pievebovigliana.jpg'
                    ],
                    [
                        'image' => 'piovese.jpg'
                    ],
                ];
            @endphp

            @foreach (collect($customers)->shuffle() as $index => $customer)
                <div class="swiper-slide aspect-square">
                    <img src="{{ asset('/assets/logos/customers/') . '/' . $customer['image'] }}" alt="logo" class="h-full object-contain">
                </div>
            @endforeach

        </div>
    </div>
</section>