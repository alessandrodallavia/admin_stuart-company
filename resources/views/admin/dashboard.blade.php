<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Chat WhatsApp - Bullstar Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-light text-black-nike antialiased">
    <div class="flex min-h-screen flex-col">
        <header class="border-b border-gray-mid bg-white">
            <div class="mx-auto flex max-w-[1440px] items-center justify-between px-20 py-16 md:px-32">
                <div class="flex items-center gap-16">
                    <img
                        src="{{ asset('assets/logos/logo-stuart.png') }}"
                        alt="Bullstar"
                        class="h-36 w-auto"
                    >
                    <div class="hidden h-32 w-px bg-gray-mid sm:block"></div>
                    <div>
                        <p class="text-12 font-extrabold uppercase tracking-normal text-bullstar">Admin</p>
                        <h1 class="text-24 font-black leading-none tracking-normal">WhatsApp</h1>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-8">
                    <a
                        href="{{ route('admin.dashboard') }}"
                        class="rounded-10 border border-bullstar bg-bullstar px-12 py-10 text-12 font-extrabold uppercase tracking-normal text-white"
                    >
                        WhatsApp
                    </a>
                    <a
                        href="{{ route('admin.leads.index') }}"
                        class="rounded-10 border border-gray-mid bg-white px-12 py-10 text-12 font-extrabold uppercase tracking-normal text-black-nike transition hover:border-black-nike"
                    >
                        Leads
                    </a>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="rounded-10 border border-gray-mid bg-white px-12 py-10 text-12 font-extrabold uppercase tracking-normal text-black-nike transition hover:border-black-nike"
                        >
                            Esci
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="mx-auto flex w-full max-w-[1440px] flex-1 flex-col px-16 py-20 md:px-32 md:py-28">
            <section class="mb-16 grid gap-12 md:grid-cols-4">
                <article class="rounded-10 border border-gray-mid bg-white p-16">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Conversazioni</p>
                    <p id="stat-total" class="mt-8 text-38 font-black leading-none tracking-normal">{{ $stats['total'] ?? 0 }}</p>
                </article>

                <article class="rounded-10 border border-gray-mid bg-white p-16">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Automatiche</p>
                    <p id="stat-auto" class="mt-8 text-38 font-black leading-none tracking-normal">{{ $stats['auto'] ?? 0 }}</p>
                </article>

                <article class="rounded-10 border border-gray-mid bg-white p-16">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Da prendere</p>
                    <p id="stat-needs-human" class="mt-8 text-38 font-black leading-none tracking-normal">{{ $stats['needs_human'] ?? 0 }}</p>
                </article>

                <article class="rounded-10 border border-gray-mid bg-white p-16">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Non letti</p>
                    <p id="stat-unread" class="mt-8 text-38 font-black leading-none tracking-normal">{{ $stats['unread'] ?? 0 }}</p>
                </article>
            </section>

            @if (session('status'))
                <div class="mb-16 rounded-10 border border-whatsapp/20 bg-whatsapp/10 px-16 py-12 text-14 font-bold text-whatsapp">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-16 rounded-10 border border-red-200 bg-red-50 px-16 py-12 text-14 font-bold text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <section class="grid min-h-[640px] flex-1 gap-16 lg:grid-cols-[360px_minmax(0,1fr)]">
                <aside class="overflow-hidden rounded-10 border border-gray-mid bg-white">
                    <div class="flex items-center justify-between border-b border-gray-mid px-16 py-14">
                        <div>
                            <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Inbox</p>
                            <p id="inbox-count" class="mt-4 text-14 font-bold text-black-nike">{{ $conversations->count() }} chat</p>
                        </div>
                        <a
                            href="{{ route('admin.dashboard') }}"
                            class="rounded-10 border border-gray-mid px-12 py-8 text-12 font-extrabold uppercase tracking-normal transition hover:border-black-nike"
                        >
                            Tutte
                        </a>
                    </div>

                    <div id="conversation-list" class="max-h-[590px] overflow-y-auto">
                        @forelse ($conversations as $conversation)
                            @php
                                $latestMessage = $conversation->latestMessage;
                                $isSelected = ($selectedConversation?->id ?? null) === $conversation->id;
                            @endphp

                            <a
                                data-conversation-id="{{ $conversation->id }}"
                                href="{{ route('admin.conversations.show', $conversation) }}"
                                class="block border-b border-gray-mid px-14 py-10 transition hover:bg-gray-light {{ $isSelected ? 'bg-gray-light' : 'bg-white' }}"
                            >
                                <div class="mb-4 flex items-start justify-between gap-10">
                                    <div class="min-w-0">
                                        <div class="flex min-w-0 items-center gap-8">
                                            <p class="truncate text-14 font-black leading-tight">
                                                {{ $conversation->lead?->name ?: $conversation->contact_phone }}
                                            </p>
                                            @if ($conversation->needs_human)
                                                <span class="shrink-0 rounded-full bg-brand/10 px-8 py-4 text-11 font-extrabold uppercase tracking-normal text-brand">
                                                    Subentra
                                                </span>
                                            @endif
                                            @if ($conversation->unread_incoming_messages_count > 0)
                                                <span class="shrink-0 rounded-full bg-black-nike px-8 py-4 text-11 font-extrabold uppercase tracking-normal text-white">
                                                    {{ $conversation->unread_incoming_messages_count }}
                                                </span>
                                            @endif
                                        </div>
                                        <p class="mt-4 truncate text-12 font-semibold text-gray">
                                            {{ $conversation->lead?->club ?: $conversation->contact_phone }}
                                        </p>
                                    </div>

                                    <span class="shrink-0 rounded-full px-8 py-4 text-11 font-extrabold uppercase tracking-normal {{ $conversation->mode === 'manual' ? 'bg-bullstar/10 text-bullstar' : 'bg-whatsapp/10 text-whatsapp' }}">
                                        {{ $conversation->mode }}
                                    </span>
                                </div>

                                <p class="line-clamp-1 text-12 font-semibold leading-[18px] text-gray">
                                    {{ $latestMessage?->body ?: match ($latestMessage?->type) {
                                        'image' => 'Immagine',
                                        'document' => 'Documento',
                                        'audio' => 'Audio',
                                        'video' => 'Video',
                                        default => 'Nessun messaggio testuale',
                                    } }}
                                </p>

                                <p class="mt-4 text-11 font-bold uppercase tracking-normal text-gray">
                                    {{ optional($conversation->last_message_at ?? $conversation->created_at)->format('d/m/Y H:i') }}
                                </p>
                            </a>
                        @empty
                            <div class="p-16">
                                <div class="rounded-10 border border-dashed border-gray-mid bg-gray-light p-20 text-14 font-semibold text-gray">
                                    Nessuna chat ancora presente.
                                </div>
                            </div>
                        @endforelse
                    </div>
                </aside>

                <section class="overflow-hidden rounded-10 border border-gray-mid bg-white">
                    @if ($selectedConversation)
                        <div class="flex min-h-[640px] flex-col">
                            <div class="border-b border-gray-mid px-20 py-16">
                                <div class="flex flex-col gap-16 xl:flex-row xl:items-center xl:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-8">
                                            <h2 class="truncate text-24 font-black leading-tight">
                                                {{ $selectedConversation->lead?->name ?: $selectedConversation->contact_phone }}
                                            </h2>
                                            @if ($selectedConversation->needs_human)
                                                <span class="rounded-full bg-brand/10 px-10 py-5 text-11 font-extrabold uppercase tracking-normal text-brand">
                                                    Devi subentrare
                                                </span>
                                            @endif
                                            <span class="rounded-full px-10 py-5 text-11 font-extrabold uppercase tracking-normal {{ $selectedConversation->mode === 'manual' ? 'bg-bullstar/10 text-bullstar' : 'bg-whatsapp/10 text-whatsapp' }}">
                                                {{ $selectedConversation->mode }}
                                            </span>
                                        </div>
                                        @if ($selectedConversation->needs_human)
                                            <p class="mt-8 rounded-10 border border-brand/20 bg-brand/5 px-12 py-10 text-14 font-bold text-brand">
                                                {{ data_get($selectedConversation->metadata, 'handoff_reason', 'La chat richiede il tuo intervento.') }}
                                            </p>
                                        @endif
                                        <p class="mt-6 text-14 font-semibold text-gray">
                                            {{ $selectedConversation->contact_phone }}
                                            @if ($selectedConversation->lead?->email)
                                                <span class="mx-6 text-gray-mid">/</span>{{ $selectedConversation->lead->email }}
                                            @endif
                                        </p>
                                    </div>

                                    <div class="flex gap-8">
                                        <form method="POST" action="{{ route('admin.conversations.mode', $selectedConversation) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="mode" value="auto">
                                            <button
                                                type="submit"
                                                class="rounded-10 border px-14 py-10 text-12 font-extrabold uppercase tracking-normal transition {{ $selectedConversation->mode === 'auto' ? 'border-whatsapp bg-whatsapp text-white' : 'border-gray-mid bg-white hover:border-whatsapp' }}"
                                            >
                                                Auto
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.conversations.mode', $selectedConversation) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="mode" value="manual">
                                            <button
                                                type="submit"
                                                class="rounded-10 border px-14 py-10 text-12 font-extrabold uppercase tracking-normal transition {{ $selectedConversation->mode === 'manual' ? 'border-bullstar bg-bullstar text-white' : 'border-gray-mid bg-white hover:border-bullstar' }}"
                                            >
                                                Manuale
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div id="message-list" class="flex-1 space-y-12 overflow-y-auto bg-gray-light px-16 py-20 md:px-24">
                                @forelse ($selectedConversation->messages as $message)
                                    @php
                                        $isOutbound = $message->direction === 'outbound';
                                        $statusLabel = $message->status ?: 'n/d';

                                        if ($isOutbound && $message->read_at) {
                                            $statusLabel = 'letto';
                                        } elseif ($isOutbound && $message->delivered_at) {
                                            $statusLabel = 'consegnato';
                                        } elseif ($isOutbound && $message->sent_at) {
                                            $statusLabel = 'inviato';
                                        } elseif (! $isOutbound && $message->admin_read_at) {
                                            $statusLabel = 'aperto';
                                        }
                                    @endphp

                                    <div class="flex {{ $isOutbound ? 'justify-end' : 'justify-start' }}">
                                        <div class="max-w-[78%] rounded-10 px-16 py-12 {{ $isOutbound ? 'bg-bullstar text-white' : 'border border-gray-mid bg-white text-black-nike' }}">
                                            @if ($message->media_path)
                                                @php
                                                    $mediaUrl = route('admin.messages.media', $message);
                                                    $mediaKind = str_starts_with((string) $message->media_mime_type, 'image/')
                                                        ? 'image'
                                                        : (str_starts_with((string) $message->media_mime_type, 'audio/') ? 'audio' : 'document');
                                                @endphp

                                                @if ($mediaKind === 'image')
                                                    <a href="{{ $mediaUrl }}" target="_blank" class="block overflow-hidden rounded-10 bg-black/5">
                                                        <img src="{{ $mediaUrl }}" alt="{{ $message->media_filename ?: 'Immagine WhatsApp' }}" class="max-h-[320px] w-full object-contain">
                                                    </a>
                                                @elseif ($mediaKind === 'audio')
                                                    <audio controls class="w-[260px] max-w-full">
                                                        <source src="{{ $mediaUrl }}" type="{{ $message->media_mime_type }}">
                                                    </audio>
                                                @else
                                                    <a href="{{ $mediaUrl }}" target="_blank" class="flex items-center gap-12 rounded-10 bg-white/15 px-12 py-10 text-14 font-bold underline-offset-4 hover:underline">
                                                        <span>Documento</span>
                                                        <span class="truncate">{{ $message->media_filename ?: 'Apri allegato' }}</span>
                                                    </a>
                                                @endif
                                            @endif

                                            @if ($message->body)
                                                <p class="whitespace-pre-line text-14 font-semibold leading-[20px] {{ $message->media_path ? 'mt-10' : '' }}">
                                                    {{ $message->body }}
                                                </p>
                                            @elseif (! $message->media_path)
                                                <p class="whitespace-pre-line text-14 font-semibold leading-[20px]">
                                                    [{{ $message->type }}]
                                                </p>
                                            @endif
                                            <div class="mt-8 flex flex-wrap items-center gap-8 text-11 font-bold uppercase tracking-normal {{ $isOutbound ? 'text-white/70' : 'text-gray' }}">
                                                <span>{{ $message->source }}</span>
                                                @if ($isOutbound)
                                                    <span>{{ $statusLabel }} {{ optional($message->read_at ?? $message->delivered_at ?? $message->sent_at ?? $message->created_at)->format('d/m/Y H:i') }}</span>
                                                @else
                                                    <span>{{ optional($message->received_at ?? $message->created_at)->format('d/m/Y H:i') }}</span>
                                                    <span>{{ $statusLabel }}</span>
                                                @endif
                                            </div>
                                            @if ($message->error_message)
                                                <p class="mt-8 text-12 font-bold text-red-100">
                                                    {{ $message->error_message }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="flex h-full items-center justify-center">
                                        <p class="text-14 font-semibold text-gray">Nessun messaggio in questa chat.</p>
                                    </div>
                                @endforelse
                            </div>

                            <div class="border-t border-gray-mid bg-white p-16">
                                @if ($selectedConversation->mode === 'manual')
                                    <form method="POST" action="{{ route('admin.conversations.messages.store', $selectedConversation) }}" enctype="multipart/form-data" class="flex flex-col gap-12">
                                        @csrf
                                        <textarea
                                            name="message"
                                            rows="2"
                                            maxlength="4096"
                                            class="min-h-[56px] resize-none rounded-10 border-gray-mid px-16 py-12 text-14 font-semibold text-black-nike placeholder:text-gray focus:border-bullstar focus:ring-bullstar"
                                            placeholder="Scrivi un messaggio o una didascalia..."
                                        >{{ old('message') }}</textarea>

                                        <div class="flex flex-col gap-10 md:flex-row md:items-center md:justify-between">
                                            <label class="flex min-w-0 flex-1 items-center gap-10 rounded-10 border border-gray-mid bg-gray-light px-12 py-10 text-12 font-bold text-gray">
                                                <span class="shrink-0 rounded-10 bg-white px-10 py-6 text-black-nike">Allega</span>
                                                <input
                                                    name="attachment"
                                                    type="file"
                                                    accept="image/*,audio/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.txt"
                                                    class="min-w-0 flex-1 text-12 file:hidden"
                                                >
                                            </label>

                                            <button
                                                type="submit"
                                                class="rounded-10 bg-bullstar px-24 py-12 text-14 font-extrabold uppercase tracking-normal text-white transition hover:bg-bullstar-hover"
                                            >
                                                Invia
                                            </button>
                                        </div>
                                    </form>
                                @else
                                    <div class="rounded-10 border border-dashed border-gray-mid bg-gray-light px-16 py-14 text-14 font-semibold text-gray">
                                        La chat è in automatico. Passala in manuale per scrivere dal pannello admin.
                                    </div>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="flex min-h-[640px] items-center justify-center p-24">
                            <div class="max-w-[420px] text-center">
                                <div class="mx-auto mb-20 flex h-56 w-56 items-center justify-center rounded-full bg-whatsapp/10 text-24 font-black text-whatsapp">
                                    W
                                </div>
                                <p class="text-30 font-black leading-tight tracking-normal">
                                    Seleziona una chat
                                </p>
                                <p class="mt-8 text-14 font-semibold text-gray">
                                    Qui vedrai lo storico dei messaggi e potrai rispondere quando la conversazione è manuale.
                                </p>
                            </div>
                        </div>
                    @endif
                </section>
            </section>
        </main>
    </div>
    <script>
            const selectedConversationId = @json($selectedConversation?->id);
            const pollUrl = @json($selectedConversation ? route('admin.conversations.poll', $selectedConversation) : route('admin.dashboard.poll'));
            let lastMessageSignature = '';

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function renderStats(stats) {
                document.getElementById('stat-total').textContent = stats.total;
                document.getElementById('stat-auto').textContent = stats.auto;
                document.getElementById('stat-needs-human').textContent = stats.needs_human;
                document.getElementById('stat-unread').textContent = stats.unread;
            }

            function renderConversationList(conversations) {
                const list = document.getElementById('conversation-list');
                document.getElementById('inbox-count').textContent = `${conversations.length} chat`;

                if (!conversations.length) {
                    list.innerHTML = `
                        <div class="p-16">
                            <div class="rounded-10 border border-dashed border-gray-mid bg-gray-light p-20 text-14 font-semibold text-gray">
                                Nessuna chat ancora presente.
                            </div>
                        </div>
                    `;
                    return;
                }

                list.innerHTML = conversations.map((conversation) => {
                    const selectedClass = conversation.id === selectedConversationId ? 'bg-gray-light' : 'bg-white';
                    const needsHuman = conversation.needs_human
                        ? '<span class="shrink-0 rounded-full bg-brand/10 px-8 py-4 text-11 font-extrabold uppercase tracking-normal text-brand">Subentra</span>'
                        : '';
                    const unread = conversation.unread_count > 0
                        ? `<span class="shrink-0 rounded-full bg-black-nike px-8 py-4 text-11 font-extrabold uppercase tracking-normal text-white">${conversation.unread_count}</span>`
                        : '';
                    const modeClass = conversation.mode === 'manual' ? 'bg-bullstar/10 text-bullstar' : 'bg-whatsapp/10 text-whatsapp';

                    return `
                        <a data-conversation-id="${conversation.id}" href="${conversation.url}" class="block border-b border-gray-mid px-14 py-10 transition hover:bg-gray-light ${selectedClass}">
                            <div class="mb-4 flex items-start justify-between gap-10">
                                <div class="min-w-0">
                                    <div class="flex min-w-0 items-center gap-8">
                                        <p class="truncate text-14 font-black leading-tight">${escapeHtml(conversation.name)}</p>
                                        ${needsHuman}
                                        ${unread}
                                    </div>
                                    <p class="mt-4 truncate text-12 font-semibold text-gray">${escapeHtml(conversation.subtitle)}</p>
                                </div>
                                <span class="shrink-0 rounded-full px-8 py-4 text-11 font-extrabold uppercase tracking-normal ${modeClass}">
                                    ${escapeHtml(conversation.mode)}
                                </span>
                            </div>
                            <p class="line-clamp-1 text-12 font-semibold leading-[18px] text-gray">${escapeHtml(conversation.latest_body)}</p>
                            <p class="mt-4 text-11 font-bold uppercase tracking-normal text-gray">${escapeHtml(conversation.last_message_at)}</p>
                        </a>
                    `;
                }).join('');
            }

            function renderMedia(media) {
                if (!media) {
                    return '';
                }

                if (media.kind === 'image') {
                    return `
                        <a href="${media.url}" target="_blank" class="block overflow-hidden rounded-10 bg-black/5">
                            <img src="${media.url}" alt="${escapeHtml(media.filename)}" class="max-h-[320px] w-full object-contain">
                        </a>
                    `;
                }

                if (media.kind === 'audio') {
                    return `
                        <audio controls class="w-[260px] max-w-full">
                            <source src="${media.url}" type="${escapeHtml(media.mime_type)}">
                        </audio>
                    `;
                }

                if (media.kind === 'video') {
                    return `
                        <video controls class="max-h-[320px] w-[320px] max-w-full rounded-10 bg-black">
                            <source src="${media.url}" type="${escapeHtml(media.mime_type)}">
                        </video>
                    `;
                }

                return `
                    <a href="${media.url}" target="_blank" class="flex items-center gap-12 rounded-10 bg-white/15 px-12 py-10 text-14 font-bold underline-offset-4 hover:underline">
                        <span>Documento</span>
                        <span class="truncate">${escapeHtml(media.filename || 'Apri allegato')}</span>
                    </a>
                `;
            }

            function renderMessages(messages) {
                const list = document.getElementById('message-list');
                const signature = messages.map((message) => [
                    message.id,
                    message.status_label,
                    message.delivered_at,
                    message.read_at,
                    message.error_message,
                ].join(':')).join('|');

                if (!list || signature === lastMessageSignature) {
                    return;
                }

                const wasNearBottom = list.scrollTop + list.clientHeight >= list.scrollHeight - 80;
                lastMessageSignature = signature;

                if (!messages.length) {
                    list.innerHTML = '<div class="flex h-full items-center justify-center"><p class="text-14 font-semibold text-gray">Nessun messaggio in questa chat.</p></div>';
                    return;
                }

                list.innerHTML = messages.map((message) => {
                    const outbound = message.direction === 'outbound';
                    const alignment = outbound ? 'justify-end' : 'justify-start';
                    const bubbleClass = outbound ? 'bg-bullstar text-white' : 'border border-gray-mid bg-white text-black-nike';
                    const metaClass = outbound ? 'text-white/70' : 'text-gray';
                    const statusMeta = outbound
                        ? `<span>${escapeHtml(message.status_label)} ${escapeHtml(message.status_at)}</span>`
                        : `<span>${escapeHtml(message.message_at)}</span><span>${escapeHtml(message.status_label)}</span>`;
                    const error = message.error_message
                        ? `<p class="mt-8 text-12 font-bold text-red-100">${escapeHtml(message.error_message)}</p>`
                        : '';
                    const media = renderMedia(message.media);
                    const body = message.body
                        ? `<p class="whitespace-pre-line text-14 font-semibold leading-[20px] ${message.media ? 'mt-10' : ''}">${escapeHtml(message.body)}</p>`
                        : message.media
                            ? ''
                            : `<p class="whitespace-pre-line text-14 font-semibold leading-[20px]">[${escapeHtml(message.type)}]</p>`;

                    return `
                        <div class="flex ${alignment}">
                            <div class="max-w-[78%] rounded-10 px-16 py-12 ${bubbleClass}">
                                ${media}
                                ${body}
                                <div class="mt-8 flex flex-wrap items-center gap-8 text-11 font-bold uppercase tracking-normal ${metaClass}">
                                    <span>${escapeHtml(message.source)}</span>
                                    ${statusMeta}
                                </div>
                                ${error}
                            </div>
                        </div>
                    `;
                }).join('');

                if (wasNearBottom) {
                    list.scrollTop = list.scrollHeight;
                }
            }

            async function pollConversation() {
                try {
                    const response = await fetch(pollUrl, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        return;
                    }

                    const data = await response.json();
                    renderStats(data.stats);
                    renderConversationList(data.conversations);
                    if (data.messages) {
                        renderMessages(data.messages);
                    }
                } catch (error) {
                    console.warn('Aggiornamento chat non riuscito', error);
                }
            }

            pollConversation();
            window.setInterval(pollConversation, 3000);
        </script>
</body>
</html>
