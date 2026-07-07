@extends('admin.layouts.app')

@section('title', 'Chat WhatsApp - Stuart Admin')
@section('page_title', 'WhatsApp')
@section('active_nav', 'whatsapp')

@section('content')
            @php
                $canManageWhatsapp = auth('admin')->user()?->hasAdminPermission('whatsapp.manage');
            @endphp

            <section class="{{ $selectedConversation ? 'hidden' : '' }} admin-stats-grid mb-12 md:mb-16">
                <article class="admin-stat-card rounded-10 border border-gray-mid bg-white p-12 md:p-16">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Conversazioni</p>
                    <p id="stat-total" class="mt-6 text-30 font-black leading-none tracking-normal md:mt-8 md:text-38">{{ $stats['total'] ?? 0 }}</p>
                </article>

                <article class="admin-stat-card rounded-10 border border-gray-mid bg-white p-12 md:p-16">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Automatiche</p>
                    <p id="stat-auto" class="mt-6 text-30 font-black leading-none tracking-normal md:mt-8 md:text-38">{{ $stats['auto'] ?? 0 }}</p>
                </article>

                <article class="admin-stat-card rounded-10 border border-gray-mid bg-white p-12 md:p-16">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Da prendere</p>
                    <p id="stat-needs-human" class="mt-6 text-30 font-black leading-none tracking-normal md:mt-8 md:text-38">{{ $stats['needs_human'] ?? 0 }}</p>
                </article>

                <article class="admin-stat-card rounded-10 border border-gray-mid bg-white p-12 md:p-16">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Follow-up dovuti</p>
                    <p id="stat-follow-ups-due" class="mt-6 text-30 font-black leading-none tracking-normal md:mt-8 md:text-38">{{ $stats['follow_ups_due'] ?? 0 }}</p>
                </article>

                <article class="admin-stat-card rounded-10 border border-gray-mid bg-white p-12 md:p-16">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Non letti</p>
                    <p id="stat-unread" class="mt-6 text-30 font-black leading-none tracking-normal md:mt-8 md:text-38">{{ $stats['unread'] ?? 0 }}</p>
                </article>
            </section>

            <section class="grid min-h-[calc(100vh-220px)] flex-1 gap-12">
                <aside class="{{ $selectedConversation ? 'hidden' : '' }} overflow-hidden rounded-10 border border-gray-mid bg-white">
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

                    <div id="conversation-list" class="max-h-[calc(100vh-250px)] overflow-y-auto">
                        @forelse ($conversations as $conversation)
                            @php
                                $latestMessage = $conversation->latestMessage;
                                $isSelected = ($selectedConversation?->id ?? null) === $conversation->id;
                            @endphp

                            <a
                                data-conversation-id="{{ $conversation->id }}"
                                href="{{ route('admin.conversations.show', $conversation) }}"
                                class="block border-b border-gray-mid px-14 py-12 transition hover:bg-gray-light md:py-10 {{ $isSelected ? 'bg-gray-light' : 'bg-white' }}"
                            >
                                <div class="mb-4 min-w-0">
                                    <div class="flex min-w-0 items-start justify-between gap-10">
                                        <p class="min-w-0 truncate text-14 font-black leading-tight">
                                            {{ $conversation->lead?->name ?: $conversation->contact_phone }}
                                        </p>

                                        <span class="shrink-0 rounded-full px-8 py-4 text-11 font-extrabold uppercase tracking-normal {{ $conversation->mode === 'manual' ? 'bg-bullstar/10 text-bullstar' : 'bg-whatsapp/10 text-whatsapp' }}">
                                            {{ $conversation->mode }}
                                        </span>
                                    </div>

                                    <div class="mt-6 flex min-w-0 flex-wrap items-center gap-5">
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
                                        @if (! $conversation->isExcludedFromFollowUps() && $conversation->due_follow_ups_count > 0)
                                            <span class="shrink-0 rounded-full bg-brand px-8 py-4 text-11 font-extrabold uppercase tracking-normal text-white">
                                                Da fare
                                            </span>
                                        @endif
                                        @if ($conversation->pending_follow_ups_count > 0)
                                            <span class="shrink-0 rounded-full bg-bullstar/10 px-8 py-4 text-11 font-extrabold uppercase tracking-normal text-bullstar">
                                                FU {{ $conversation->pending_follow_ups_count }}
                                            </span>
                                        @endif
                                        @if ($conversation->isExcludedFromFollowUps())
                                            <span class="shrink-0 rounded-full bg-gray-mid px-8 py-4 text-11 font-extrabold uppercase tracking-normal text-black-nike">
                                                Pausa FU
                                            </span>
                                        @endif
                                        @if ($conversation->isWhatsappWindowExpired())
                                            <span class="shrink-0 rounded-full bg-red-50 px-8 py-4 text-11 font-extrabold uppercase tracking-normal text-red-700">
                                                24h scadute
                                            </span>
                                        @endif
                                    </div>

                                    <p class="mt-4 truncate text-12 font-semibold text-gray">
                                        {{ $conversation->lead?->club ?: $conversation->contact_phone }}
                                    </p>
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
                                    {{ optional($conversation->last_message_at ?? $conversation->created_at)?->timezone(config('app.display_timezone'))->format('d/m/Y H:i') }}
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

                <section class="{{ $selectedConversation ? '' : 'hidden' }} overflow-hidden rounded-10 border border-gray-mid bg-white">
                    @if ($selectedConversation)
                        <div class="flex min-h-[calc(100vh-170px)] flex-col lg:min-h-[640px]">
                            <div class="border-b border-gray-mid px-10 py-10 md:px-12 md:py-10">
                                <div class="flex flex-col gap-12 xl:flex-row xl:items-center xl:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-8">
                                            <h2 class="min-w-0 truncate text-20 font-black leading-tight md:text-24">
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
                                            @if ($selectedConversation->isWhatsappWindowExpired())
                                                <span class="rounded-full bg-red-50 px-10 py-5 text-11 font-extrabold uppercase tracking-normal text-red-700">
                                                    24h scadute
                                                </span>
                                            @endif
                                            @if ($selectedConversation->isExcludedFromFollowUps())
                                                <span class="rounded-full bg-gray-mid px-10 py-5 text-11 font-extrabold uppercase tracking-normal text-black-nike">
                                                    Follow-up sospesi
                                                </span>
                                            @endif
                                        </div>
                                        @if ($selectedConversation->needs_human)
                                            <p class="mt-8 rounded-10 border border-brand/20 bg-brand/5 px-12 py-10 text-14 font-bold text-brand">
                                                {{ data_get($selectedConversation->metadata, 'handoff_reason', 'La chat richiede il tuo intervento.') }}
                                            </p>
                                        @endif
                                        <div class="mt-6 flex flex-wrap items-center gap-8">
                                            <a href="{{ route('admin.dashboard') }}" class="inline-flex h-28 w-fit shrink-0 items-center gap-6 rounded-10 border border-gray-mid px-10 text-12 font-extrabold uppercase tracking-normal text-black-nike transition hover:border-black-nike">
                                                <svg class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 6l-6 6 6 6" />
                                                </svg>
                                                <span>Inbox</span>
                                            </a>
                                            <p class="min-w-0 break-all text-13 font-semibold text-gray md:text-14">
                                                {{ $selectedConversation->contact_phone }}
                                                @if ($selectedConversation->lead?->email)
                                                    <span class="mx-6 text-gray-mid">/</span>{{ $selectedConversation->lead->email }}
                                                @endif
                                            </p>
                                        </div>
                                        @if ($selectedConversation->isExcludedFromFollowUps())
                                            <p class="mt-8 rounded-10 border border-gray-mid bg-gray-light px-12 py-10 text-14 font-bold text-gray">
                                                @if ($selectedConversation->follow_up_excluded_permanently)
                                                    Esclusa dai follow-up a tempo indeterminato.
                                                @else
                                                    Esclusa dai follow-up fino al {{ $selectedConversation->follow_up_excluded_until?->timezone(config('app.display_timezone'))->format('d/m/Y H:i') }}.
                                                @endif
                                                @if ($selectedConversation->follow_up_exclusion_reason)
                                                    <span class="block text-black-nike">{{ $selectedConversation->follow_up_exclusion_reason }}</span>
                                                @endif
                                            </p>
                                        @endif
                                        <p
                                            id="selected-follow-up-alert"
                                            class="{{ (! $selectedConversation->isExcludedFromFollowUps() && ($selectedConversation->due_follow_ups_count ?? 0) > 0) ? '' : 'hidden' }} mt-8 rounded-10 border border-brand/20 bg-brand/5 px-12 py-10 text-14 font-bold text-brand"
                                        >
                                            Follow-up da fare: questa chat ha <span id="selected-follow-up-alert-count">{{ $selectedConversation->due_follow_ups_count ?? 0 }}</span> promemoria scaduti.
                                        </p>
                                    </div>

                                    @if ($canManageWhatsapp)
                                        <div class="grid grid-cols-3 gap-6 md:flex md:flex-nowrap md:gap-8">
                                            <form method="POST" action="{{ route('admin.conversations.mark-unread', $selectedConversation) }}" class="min-w-0">
                                                @csrf
                                                @method('PATCH')
                                                <button
                                                    type="submit"
                                                    class="w-full rounded-10 border border-gray-mid bg-white px-8 py-8 text-11 font-extrabold uppercase tracking-normal transition hover:border-black-nike md:w-auto md:px-14 md:py-10 md:text-12"
                                                >
                                                    Da leggere
                                                </button>
                                            </form>

                                            @if ($selectedConversation->manual_started_at && $selectedConversation->mode !== 'auto')
                                                <button
                                                    type="button"
                                                    disabled
                                                    class="w-full rounded-10 border border-gray-mid bg-gray-light px-8 py-8 text-11 font-extrabold uppercase tracking-normal text-gray md:w-auto md:px-14 md:py-10 md:text-12"
                                                    title="Chat già passata in manuale"
                                                >
                                                    Auto
                                                </button>
                                            @else
                                                <form method="POST" action="{{ route('admin.conversations.mode', $selectedConversation) }}" class="min-w-0">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="mode" value="auto">
                                                    <button
                                                        type="submit"
                                                        class="w-full rounded-10 border px-8 py-8 text-11 font-extrabold uppercase tracking-normal transition md:w-auto md:px-14 md:py-10 md:text-12 {{ $selectedConversation->mode === 'auto' ? 'border-whatsapp bg-whatsapp text-white' : 'border-gray-mid bg-white hover:border-whatsapp' }}"
                                                    >
                                                        Auto
                                                    </button>
                                                </form>
                                            @endif

                                            @if ($selectedConversation->mode === 'manual')
                                                <button
                                                    type="button"
                                                    disabled
                                                    class="w-full rounded-10 border border-bullstar bg-bullstar px-8 py-8 text-11 font-extrabold uppercase tracking-normal text-white md:w-auto md:px-14 md:py-10 md:text-12"
                                                >
                                                    Manuale
                                                </button>
                                            @else
                                                <form method="POST" action="{{ route('admin.conversations.mode', $selectedConversation) }}" class="min-w-0">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="mode" value="manual">
                                                    <button
                                                        type="submit"
                                                        class="w-full rounded-10 border border-gray-mid bg-white px-8 py-8 text-11 font-extrabold uppercase tracking-normal transition hover:border-bullstar md:w-auto md:px-14 md:py-10 md:text-12"
                                                    >
                                                        Manuale
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <details id="follow-up-panel" class="admin-follow-up-panel border-b border-gray-mid bg-white">
                                <summary class="admin-follow-up-summary flex items-center justify-between gap-12 px-12 py-10 text-12 font-extrabold uppercase tracking-normal text-gray md:hidden">
                                    <span>Follow-up</span>
                                    <span class="flex items-center gap-8">
                                        <span class="rounded-full bg-gray-light px-8 py-4 text-11 text-black-nike">{{ $selectedConversation->followUps->count() }}</span>
                                        <svg class="admin-follow-up-chevron h-10 w-10 text-black-nike transition" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6" />
                                        </svg>
                                    </span>
                                </summary>

                                <div class="admin-follow-up-grid px-12 py-10 md:px-12 md:py-8">
                                <div class="h-full rounded-10 border border-gray-mid bg-gray-light p-8 md:p-10">
                                    <div class="mb-8 flex items-center justify-between gap-12">
                                        <div>
                                            <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Follow-up programmati</p>
                                            <p class="mt-3 text-12 font-semibold text-gray">I follow-up in pausa restano in attesa finché l’esclusione scade.</p>
                                        </div>
                                    </div>

                                    <div id="follow-up-list" class="admin-follow-up-list space-y-6">
                                        @forelse ($selectedConversation->followUps as $followUp)
                                            @php
                                                $isFollowUpDue = $followUp->status === 'pending' && $followUp->due_at->isPast();
                                            @endphp
                                            <div class="rounded-10 border border-gray-mid bg-white px-10 py-7">
                                                <div class="flex flex-col gap-8 md:flex-row md:items-start md:justify-between">
                                                    <div class="min-w-0">
                                                        <div class="flex flex-wrap items-center gap-8">
                                                            <span class="rounded-full px-8 py-4 text-11 font-extrabold uppercase tracking-normal {{ match ($followUp->status) {
                                                                'sent' => 'bg-whatsapp/10 text-whatsapp',
                                                                'failed' => 'bg-red-50 text-red-700',
                                                                'cancelled' => 'bg-gray-mid text-black-nike',
                                                                default => $isFollowUpDue ? 'bg-brand text-white' : 'bg-bullstar/10 text-bullstar',
                                                            } }}">
                                                                {{ match ($followUp->status) {
                                                                    'sent' => 'Inviato',
                                                                    'failed' => 'Errore',
                                                                    'cancelled' => 'Annullato',
                                                                    default => $isFollowUpDue ? 'Da fare' : 'Programmato',
                                                                } }}
                                                            </span>
                                                            @if ($followUp->auto_generated)
                                                                <span class="rounded-full bg-white px-8 py-4 text-11 font-extrabold uppercase tracking-normal text-gray">
                                                                    Auto
                                                                </span>
                                                            @endif
                                                            <span class="text-12 font-bold uppercase tracking-normal text-gray">{{ $followUp->due_at->timezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</span>
                                                        </div>
                                                        <p class="admin-line-clamp-2 mt-6 text-14 font-semibold leading-[20px] text-black-nike">{{ $followUp->body }}</p>
                                                        @if ($followUp->error_message)
                                                            <p class="mt-6 text-12 font-bold text-red-700">{{ $followUp->error_message }}</p>
                                                        @endif
                                                    </div>

                                                    @if ($canManageWhatsapp && $followUp->status === 'pending')
                                                        <form method="POST" action="{{ route('admin.conversations.follow-ups.cancel', [$selectedConversation, $followUp]) }}">
                                                            @csrf
                                                            @method('PATCH')
                                                            <button type="submit" class="rounded-10 border border-gray-mid bg-white px-10 py-8 text-11 font-extrabold uppercase tracking-normal transition hover:border-red-400 hover:text-red-700">
                                                                Annulla
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </div>
                                        @empty
                                            <div class="rounded-10 border border-dashed border-gray-mid bg-white px-12 py-10 text-14 font-semibold text-gray">
                                                Nessun follow-up programmato.
                                            </div>
                                        @endforelse
                                    </div>
                                </div>

                                @if ($canManageWhatsapp)
                                    @php
                                        $canCreateFollowUps = ! in_array($selectedConversation->lead?->status, ['completed', 'order_completed', 'lost'], true);
                                    @endphp
                                    <div class="h-full space-y-8">
                                        @if ($canCreateFollowUps)
                                            <form method="POST" action="{{ route('admin.conversations.follow-ups.store', $selectedConversation) }}" class="rounded-10 border border-gray-mid bg-white p-8">
                                                @csrf
                                                <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Nuovo follow-up</p>
                                                <input
                                                    name="due_at"
                                                    type="datetime-local"
                                                    value="{{ old('due_at') }}"
                                                    class="mt-4 h-32 w-full rounded-10 border-gray-mid px-10 py-5 text-12 font-semibold focus:border-bullstar focus:ring-bullstar"
                                                >
                                                <textarea
                                                    name="body"
                                                    rows="1"
                                                    maxlength="4096"
                                                    class="mt-4 min-h-32 w-full resize-none rounded-10 border-gray-mid px-10 py-5 text-12 font-semibold focus:border-bullstar focus:ring-bullstar"
                                                    placeholder="Messaggio follow-up..."
                                                >{{ old('body') }}</textarea>
                                                <button type="submit" class="mt-4 w-full rounded-10 bg-bullstar px-12 py-6 text-12 font-extrabold uppercase tracking-normal text-white transition hover:bg-bullstar-hover">
                                                    Programma
                                                </button>
                                            </form>
                                        @else
                                            <div class="rounded-10 border border-gray-mid bg-white p-8 text-12 font-bold leading-[18px] text-gray">
                                                Follow-up disattivati per questo lead.
                                            </div>
                                        @endif

                                        <form method="POST" action="{{ route('admin.conversations.follow-up-exclusion', $selectedConversation) }}" class="rounded-10 border border-gray-mid bg-white p-8">
                                            @csrf
                                            @method('PATCH')
                                            <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Esclusione follow-up</p>
                                            <select name="exclusion_type" class="mt-4 h-32 w-full rounded-10 border-gray-mid px-10 py-5 text-12 font-semibold focus:border-bullstar focus:ring-bullstar">
                                                <option value="none">Attiva follow-up</option>
                                                <option value="until" @selected($selectedConversation->follow_up_excluded_until && ! $selectedConversation->follow_up_excluded_permanently)>Escludi fino a data</option>
                                                <option value="permanent" @selected($selectedConversation->follow_up_excluded_permanently)>Escludi a tempo indeterminato</option>
                                            </select>
                                            <input
                                                name="excluded_until"
                                                type="datetime-local"
                                                value="{{ old('excluded_until', $selectedConversation->follow_up_excluded_until?->timezone(config('app.display_timezone'))->format('Y-m-d\TH:i')) }}"
                                                class="mt-4 h-32 w-full rounded-10 border-gray-mid px-10 py-5 text-12 font-semibold focus:border-bullstar focus:ring-bullstar"
                                            >
                                            <textarea
                                                name="reason"
                                                rows="2"
                                                class="mt-4 min-h-32 w-full resize-none rounded-10 border-gray-mid px-10 py-5 text-12 font-semibold focus:border-bullstar focus:ring-bullstar"
                                                placeholder="Motivo interno..."
                                            >{{ old('reason', $selectedConversation->follow_up_exclusion_reason) }}</textarea>
                                            <button type="submit" class="mt-4 w-full rounded-10 border border-black-nike bg-white px-12 py-6 text-12 font-extrabold uppercase tracking-normal text-black-nike transition hover:bg-gray-light">
                                                Salva esclusione
                                            </button>
                                        </form>
                                    </div>
                                @endif
                                </div>
                            </details>

                            <div id="message-list" class="admin-message-list flex-1 space-y-10 bg-gray-light px-10 py-14 md:space-y-12 md:px-24 md:py-20">
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
                                        <div class="max-w-[92%] rounded-10 px-12 py-10 md:max-w-[78%] md:px-16 md:py-12 {{ $isOutbound ? 'bg-bullstar text-white' : 'border border-gray-mid bg-white text-black-nike' }}">
                                            @if ($message->media_path)
                                                @php
                                                    $mediaUrl = route('admin.messages.media', $message);
                                                    $mediaKind = str_starts_with((string) $message->media_mime_type, 'image/')
                                                        ? 'image'
                                                        : (str_starts_with((string) $message->media_mime_type, 'audio/')
                                                            ? 'audio'
                                                            : (str_starts_with((string) $message->media_mime_type, 'video/') ? 'video' : 'document'));
                                                @endphp

                                                @if ($mediaKind === 'image')
                                                    <a href="{{ $mediaUrl }}" target="_blank" class="block overflow-hidden rounded-10 bg-black/5">
                                                        <img src="{{ $mediaUrl }}" alt="{{ $message->media_filename ?: 'Immagine WhatsApp' }}" class="max-h-[320px] w-full object-contain">
                                                    </a>
                                                @elseif ($mediaKind === 'audio')
                                                    <audio controls class="w-[260px] max-w-full">
                                                        <source src="{{ $mediaUrl }}" type="{{ $message->media_mime_type }}">
                                                    </audio>
                                                @elseif ($mediaKind === 'video')
                                                    <video controls class="max-h-[320px] w-[320px] max-w-full rounded-10 bg-black">
                                                        <source src="{{ $mediaUrl }}" type="{{ $message->media_mime_type }}">
                                                    </video>
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
                                                    @if ($message->sent_at)
                                                        <span>Inviato {{ $message->sent_at->timezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</span>
                                                    @endif
                                                    @if ($message->delivered_at)
                                                        <span>Consegnato {{ $message->delivered_at->timezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</span>
                                                    @endif
                                                    @if ($message->read_at)
                                                        <span>Letto {{ $message->read_at->timezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</span>
                                                    @endif
                                                    @unless ($message->sent_at || $message->delivered_at || $message->read_at)
                                                        <span>{{ $statusLabel }} {{ optional($message->created_at)?->timezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</span>
                                                    @endunless
                                                @else
                                                    <span>{{ optional($message->received_at ?? $message->created_at)?->timezone(config('app.display_timezone'))->format('d/m/Y H:i') }}</span>
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
                                <div id="message-list-bottom" class="h-px" aria-hidden="true"></div>
                            </div>

                            <div class="border-t border-gray-mid bg-white p-12 md:p-16">
                                @if ($selectedConversation->mode === 'manual' && $canManageWhatsapp)
                                    @php
                                        $messageTemplates = \App\Support\MessageTemplates::current();
                                        $whatsappApprovedTemplates = config('whatsapp_templates.templates', []);
                                        $messageTemplateMessages = collect($messageTemplates)
                                            ->pluck('message')
                                            ->map(fn ($message) => str_replace('\n', "\n", $message))
                                            ->values();
                                    @endphp
                                    <form method="POST" action="{{ route('admin.conversations.messages.store', $selectedConversation) }}" enctype="multipart/form-data" class="flex flex-col gap-12">
                                        @csrf
                                        <div class="flex max-h-96 flex-wrap gap-8 overflow-y-auto pr-2 md:max-h-none md:overflow-visible">
                                            @foreach ($messageTemplates as $template)
                                                <button
                                                    type="button"
                                                    data-message-template-index="{{ $loop->index }}"
                                                    class="rounded-10 border border-gray-mid bg-gray-light px-10 py-6 text-left text-12 font-bold leading-[18px] text-black-nike transition hover:border-bullstar hover:text-bullstar"
                                                >
                                                    {{ $template['title'] }}
                                                </button>
                                            @endforeach
                                        </div>

                                        @if (! empty($whatsappApprovedTemplates))
                                            <label class="block">
                                                <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Modello WhatsApp approvato</span>
                                                <select
                                                    id="whatsapp-template-select"
                                                    name="whatsapp_template"
                                                    class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold text-black-nike focus:border-bullstar focus:ring-bullstar"
                                                >
                                                    <option value="">Messaggio libero</option>
                                                    @foreach ($whatsappApprovedTemplates as $key => $template)
                                                        <option value="{{ $key }}" @selected(old('whatsapp_template') === $key)>
                                                            {{ $template['label'] ?? $template['name'] ?? $key }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </label>
                                        @endif

                                        <textarea
                                            id="message-composer"
                                            name="message"
                                            rows="4"
                                            maxlength="4096"
                                            class="min-h-[130px] resize-none rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold text-black-nike placeholder:text-gray focus:border-bullstar focus:ring-bullstar md:min-h-[172px] md:px-16 md:py-12"
                                            placeholder="Scrivi un messaggio o una didascalia..."
                                        >{{ old('message') }}</textarea>

                                        <div class="flex flex-col gap-10 md:flex-row md:items-center md:justify-between">
                                            <label class="flex min-w-0 flex-1 items-center gap-10 rounded-10 border border-gray-mid bg-gray-light px-12 py-10 text-12 font-bold text-gray">
                                                <span class="shrink-0 rounded-10 bg-white px-10 py-6 text-black-nike">Allega</span>
                                                <input
                                                    id="message-attachment"
                                                    name="attachments[]"
                                                    type="file"
                                                    multiple
                                                    accept="image/*,audio/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.txt"
                                                    class="min-w-0 flex-1 text-12 file:hidden"
                                                >
                                            </label>

                                            <input id="voice-attachment" name="attachments[]" type="file" accept="audio/*" class="hidden">

                                            <div id="voice-recorder" class="relative min-w-0 select-none md:min-w-[260px]">
                                                <button
                                                    type="button"
                                                    id="voice-record-button"
                                                    style="touch-action: none; user-select: none; -webkit-user-select: none; -webkit-touch-callout: none;"
                                                    class="w-full select-none rounded-10 bg-bullstar px-24 py-12 text-14 font-extrabold uppercase tracking-normal text-white transition hover:bg-bullstar-hover active:scale-[0.98] active:bg-whatsapp disabled:cursor-not-allowed disabled:bg-gray disabled:text-white/70 md:w-auto"
                                                    draggable="false"
                                                >
                                                    Tieni premuto per nota vocale
                                                </button>
                                                <div id="voice-preview" class="mt-6 hidden min-w-0 rounded-10 border border-gray-mid bg-gray-light px-10 py-8">
                                                    <audio id="voice-preview-audio" controls class="w-full max-w-[220px]"></audio>
                                                    <button type="button" id="voice-clear-button" class="mt-6 text-11 font-extrabold uppercase tracking-normal text-red-700">
                                                        Rimuovi audio
                                                    </button>
                                                </div>
                                            </div>

                                            <button
                                                type="submit"
                                                class="rounded-10 bg-bullstar px-24 py-12 text-14 font-extrabold uppercase tracking-normal text-white transition hover:bg-bullstar-hover md:w-auto"
                                            >
                                                Invia
                                            </button>
                                        </div>
                                    </form>
                                @else
                                    <div class="rounded-10 border border-dashed border-gray-mid bg-gray-light px-16 py-14 text-14 font-semibold text-gray">
                                        {{ $canManageWhatsapp ? 'La chat è in automatico. Passala in manuale per scrivere dal pannello admin.' : 'Il tuo utente può leggere questa chat, ma non può modificarla o inviare messaggi.' }}
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
@endsection

@push('scripts')
    <script>
            const selectedConversationId = @json($selectedConversation?->id);
            const pollUrl = @json($selectedConversation ? route('admin.conversations.poll', $selectedConversation) : route('admin.dashboard.poll'));
            const csrfToken = @json(csrf_token());
            const messageTemplates = @json($messageTemplateMessages ?? []);
            const canManageWhatsapp = @json($canManageWhatsapp);
            let lastMessageSignature = '';
            let lastFollowUpSignature = '';
            let shouldScrollToLatestMessage = Boolean(selectedConversationId);

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
                document.getElementById('stat-follow-ups-due').textContent = stats.follow_ups_due;
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
                    const dueFollowUps = conversation.due_follow_ups_count > 0
                        ? '<span class="shrink-0 rounded-full bg-brand px-8 py-4 text-11 font-extrabold uppercase tracking-normal text-white">Da fare</span>'
                        : '';
                    const followUps = conversation.pending_follow_ups_count > 0
                        ? `<span class="shrink-0 rounded-full bg-bullstar/10 px-8 py-4 text-11 font-extrabold uppercase tracking-normal text-bullstar">FU ${conversation.pending_follow_ups_count}</span>`
                        : '';
                    const followUpExcluded = conversation.follow_up_excluded
                        ? '<span class="shrink-0 rounded-full bg-gray-mid px-8 py-4 text-11 font-extrabold uppercase tracking-normal text-black-nike">Pausa FU</span>'
                        : '';
                    const whatsappWindowExpired = conversation.whatsapp_window_expired
                        ? '<span class="shrink-0 rounded-full bg-red-50 px-8 py-4 text-11 font-extrabold uppercase tracking-normal text-red-700">24h scadute</span>'
                        : '';
                    const modeClass = conversation.mode === 'manual' ? 'bg-bullstar/10 text-bullstar' : 'bg-whatsapp/10 text-whatsapp';

                    return `
                        <a data-conversation-id="${conversation.id}" href="${conversation.url}" class="block border-b border-gray-mid px-14 py-10 transition hover:bg-gray-light ${selectedClass}">
                            <div class="mb-4 min-w-0">
                                <div class="flex min-w-0 items-start justify-between gap-10">
                                    <p class="min-w-0 truncate text-14 font-black leading-tight">${escapeHtml(conversation.name)}</p>
                                    <span class="shrink-0 rounded-full px-8 py-4 text-11 font-extrabold uppercase tracking-normal ${modeClass}">
                                        ${escapeHtml(conversation.mode)}
                                    </span>
                                </div>
                                <div class="mt-6 flex min-w-0 flex-wrap items-center gap-5">
                                    ${needsHuman}
                                    ${unread}
                                    ${dueFollowUps}
                                    ${followUps}
                                    ${followUpExcluded}
                                    ${whatsappWindowExpired}
                                </div>
                                <p class="mt-4 truncate text-12 font-semibold text-gray">${escapeHtml(conversation.subtitle)}</p>
                            </div>
                            <p class="line-clamp-1 text-12 font-semibold leading-[18px] text-gray">${escapeHtml(conversation.latest_body)}</p>
                            <p class="mt-4 text-11 font-bold uppercase tracking-normal text-gray">${escapeHtml(conversation.last_message_at)}</p>
                        </a>
                    `;
                }).join('');
            }

            function renderSelectedFollowUpAlert(conversation) {
                const alert = document.getElementById('selected-follow-up-alert');
                const count = document.getElementById('selected-follow-up-alert-count');

                if (!alert || !count || !conversation) {
                    return;
                }

                const dueCount = Number(conversation.due_follow_ups_count || 0);
                count.textContent = dueCount;
                alert.classList.toggle('hidden', dueCount <= 0);
            }

            function renderFollowUps(followUps) {
                const list = document.getElementById('follow-up-list');

                if (!list || !Array.isArray(followUps)) {
                    return;
                }

                const signature = followUps.map((followUp) => [
                    followUp.id,
                    followUp.status,
                    followUp.status_label,
                    followUp.due_at,
                    followUp.body,
                    followUp.error_message,
                ].join(':')).join('|');

                if (signature === lastFollowUpSignature) {
                    return;
                }

                lastFollowUpSignature = signature;

                if (!followUps.length) {
                    list.innerHTML = `
                        <div class="rounded-10 border border-dashed border-gray-mid bg-white px-12 py-10 text-14 font-semibold text-gray">
                            Nessun follow-up programmato.
                        </div>
                    `;
                    return;
                }

                list.innerHTML = followUps.map((followUp) => {
                    const autoBadge = followUp.auto_generated
                        ? '<span class="rounded-full bg-white px-8 py-4 text-11 font-extrabold uppercase tracking-normal text-gray">Auto</span>'
                        : '';
                    const error = followUp.error_message
                        ? `<p class="mt-6 text-12 font-bold text-red-700">${escapeHtml(followUp.error_message)}</p>`
                        : '';
                    const cancelForm = canManageWhatsapp && followUp.is_pending
                        ? `
                            <form method="POST" action="${followUp.cancel_url}">
                                <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
                                <input type="hidden" name="_method" value="PATCH">
                                <button type="submit" class="rounded-10 border border-gray-mid bg-white px-10 py-8 text-11 font-extrabold uppercase tracking-normal transition hover:border-red-400 hover:text-red-700">
                                    Annulla
                                </button>
                            </form>
                        `
                        : '';

                    return `
                        <div class="rounded-10 border border-gray-mid bg-white px-12 py-10">
                            <div class="flex flex-col gap-8 md:flex-row md:items-start md:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-8">
                                        <span class="rounded-full px-8 py-4 text-11 font-extrabold uppercase tracking-normal ${escapeHtml(followUp.status_class)}">
                                            ${escapeHtml(followUp.status_label)}
                                        </span>
                                        ${autoBadge}
                                        <span class="text-12 font-bold uppercase tracking-normal text-gray">${escapeHtml(followUp.due_at)}</span>
                                    </div>
                                    <p class="admin-line-clamp-2 mt-6 text-14 font-semibold leading-[20px] text-black-nike">${escapeHtml(followUp.body)}</p>
                                    ${error}
                                </div>
                                ${cancelForm}
                            </div>
                        </div>
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
                    message.sent_at,
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
                    const outboundTimeline = [
                        message.sent_at ? `<span>Inviato ${escapeHtml(message.sent_at)}</span>` : '',
                        message.delivered_at ? `<span>Consegnato ${escapeHtml(message.delivered_at)}</span>` : '',
                        message.read_at ? `<span>Letto ${escapeHtml(message.read_at)}</span>` : '',
                    ].filter(Boolean).join('');
                    const statusMeta = outbound
                        ? (outboundTimeline || `<span>${escapeHtml(message.status_label)} ${escapeHtml(message.status_at)}</span>`)
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
                            <div class="max-w-[92%] rounded-10 px-12 py-10 md:max-w-[78%] md:px-16 md:py-12 ${bubbleClass}">
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
                }).join('') + '<div id="message-list-bottom" class="h-px" aria-hidden="true"></div>';

                if (wasNearBottom || shouldScrollToLatestMessage) {
                    scrollMessagesToBottom();
                    shouldScrollToLatestMessage = false;
                }
            }

            function scrollMessagesToBottom() {
                const list = document.getElementById('message-list');
                const bottom = document.getElementById('message-list-bottom');

                if (list) {
                    const scroll = () => {
                        if (bottom) {
                            bottom.scrollIntoView({ block: 'end' });
                        }

                        list.scrollTop = list.scrollHeight;
                    };

                    window.requestAnimationFrame(scroll);
                    window.setTimeout(scroll, 80);
                    window.setTimeout(scroll, 220);
                    window.setTimeout(scroll, 500);
                }
            }

            function bindMessageTemplates() {
                const composer = document.getElementById('message-composer');
                const templateSelect = document.getElementById('whatsapp-template-select');
                const attachment = document.getElementById('message-attachment');
                const voiceAttachment = document.getElementById('voice-attachment');
                const voiceButton = document.getElementById('voice-record-button');

                if (!composer) {
                    return;
                }

                const syncApprovedTemplateComposer = () => {
                    const hasApprovedTemplate = templateSelect && templateSelect.value !== '';

                    composer.disabled = hasApprovedTemplate;
                    composer.classList.toggle('bg-gray-light', hasApprovedTemplate);
                    composer.placeholder = hasApprovedTemplate
                        ? 'Il testo viene preso dal modello WhatsApp approvato.'
                        : 'Scrivi un messaggio o una didascalia...';

                    if (hasApprovedTemplate) {
                        composer.value = '';
                    }

                    if (attachment) {
                        attachment.disabled = hasApprovedTemplate;
                        if (hasApprovedTemplate) {
                            attachment.value = '';
                        }
                    }

                    if (voiceButton) {
                        voiceButton.disabled = hasApprovedTemplate;
                    }

                    if (voiceAttachment && hasApprovedTemplate) {
                        voiceAttachment.value = '';
                        document.getElementById('voice-clear-button')?.click();
                    }
                };

                templateSelect?.addEventListener('change', syncApprovedTemplateComposer);
                syncApprovedTemplateComposer();

                document.querySelectorAll('[data-message-template-index]').forEach((button) => {
                    button.addEventListener('click', () => {
                        if (templateSelect) {
                            templateSelect.value = '';
                            syncApprovedTemplateComposer();
                        }
                        composer.value = messageTemplates[Number(button.dataset.messageTemplateIndex)] || '';
                        composer.focus();
                    });
                });
            }

            function bindVoiceRecorder() {
                const button = document.getElementById('voice-record-button');
                const input = document.getElementById('voice-attachment');
                const preview = document.getElementById('voice-preview');
                const audio = document.getElementById('voice-preview-audio');
                const clearButton = document.getElementById('voice-clear-button');
                const defaultButtonText = button?.textContent.trim() || 'Tieni premuto per nota vocale';

                if (!button || !input || !window.MediaRecorder || !navigator.mediaDevices?.getUserMedia) {
                    if (button) {
                        button.textContent = 'Registrazione non supportata';
                        button.disabled = true;
                    }

                    return;
                }

                let recorder = null;
                let stream = null;
                let chunks = [];
                let recordingStartedAt = null;
                let previewUrl = null;

                const recordingMimeType = () => [
                    'audio/mp4',
                    'audio/aac',
                    'audio/ogg;codecs=opus',
                    'audio/webm;codecs=opus',
                ].find((type) => MediaRecorder.isTypeSupported(type)) || '';

                const setStatus = (message, active = false) => {
                    button.textContent = message || defaultButtonText;
                    button.classList.toggle('border-whatsapp', active);
                    button.classList.toggle('bg-whatsapp', active);
                    button.classList.toggle('text-white', active);
                };

                const clearRecording = () => {
                    input.value = '';

                    if (previewUrl) {
                        URL.revokeObjectURL(previewUrl);
                        previewUrl = null;
                    }

                    if (audio) {
                        audio.removeAttribute('src');
                        audio.load();
                    }

                    preview?.classList.add('hidden');
                    setStatus('');
                };

                const stopStream = () => {
                    stream?.getTracks().forEach((track) => track.stop());
                    stream = null;
                };

                const startRecording = async (event) => {
                    event.preventDefault();
                    button.setPointerCapture?.(event.pointerId);

                    if (button.disabled || recorder?.state === 'recording') {
                        return;
                    }

                    clearRecording();

                    try {
                        stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                        const mimeType = recordingMimeType();
                        recorder = new MediaRecorder(stream, mimeType ? { mimeType } : undefined);
                        chunks = [];
                        recordingStartedAt = Date.now();

                        recorder.addEventListener('dataavailable', (recordEvent) => {
                            if (recordEvent.data.size > 0) {
                                chunks.push(recordEvent.data);
                            }
                        });

                        recorder.addEventListener('stop', () => {
                            stopStream();

                            if (!chunks.length || Date.now() - recordingStartedAt < 400) {
                                setStatus('Registrazione troppo breve.');
                                return;
                            }

                            const blobType = recorder.mimeType || mimeType || 'audio/ogg';
                            const extension = blobType.includes('aac')
                                ? 'aac'
                                : (blobType.includes('webm') ? 'webm' : (blobType.includes('mp4') ? 'm4a' : 'ogg'));
                            const blob = new Blob(chunks, { type: blobType });
                            const file = new File([blob], `nota-vocale-${Date.now()}.${extension}`, { type: blobType });
                            const transfer = new DataTransfer();

                            transfer.items.add(file);
                            input.files = transfer.files;
                            previewUrl = URL.createObjectURL(blob);
                            audio.src = previewUrl;
                            preview?.classList.remove('hidden');
                            setStatus('Nota vocale pronta.');
                        });

                        recorder.start();
                        setStatus('Registrazione in corso...', true);
                    } catch (error) {
                        stopStream();
                        setStatus('Microfono non disponibile o permesso negato.');
                    }
                };

                const stopRecording = (event) => {
                    event.preventDefault();
                    if (button.hasPointerCapture?.(event.pointerId)) {
                        button.releasePointerCapture(event.pointerId);
                    }

                    if (recorder?.state === 'recording') {
                        recorder.stop();
                    }
                };

                button.addEventListener('pointerdown', startRecording);
                button.addEventListener('pointerup', stopRecording);
                button.addEventListener('pointercancel', stopRecording);
                button.addEventListener('contextmenu', (event) => event.preventDefault());
                button.addEventListener('selectstart', (event) => event.preventDefault());
                button.addEventListener('lostpointercapture', (event) => {
                    if (recorder?.state === 'recording') {
                        stopRecording(event);
                    }
                });
                clearButton?.addEventListener('click', clearRecording);
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
                    renderSelectedFollowUpAlert(data.selected_conversation);
                    if (data.follow_ups) {
                        renderFollowUps(data.follow_ups);
                    }
                    if (data.messages) {
                        renderMessages(data.messages);
                    }
                } catch (error) {
                    console.warn('Aggiornamento chat non riuscito', error);
                }
            }

            function initFollowUpPanel() {
                const panel = document.getElementById('follow-up-panel');

                if (panel && window.matchMedia('(min-width: 768px)').matches) {
                    panel.open = true;
                }
            }

            bindMessageTemplates();
            bindVoiceRecorder();
            initFollowUpPanel();
            scrollMessagesToBottom();
            window.addEventListener('load', scrollMessagesToBottom, { once: true });
            document.querySelectorAll('#message-list img').forEach((image) => {
                image.addEventListener('load', scrollMessagesToBottom, { once: true });
            });
            pollConversation();
            window.setInterval(pollConversation, 3000);
    </script>
@endpush
