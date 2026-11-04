@foreach(collect([null])->concat($printTypes) as $printType)
    @if(!$printType && !$canManageCatalog) @continue @endif
    @php($printDialogId = 'print-'.($printType?->id ?? 'new'))
    <x-catalog-dialog :id="$printDialogId" :title="$printType ? $printType->name : 'Nuova stampa'" description="Tariffe per applicazione · IVA esclusa" :wide="(bool)$printType">
        <div data-catalog-local-tabs>
        @if($printType)
            <div class="catalog-local-tabs" role="tablist" aria-label="Scheda lavorazione">
                <button type="button" role="tab" id="{{ $printDialogId }}-details-tab" data-catalog-local-tab="{{ $printDialogId }}-details" aria-controls="{{ $printDialogId }}-details" aria-selected="true">Lavorazione</button>
                <button type="button" role="tab" id="{{ $printDialogId }}-pricing-tab" data-catalog-local-tab="{{ $printDialogId }}-pricing" aria-controls="{{ $printDialogId }}-pricing" aria-selected="false" tabindex="-1">Fasce prezzo <span>{{ $printType->priceTiers->count() }}</span></button>
            </div>
        @endif
        <div id="{{ $printDialogId }}-details" data-catalog-local-panel @if($printType) role="tabpanel" aria-labelledby="{{ $printDialogId }}-details-tab" @endif>
        @if($canManageCatalog)
            @php($restorePrint = old('catalog_dialog') === $printDialogId)
            <form method="POST" action="{{ $printType ? route('admin.crm-catalog.prints.update', $printType) : route('admin.crm-catalog.prints.store') }}" class="catalog-form">@csrf @if($printType) @method('PATCH') @endif<input type="hidden" name="catalog_dialog" value="{{ $printDialogId }}"><input type="hidden" name="catalog_section" value="prints">
                <div class="catalog-modal-body catalog-form-grid"><label class="catalog-field"><span>Codice *</span><input name="code" value="{{ $restorePrint ? old('code') : $printType?->code }}" required maxlength="100" placeholder="Es. STAMPA_CUORE"></label><label class="catalog-field"><span>Nome lavorazione *</span><input name="name" value="{{ $restorePrint ? old('name') : $printType?->name }}" required maxlength="255" placeholder="Es. Stampa lato cuore"></label></div>
                <footer class="catalog-modal-footer"><button type="button" data-catalog-close class="catalog-button catalog-button-secondary">Annulla</button><button class="catalog-button catalog-button-primary">{{ $printType ? 'Salva stampa' : 'Aggiungi stampa' }}</button></footer>
            </form>
        @else<div class="catalog-modal-body"><dl class="catalog-details-grid"><div><dt>Codice</dt><dd>{{ $printType->code }}</dd></div><div><dt>Lavorazione</dt><dd>{{ $printType->name }}</dd></div></dl></div><footer class="catalog-modal-footer"><button type="button" data-catalog-close class="catalog-button catalog-button-secondary">Chiudi</button></footer>@endif
        @if($printType)
            @if($canManageCatalog)<div class="catalog-delete-zone"><span>La rimozione elimina anche le fasce della lavorazione.</span><form method="POST" action="{{ route('admin.crm-catalog.prints.destroy', $printType) }}" data-catalog-delete="Eliminare {{ $printType->name }} e tutte le sue fasce prezzo? Lo storico delle schede vendita conserverà nomi e importi.">@csrf @method('DELETE')<button class="catalog-text-button catalog-text-danger">Elimina stampa</button></form></div>@endif
        @endif
        </div>
        @if($printType)
            <div id="{{ $printDialogId }}-pricing" data-catalog-local-panel role="tabpanel" aria-labelledby="{{ $printDialogId }}-pricing-tab" hidden>
                @include('admin.crm-catalog.price-list', ['priceOwner' => $printType, 'priceKind' => 'print'])
            </div>
        @endif
        </div>
    </x-catalog-dialog>
    @if($printType && $canManageCatalog) @include('admin.crm-catalog.tier-dialogs', ['priceOwner' => $printType, 'priceKind' => 'print']) @endif
@endforeach
