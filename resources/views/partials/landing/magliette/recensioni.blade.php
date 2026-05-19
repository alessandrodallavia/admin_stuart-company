<style>
    .swiper-button-prev7,
    .swiper-button-next7 {
        cursor: pointer;
    }
    .swiper-button-prev7.swiper-button-disabled,
    .swiper-button-next7.swiper-button-disabled {
        opacity: 0.35;
        cursor: auto;
        pointer-events: none;
    }
</style>

<section class="w-full pb-150 px-24 md:px-96">

    <div class="swiper mySwiper7 md:mt-0">
        <div class="swiper-wrapper">

            @php
                $reviews = [
                    [
                        'name' => 'Claudia e Camilla',
                        'club' => 'Liceo Teresa Ciceri - Como (CO)',
                        'row' => '2024/2025',
                        'text' => 'Dovevamo fare felpe per il gruppo ma non sapevamo da dove iniziare. In giornata ci hanno mandato il mockup e tutto è stato semplice. Qualità top e consegna veloce.',
                    ],
                    [
                        'name' => 'Claudia e Camilla',
                        'club' => 'Liceo Teresa Ciceri - Como (CO)',
                        'row' => '2024/2025',
                        'text' => 'Dovevamo fare felpe per il gruppo ma non sapevamo da dove iniziare. In giornata ci hanno mandato il mockup e tutto è stato semplice. Qualità top e consegna veloce.',
                    ],
                    [
                        'name' => 'Claudia e Camilla',
                        'club' => 'Liceo Teresa Ciceri - Como (CO)',
                        'row' => '2024/2025',
                        'text' => 'Dovevamo fare felpe per il gruppo ma non sapevamo da dove iniziare. In giornata ci hanno mandato il mockup e tutto è stato semplice. Qualità top e consegna veloce.',
                    ],
                    [
                        'name' => 'Claudia e Camilla',
                        'club' => 'Liceo Teresa Ciceri - Como (CO)',
                        'row' => '2024/2025',
                        'text' => 'Dovevamo fare felpe per il gruppo ma non sapevamo da dove iniziare. In giornata ci hanno mandato il mockup e tutto è stato semplice. Qualità top e consegna veloce.',
                    ],
                ];
            @endphp

            @foreach ($reviews as $index => $review)
                <div class="swiper-slide flex flex-col border border-gray-300 p-24">
                    <div class="w-full flex flex-col">

                        <div class="w-full grid grid-cols-3">
                            <div class="w-full col-span-2 flex flex-col">
                                <span class="font-semibold">{{ $review['name'] }}</span>
                                <span>{{ $review['club'] }}</span>
                                <span>{{ $review['row'] }}</span>
                            </div>

                            <div class="w-full col-span-1 flex justify-end">
                                <div class="w-48 h-48 rounded-full bg-black"></div>
                            </div>
                        </div>

                        <div class="mt-36 w-full pr-12 md:pr-12">
                            <p class="body3-text">{{ $review['text'] }}</p>
                        </div>

                    </div>
                </div>
            @endforeach

        </div>
    </div>
</section>