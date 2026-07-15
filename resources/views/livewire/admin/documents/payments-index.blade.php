<div class="font-montserrat">
    <section class="mb-16 flex flex-wrap gap-8">
        <a href="{{ route('admin.documents.index') }}" class="rounded-10 border border-gray-mid bg-white px-12 py-8 text-12 font-extrabold uppercase tracking-normal transition hover:border-black-nike">Documenti</a>
        @foreach (['unpaid' => 'Da pagare', 'partial' => 'Parziali', 'paid' => 'Pagati'] as $value => $label)
            <button type="button" wire:click="filter('{{ $value }}')" class="rounded-10 border px-12 py-8 text-12 font-extrabold uppercase tracking-normal transition {{ $status === $value ? 'border-bullstar bg-bullstar text-white' : 'border-gray-mid bg-white hover:border-black-nike' }}">{{ $label }}</button>
        @endforeach
        <button type="button" wire:click="filter('')" class="rounded-10 border px-12 py-8 text-12 font-extrabold uppercase tracking-normal transition {{ $status === '' ? 'border-bullstar bg-bullstar text-white' : 'border-gray-mid bg-white hover:border-black-nike' }}">Tutti</button>
    </section>

    <section class="document-surface overflow-hidden">
        <div class="border-b border-gray-mid px-16 py-12">
            <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Scadenziario Livewire</p>
            <p class="mt-4 text-14 font-bold text-black-nike">{{ $payments->total() }} scadenze trovate</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[920px] text-left">
                <thead class="border-b border-gray-mid bg-gray-light text-11 font-extrabold uppercase tracking-normal text-gray">
                    <tr>
                        <th class="px-12 py-12">Scadenza</th>
                        <th class="px-12 py-12">Documento</th>
                        <th class="px-12 py-12">Cliente</th>
                        <th class="px-12 py-12">Stato</th>
                        <th class="px-12 py-12 text-right">Importo</th>
                        <th class="px-12 py-12 text-right">Pagato</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-mid">
                    @forelse ($payments as $payment)
                        <tr wire:key="payment-{{ $payment->id }}">
                            <td class="px-12 py-12">
                                <p class="text-14 font-black">{{ optional($payment->due_date)->format('d/m/Y') }}</p>
                                <p class="mt-4 text-11 font-semibold text-gray">{{ $payment->method ?: 'Metodo non indicato' }}</p>
                            </td>
                            <td class="px-12 py-12">
                                <a href="{{ route('admin.documents.show', $payment->document) }}" class="text-14 font-bold text-bullstar underline-offset-4 hover:underline">{{ $payment->document->type_label }} {{ $payment->document->display_code }}</a>
                            </td>
                            <td class="px-12 py-12 text-14 font-semibold">{{ $payment->document->customer_name }}</td>
                            <td class="px-12 py-12"><span class="rounded-full bg-gray-light px-10 py-6 text-11 font-extrabold uppercase tracking-normal text-gray">{{ $payment->status_label }}</span></td>
                            <td class="px-12 py-12 text-right text-14 font-black">€ {{ number_format((float) $payment->amount, 2, ',', '.') }}</td>
                            <td class="px-12 py-12 text-right text-14 font-black">€ {{ number_format((float) $payment->paid_amount, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-16 py-28"><div class="rounded-10 border border-dashed border-gray-mid bg-gray-light p-20 text-14 font-semibold text-gray">Nessuna scadenza trovata.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-mid px-16 py-12">
            {{ $payments->links() }}
        </div>
    </section>
</div>
