@props(['id', 'title', 'description' => '', 'wide' => false, 'parent' => ''])
<template id="{{ $id }}-template" data-catalog-dialog-template="{{ $id }}">
<dialog id="{{ $id }}" data-catalog-dialog data-catalog-parent="{{ $parent }}" class="catalog-dialog {{ $wide ? 'catalog-dialog-wide' : '' }}" aria-labelledby="{{ $id }}-title">
    <div class="catalog-dialog-shell">
        <header class="catalog-dialog-header">
            <div><h2 id="{{ $id }}-title">{{ $title }}</h2>@if($description)<p>{{ $description }}</p>@endif</div>
            <button type="button" data-catalog-close class="catalog-close" aria-label="Chiudi finestra"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
        </header>
        <div class="catalog-dialog-content">
            @if($errors->any() && old('catalog_dialog') === $id)
                <div class="catalog-error" role="alert"><strong>Controlla i dati inseriti</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            {{ $slot }}
        </div>
    </div>
</dialog>
</template>
