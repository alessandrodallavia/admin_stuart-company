@extends('admin.layouts.app')
@section('title', 'Personalizzazioni del prodotto - Stuart Admin')
@section('page_title', 'Personalizzazioni per posizione')
@section('active_nav', 'crm-catalog')
@section('content')
@php
    $canManage = auth('admin')->user()?->hasAdminPermission('crm_catalog.manage');
    $selected = old('positions', $rules);
@endphp
<div class="catalog-workspace">
    <header class="catalog-page-header"><div><p class="catalog-eyebrow">{{ $product->code }} · {{ $product->name }}</p><h1>Personalizzazioni per posizione</h1><p>Seleziona tu le lavorazioni consentite su questo prodotto. Le posizioni senza selezioni non compariranno al cliente.</p></div><a class="catalog-button catalog-button-secondary" href="{{ route('admin.crm-catalog.calculator.index') }}">Prodotti per calcolatore</a></header>
    @if($errors->any())<div class="catalog-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="catalog-surface">
        <div class="catalog-section-heading"><div><h2>Fronte, retro e manica</h2><p>Nessuna associazione viene creata automaticamente. Se il prodotto non ha una manica personalizzabile, lascia vuota l’intera colonna Manica.</p></div></div>
        <form method="POST" action="{{ route('admin.crm-catalog.products.personalizations.update', $product) }}">@csrf @method('PATCH')
            <div class="catalog-table-scroll"><table class="catalog-table"><thead><tr><th scope="col">Lavorazione</th><th scope="col">Tecnica</th>@foreach(['front'=>'Fronte','back'=>'Retro','sleeve'=>'Manica'] as $position=>$label)<th scope="col">{{ $label }}</th>@endforeach</tr></thead><tbody>
            @forelse($printTypes as $type)
                <tr><td><strong>{{ $type->name }}</strong><span class="catalog-secondary">{{ $type->code }}{{ $type->size_label ? ' · '.$type->size_label : '' }}</span>@if(!$type->is_active)<span class="catalog-secondary">Inattiva</span>@endif</td><td>{{ ['print'=>'Stampa','embroidery'=>'Ricamo','other'=>'Altra lavorazione'][$type->technique] ?? $type->technique }}@if($type->setup_print_type_id)<span class="catalog-secondary">Impianto una tantum per ogni ricamo selezionato</span>@endif</td>
                @foreach(['front'=>'Fronte','back'=>'Retro','sleeve'=>'Manica'] as $position=>$label)<td><label class="catalog-check" style="min-height:44px;align-items:center"><input type="checkbox" name="positions[{{ $position }}][]" value="{{ $type->id }}" @checked(in_array($type->id, $selected[$position] ?? [])) @disabled(!$canManage || !$type->is_active) aria-label="{{ $type->name }} su {{ strtolower($label) }}"><span>{{ $label }}</span></label></td>@endforeach</tr>
            @empty<tr><td colspan="5" class="catalog-empty">Crea prima le lavorazioni nel catalogo.</td></tr>@endforelse
            </tbody></table></div>
            <footer class="catalog-modal-footer"><span class="catalog-help catalog-footer-note">Le stesse regole seguono il prodotto in ogni associazione Economic, Basic o Premium.</span>@if($canManage)<button class="catalog-button catalog-button-primary">Salva posizioni e lavorazioni</button>@endif</footer>
        </form>
    </div>
</div>
@endsection
