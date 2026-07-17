@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Paginazione" class="flex flex-col gap-8 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-11 font-semibold text-gray">
            Righe {{ $paginator->firstItem() ?: 0 }}-{{ $paginator->lastItem() ?: 0 }} di {{ $paginator->total() }}
        </p>

        <div class="flex flex-wrap items-center gap-4">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" aria-label="Pagina precedente" class="inline-flex h-28 items-center rounded-10 border border-gray-mid px-8 text-11 font-bold text-gray opacity-50">‹</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Pagina precedente" class="inline-flex h-28 items-center rounded-10 border border-gray-mid bg-white px-8 text-11 font-bold transition hover:border-bullstar hover:text-bullstar focus:outline-none focus:ring-2 focus:ring-bullstar">‹</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span aria-disabled="true" class="px-3 text-11 font-bold text-gray">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="inline-flex h-28 min-w-28 items-center justify-center rounded-10 border border-black-nike bg-black-nike px-6 text-11 font-bold text-white">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" aria-label="Vai alla pagina {{ $page }}" class="inline-flex h-28 min-w-28 items-center justify-center rounded-10 border border-gray-mid bg-white px-6 text-11 font-bold transition hover:border-bullstar hover:text-bullstar focus:outline-none focus:ring-2 focus:ring-bullstar">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Pagina successiva" class="inline-flex h-28 items-center rounded-10 border border-gray-mid bg-white px-8 text-11 font-bold transition hover:border-bullstar hover:text-bullstar focus:outline-none focus:ring-2 focus:ring-bullstar">›</a>
            @else
                <span aria-disabled="true" aria-label="Pagina successiva" class="inline-flex h-28 items-center rounded-10 border border-gray-mid px-8 text-11 font-bold text-gray opacity-50">›</span>
            @endif
        </div>
    </nav>
@endif
