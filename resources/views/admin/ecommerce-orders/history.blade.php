<section class="catalog-surface p-20 md:p-24">
    <h2 class="text-base font-semibold">Note interne</h2>
    @if($canManageOrder && $order->status !== 'cancelled')<form method="POST" action="{{ route('admin.ecommerce-orders.notes', $order) }}" class="mt-16 space-y-16">@csrf @method('PATCH')<label class="catalog-field"><span>Visibili solo agli operatori</span><textarea name="internal_notes" rows="4" maxlength="5000" class="w-full rounded-xl border border-gray-mid p-12 text-13">{{ old('internal_notes', $order->internal_notes) }}</textarea></label><button class="catalog-button catalog-button-secondary">Salva note interne</button></form>
    @else<p class="mt-16 whitespace-pre-line text-13 leading-relaxed">{{ $order->internal_notes ?: 'Nessuna nota interna.' }}</p>@endif
</section>
<section class="catalog-surface p-20 md:p-24">
    <h2 class="text-base font-semibold">Storico gestione</h2>
    <ol class="mt-16 divide-y divide-gray-mid">
        @forelse($order->events as $event)
            @php($eventLabel = match($event->kind) { 'in_production' => 'Ordine in lavorazione', 'shipped' => 'Ordine spedito', 'delivered' => 'Consegna registrata', 'collected' => 'Ritiro registrato', 'tracking_updated' => 'Tracking aggiornato', 'variants' => 'Varianti aggiornate', 'document' => 'Documento generato', default => 'Gestione ordine aggiornata' })
            <li class="py-16 first:pt-0 last:pb-0"><p class="text-13 font-semibold">{{ $eventLabel }}</p><p class="mt-4 text-11 text-gray">{{ $event->created_at->format('d/m/Y H:i') }} · {{ $event->actor?->name ?: 'Sistema' }}</p>
                @if(isset($event->details['after']['order_status']))<p class="mt-8 text-12">Stato: {{ \App\Models\EcommerceOrder::ORDER_STATUSES[$event->details['after']['order_status']] ?? $event->details['after']['order_status'] }}</p>@endif
                @if(isset($event->details['after']['fulfillment_status']))<p class="mt-8 text-12">Consegna: {{ \App\Models\EcommerceOrder::FULFILLMENT_STATUSES[$event->details['after']['fulfillment_status']] ?? $event->details['after']['fulfillment_status'] }}</p>@endif
                @if($event->details['code'] ?? null)<p class="mt-8 text-12">{{ $event->details['code'] }}</p>@endif
                @if($event->kind === 'variants')<p class="mt-8 text-12">{{ $event->details['item'] ?? '' }}</p>@endif
                @if($event->details['tracking'] ?? null)<p class="mt-8 break-all text-12">{{ $event->details['carrier'] ?? '' }} · {{ $event->details['tracking'] }}</p>@endif
                @if($event->notification_status)<p class="mt-8 text-11 text-gray">Notifica cliente: {{ match($event->notification_status) { 'sent' => 'inviata', 'suppressed' => 'non inviata per scelta dell’operatore', 'failed' => 'invio da ritentare', 'skipped' => 'non inviata: ordine annullato', default => 'in coda' } }}</p>@endif
            </li>
        @empty<li class="text-13 text-gray">Non ci sono ancora aggiornamenti operativi.</li>@endforelse
    </ol>
</section>
