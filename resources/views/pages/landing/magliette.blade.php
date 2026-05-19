<x-layouts.landing>
    @section('meta_title', 'T-Shirt Personalizzate con Mockup Gratuito | Stuart')
    @section('meta_description', 'Ricevi il mockup delle tue t-shirt personalizzate in giornata. Ideali per gruppi, eventi, team e organizzazioni. Preventivo veloce su Whatsapp.')
    @section('meta_type', 'website')

    @php
        $landing = config('landing.' . request()->route()->getName(), []);
    @endphp

    @include('partials.landing.magliette.hero', ['landing' => $landing])
    @include('partials.landing.magliette.call')
    @include('partials.landing.magliette.processo')
    @include('partials.landing.magliette.prodotti')
    @include('partials.landing.magliette.doppio')
    @include('partials.landing.magliette.progetto-grafico')
    @include('partials.landing.magliette.partners')
    @include('partials.landing.magliette.clienti')
    @include('partials.landing.magliette.modulo')
    @include('partials.landing.magliette.faq')
</x-layouts.landing>