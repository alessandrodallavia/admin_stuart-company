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
            <div style="padding:24px"><h2>Aree personalizzabili</h2><p class="catalog-help">Per ciascun lato indica le dimensioni reali in cm e il rettangolo sulla tavola di anteprima 4:5 (percentuali da sinistra e dall’alto). L’altezza sulla tavola viene calcolata dalle dimensioni reali per mantenere le proporzioni. Usa foto uniformi per tutti i colori. Per la manica viene mostrata una tavola dedicata. Senza tutte le misure il posizionamento libero non è disponibile. Svuota tutti i campi di un lato per rimuovere l’area.</p>
@foreach(['front'=>'Fronte','back'=>'Retro','sleeve'=>'Manica'] as $side=>$label)
<fieldset data-print-area-fields style="margin-top:20px"><legend><strong>{{ $label }}</strong></legend><div class="catalog-form-grid">
@foreach(['width_cm'=>'Larghezza reale cm','height_cm'=>'Altezza reale cm','x_percent'=>'Distanza sinistra %','y_percent'=>'Distanza alto %','width_percent'=>'Larghezza tavola %','height_percent'=>'Altezza tavola % · calcolata'] as $field=>$caption)
<label class="catalog-field"><span>{{ $caption }}</span><input name="areas[{{ $side }}][{{ $field }}]" type="number" step="0.01" min="{{ in_array($field,['x_percent','y_percent']) ? 0 : 0.1 }}" max="{{ str_ends_with($field,'_cm') ? 200 : 100 }}" data-area-field="{{ $field }}" @readonly($field === 'height_percent') value="{{ old('areas.'.$side.'.'.$field, $areas[$side][$field] ?? '') }}" @disabled(!$canManage)></label>
@endforeach</div>
@php($previewColor = $side !== 'sleeve' ? $product->colors->first(fn($color) => filled($color->{'mockup_'.$side.'_path'})) : null)
<div style="position:relative;aspect-ratio:4/5;max-width:260px;margin-top:16px;background:#f1f5f9;border:1px solid #dfe5ed;border-radius:12px;overflow:hidden">
@if($previewColor)<img src="{{ route('admin.crm-catalog.mockup-assets.show', ['path'=>$previewColor->{'mockup_'.$side.'_path'}]) }}" alt="Riferimento {{ $label }} · {{ $previewColor->name }}" style="width:100%;height:100%;object-fit:contain">@endif
<div data-area-preview hidden style="position:absolute;border:2px dashed #dc2626;background:rgba(220,38,38,.08);pointer-events:none"></div>
</div><p class="catalog-help">{{ $previewColor ? 'Colore di riferimento: '.$previewColor->name : 'Tavola di riferimento · foto non disponibile per questo lato' }}</p>
</fieldset>@endforeach</div>
<footer class="catalog-modal-footer"><span class="catalog-help catalog-footer-note">Le stesse regole seguono il prodotto in ogni associazione Economic, Basic o Premium.</span>@if($canManage)<button class="catalog-button catalog-button-primary">Salva posizioni e lavorazioni</button>@endif</footer>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-print-area-fields]').forEach(group=>{
 const field=name=>group.querySelector(`[data-area-field="${name}"]`);
 const sync=()=>{const w=Number(field('width_cm').value),h=Number(field('height_cm').value),p=Number(field('width_percent').value);field('height_percent').value=w>0&&h>0&&p>0?(p*h/w*.8).toFixed(2):'';
 const preview=group.querySelector('[data-area-preview]');preview.hidden=!field('height_percent').value;
 Object.assign(preview.style,{left:`${Number(field('x_percent').value)}%`,top:`${Number(field('y_percent').value)}%`,width:`${p}%`,height:`${Number(field('height_percent').value)}%`});};
 group.addEventListener('input',sync);sync();
});
</script>
@endpush
