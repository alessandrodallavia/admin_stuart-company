<div class="space-y-16 font-montserrat">
    @if (session('status'))
        <div class="rounded-10 border border-whatsapp/20 bg-whatsapp/10 px-12 py-8 text-14 font-bold text-whatsapp">
            {{ session('status') }}
        </div>
    @endif

    @if (session('import_errors'))
        <div class="rounded-10 border border-red-200 bg-red-50 px-12 py-8 text-14 font-semibold text-red-700">
            <p class="font-black">File saltati</p>
            <ul class="mt-6 list-disc space-y-2 pl-16">
                @foreach (session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form wire:submit.prevent="import" class="document-surface p-20">
        <div class="grid gap-12 lg:grid-cols-[minmax(0,1fr)_220px] lg:items-end">
            <label class="block">
                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">File XML fattura</span>
                <input wire:model="xmlFiles" type="file" multiple accept=".xml" class="mt-6 w-full rounded-10 border border-gray-mid px-12 py-8 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
            </label>

            <label class="flex items-center gap-8 rounded-10 border border-gray-mid bg-gray-light px-12 py-8 text-13 font-bold text-black-nike">
                <input wire:model="markAsPaid" type="checkbox" value="1" class="rounded border-gray-mid text-bullstar focus:ring-bullstar">
                <span>Importa come pagate</span>
            </label>
        </div>

        @error('xmlFiles')
            <p class="mt-6 text-12 font-bold text-red-700">{{ $message }}</p>
        @enderror
        @error('xmlFiles.*')
            <p class="mt-6 text-12 font-bold text-red-700">{{ $message }}</p>
        @enderror

        <div class="mt-12 flex flex-wrap items-center gap-8">
            <button type="submit" wire:loading.attr="disabled" class="rounded-10 bg-bullstar px-12 py-8 text-12 font-extrabold uppercase tracking-normal text-white transition hover:bg-bullstar-hover disabled:opacity-60">
                <span wire:loading.remove>Importa</span>
                <span wire:loading>Importazione...</span>
            </button>
            <a href="{{ route('admin.documents.index', ['type' => 'invoice']) }}" class="rounded-10 border border-gray-mid px-12 py-8 text-12 font-extrabold uppercase tracking-normal transition hover:border-black-nike">
                Annulla
            </a>
        </div>
    </form>
</div>
