@extends('admin.layouts.app')
@section('title', 'Prodotti per calcolatore - Stuart Admin')
@section('page_title', 'Prodotti per calcolatore')
@section('active_nav', 'crm-catalog')
@section('content')
@php
    $canManageCatalog = auth('admin')->user()?->hasAdminPermission('crm_catalog.manage');
@endphp
<div data-crm-catalog data-restore-dialog="{{ old('catalog_dialog', '') }}" class="catalog-workspace">
    <header class="catalog-page-header">
        <div><p class="catalog-eyebrow">Catalogo CRM</p><h1>Prodotti per calcolatore</h1><p>Scegli il prodotto e le lavorazioni per ogni modello e qualità.</p></div>
        <a href="{{ route('admin.crm-catalog.index') }}" class="catalog-button catalog-button-secondary">Torna al catalogo</a>
    </header>
    <p class="catalog-tax-note">Calcolatore e configuratore usano i prodotti abilitati e visibili sul sito. Il preventivo WhatsApp usa tutte le associazioni con prodotti attivi, anche se nascosti sul sito. Le T-shirt mantengono il listino attuale finché non assegni il primo prodotto. Da quel momento le opzioni senza associazione abilitata richiedono un preventivo.</p>
    @foreach(['hoodie' => 'Felpe', 'tshirt' => 'T-shirt'] as $family => $title)
    <section class="catalog-surface" style="margin-top:24px" aria-labelledby="heading-{{ $family }}">
        <div class="catalog-section-heading"><div><h2 id="heading-{{ $family }}">{{ $title }}</h2><p>{{ $family === 'tshirt' ? 'Scegli i prodotti per Economic, Basic e Premium, le lavorazioni consentite e le opzioni da abilitare. Le foto richiedono una corrispondenza verificata con il prodotto.' : 'Associazioni iniziali ricavate dal configuratore attuale.' }}</p></div></div>
        <div class="catalog-table-scroll"><table class="catalog-table"><thead><tr><th scope="col">Modello e qualità</th><th scope="col">Prodotto CRM</th><th scope="col">Lavorazioni consentite</th><th scope="col">Abilitazione sito</th><th scope="col"><span class="sr-only">Azioni</span></th></tr></thead><tbody>
        @foreach($slots->where('family', $family) as $slot)
            <tr>
                <td><strong>{{ $slot->label }}</strong></td>
                <td>@if($slot->product)<strong>{{ $slot->product->code }}</strong><span class="catalog-secondary">{{ $slot->product->name }}</span>@if(!$slot->product->is_active)<span class="catalog-secondary">Prodotto inattivo</span>@elseif(!$slot->product->is_visible_on_site)<span class="catalog-secondary">Prodotto nascosto sul sito</span>@endif @else<span class="catalog-secondary">Da scegliere</span>@endif</td>
                <td>{{ $slot->printTypes->pluck('name')->join(', ') ?: 'Da scegliere' }}</td>
                <td><span class="catalog-badge {{ $slot->is_enabled_on_site ? 'catalog-badge-green' : '' }}">{{ $slot->is_enabled_on_site ? 'Abilitata' : 'Disabilitata' }}</span></td>
                <td class="catalog-row-actions">@if($canManageCatalog)<button type="button" data-catalog-open="calculator-{{ $slot->id }}" class="catalog-button catalog-button-secondary">Modifica</button>@endif</td>
            </tr>
        @endforeach
        </tbody></table></div>
    </section>
    @endforeach
    @if($canManageCatalog)
    @foreach($slots as $slot)
    @php
        $restoring = old('catalog_dialog') === 'calculator-'.$slot->id;
        $selectedProduct = $restoring ? old('crm_product_id') : $slot->crm_product_id;
        $selectedPrints = $restoring ? old('print_type_ids', []) : $slot->printTypes->pluck('id')->all();
    @endphp
    <x-catalog-dialog id="calculator-{{ $slot->id }}" title="{{ $slot->label }}" description="Il prodotto e le lavorazioni potranno essere cambiati anche in seguito.">
        <form method="POST" action="{{ route('admin.crm-catalog.calculator.update', $slot) }}" class="catalog-form">
            @csrf @method('PATCH')
            <input type="hidden" name="catalog_dialog" value="calculator-{{ $slot->id }}">
            <div class="catalog-modal-body catalog-form-grid">
                <label class="catalog-field catalog-span-full"><span>Prodotto del catalogo</span><select name="crm_product_id"><option value="">Da scegliere in seguito</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected((string)$selectedProduct === (string)$product->id)>{{ $product->code }} — {{ $product->name }}{{ !$product->is_active ? ' (inattivo)' : '' }}</option>@endforeach</select><small>Puoi lasciare il prodotto non assegnato finché non hai deciso.</small></label>
                <fieldset class="catalog-field catalog-span-full"><legend>Lavorazioni consentite</legend>@foreach($printTypes as $print)<label style="display:flex;align-items:center;gap:12px;min-height:44px"><input type="checkbox" name="print_type_ids[]" value="{{ $print->id }}" @checked(in_array($print->id, $selectedPrints)) style="width:20px;height:20px"><span>{{ $print->name }}</span></label>@endforeach<small>Il calcolo usa STAMPA CUORE, FRONT, BACK e MAX per le rispettive dimensioni. Ricami e altre lavorazioni richiedono una quotazione dedicata.</small></fieldset>
                <label class="catalog-field catalog-span-full"><span>Abilitazione sul sito</span><select name="is_enabled_on_site"><option value="0" @selected(!($restoring ? old('is_enabled_on_site') : $slot->is_enabled_on_site))>Disabilitata</option><option value="1" @selected($restoring ? old('is_enabled_on_site') : $slot->is_enabled_on_site)>Abilitata</option></select><small>Richiede un prodotto attivo, visibile sul sito e con fasce prezzo, oltre a lavorazioni con fasce prezzo. Il prezzo sul sito segue gli scaglioni del catalogo CRM.</small></label>
            </div>
            <footer class="catalog-modal-footer"><button type="button" data-catalog-close class="catalog-button catalog-button-secondary">Annulla</button><button class="catalog-button catalog-button-primary">Salva associazione</button></footer>
        </form>
    </x-catalog-dialog>
    @endforeach
    @endif
</div>
@endsection
