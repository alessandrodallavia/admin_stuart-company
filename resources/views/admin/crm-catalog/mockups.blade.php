@extends('admin.layouts.app')
@section('title', 'Mockup per colore - Stuart Admin')
@section('page_title', 'Mockup per colore')
@section('active_nav', 'crm-catalog')
@section('content')
@php
    $canManage = auth('admin')->user()?->hasAdminPermission('crm_catalog.manage');
@endphp
<div class="catalog-workspace" data-crm-mockups>
    <header class="catalog-page-header"><div><p class="catalog-eyebrow">{{ $product->code }} · {{ $product->name }}</p><h1>Mockup per colore</h1><p>Associa immagini fronte e retro al colore del prodotto. Verranno usate dal configuratore.</p></div><a href="{{ route('admin.crm-catalog.index') }}" class="catalog-button catalog-button-secondary">Torna al catalogo</a></header>
    @if($errors->any())<div class="catalog-error" role="alert"><strong>Controlla le immagini selezionate</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="catalog-surface catalog-mockup-layout">
        <nav class="catalog-mockup-colors" aria-label="Colori del prodotto">
            @forelse($product->colors as $item)
                <a href="{{ route('admin.crm-catalog.products.mockups.index', ['product' => $product, 'color' => $item->id]) }}" @if($color?->id === $item->id) aria-current="page" @endif><i style="background:{{ $item->hex ?: '#eeeeee' }}"></i><span><strong>{{ $item->name }}</strong><small>{{ $item->code }} · {{ (int)(bool)$item->mockup_front_path + (int)(bool)$item->mockup_back_path }}/2 immagini</small></span></a>
            @empty<p class="catalog-help">Aggiungi prima i colori nella scheda prodotto.</p>@endforelse
        </nav>
        <div class="catalog-modal-body">
        @if($color)
            <h2 class="text-20 font-bold">{{ $color->name }} · {{ $color->code }}</h2>
            <p class="catalog-help" style="margin:8px 0 20px">Scegli immagini del prodotto e colore corretti. PNG, JPG o WebP, massimo 8 MB e 5000 × 5000 px. Per sovrapporre le grafiche sono consigliati PNG o WebP con sfondo trasparente.</p>
            <form method="POST" enctype="multipart/form-data" action="{{ route('admin.crm-catalog.products.mockups.update', [$product, $color]) }}" data-mockup-form>
                @csrf @method('PATCH')
                <div class="catalog-mockup-sides">
                @foreach(['front' => 'Fronte', 'back' => 'Retro'] as $side => $label)
                    @php
                        $path = $color->{'mockup_'.$side.'_path'};
                        $preview = $path ? route('admin.crm-catalog.mockup-assets.show', ['path' => $path]) : '';
                    @endphp
                    <section data-mockup-side="{{ $side }}" data-original-url="{{ $preview }}" data-original-path="{{ $path }}">
                        <h3>{{ $label }}</h3>
                        <div class="catalog-mockup-preview"><img data-mockup-preview @if($preview) src="{{ $preview }}" @endif alt="Mockup {{ strtolower($label) }} {{ $color->name }}" @if(!$path) hidden @endif><p data-mockup-empty @if($path) hidden @endif>Nessuna immagine associata</p></div>
                        <p data-mockup-filename class="catalog-help catalog-mockup-filename">{{ $path ?: 'Anteprima non disponibile' }}</p>
                        @if($canManage)
                            <input type="hidden" name="{{ $side }}_action" value="keep" data-mockup-action>
                            <input type="hidden" name="{{ $side }}_asset" value="" data-mockup-asset>
                            <div class="catalog-mockup-actions"><button type="button" data-mockup-browse="{{ $side }}" class="catalog-button catalog-button-secondary">Scegli dalla libreria</button><label class="catalog-button catalog-button-secondary">Carica immagine<input type="file" name="{{ $side }}_file" accept="image/png,image/jpeg,image/webp" data-mockup-upload class="sr-only"></label></div>
                            <div class="catalog-mockup-actions"><button type="button" data-mockup-clear class="catalog-text-button catalog-text-danger">Rimuovi associazione</button><button type="button" data-mockup-reset class="catalog-text-button">Ripristina</button></div>
                        @endif
                    </section>
                @endforeach
                </div>
                @if($canManage)<footer class="catalog-modal-footer"><p class="catalog-help catalog-footer-note" data-mockup-status aria-live="polite">Le modifiche saranno applicate dopo il salvataggio. I file della libreria non vengono eliminati.</p><button class="catalog-button catalog-button-primary">Salva mockup del colore</button></footer>@endif
            </form>
        @else<p>Scegli un prodotto con colori per associare i suoi mockup.</p>@endif
        </div>
    </div>
    @if($canManage && $color)
    <script type="application/json" data-mockup-library-data>@json($library)</script>
    <dialog class="catalog-dialog catalog-dialog-wide" data-mockup-library aria-labelledby="mockup-library-title">
        <div class="catalog-dialog-shell"><header class="catalog-dialog-header"><div><h2 id="mockup-library-title">Libreria mockup</h2><p>Seleziona una foto del prodotto e del colore che stai modificando.</p></div><button type="button" class="catalog-close" data-mockup-library-close aria-label="Chiudi libreria">×</button></header>
            <div class="catalog-dialog-content catalog-modal-body"><label class="catalog-field"><span>Cerca nella libreria</span><input type="search" data-mockup-search placeholder="Nome file, cartella o codice colore" autocomplete="off"></label><p class="catalog-help" data-mockup-library-count aria-live="polite" style="margin:12px 0"></p><div class="catalog-mockup-gallery" data-mockup-gallery></div><button type="button" data-mockup-more class="catalog-button catalog-button-secondary" style="margin-top:16px">Mostra altre immagini</button></div>
        </div>
    </dialog>
    @endif
</div>
@endsection
