@extends('admin.layouts.app')

@section('title', 'Email - Stuart Admin')
@section('page_title', 'Email')
@section('active_nav', 'email')

@php
    $hasAccount = (bool) $account;
    $selectedMessages = $selectedConversation?->messages ?? collect();
    $emailTemplates = config('message_templates');
@endphp

@section('content')
    <div class="grid gap-16 xl:grid-cols-[320px_minmax(0,1fr)_360px]">
        <aside class="overflow-hidden rounded-10 border border-gray-mid bg-white">
            <div class="border-b border-gray-mid px-16 py-12">
                <p class="text-12 font-extrabold uppercase tracking-normal text-bullstar">Conversazioni</p>
                <h2 class="mt-4 text-20 font-black leading-none tracking-normal">Posta clienti</h2>
            </div>

            @if (! $hasAccount)
                <div class="p-16 text-14 font-semibold leading-[22px] text-gray">
                    Configura la tua casella per iniziare a inviare email dal pannello.
                </div>
            @elseif ($conversations->isEmpty())
                <div class="p-16 text-14 font-semibold leading-[22px] text-gray">
                    Nessuna conversazione email ancora presente.
                </div>
            @else
                <div class="divide-y divide-gray-mid">
                    @foreach ($conversations as $conversation)
                        @php
                            $isActive = $selectedConversation?->id === $conversation->id;
                            $preview = $conversation->latestMessage?->body_text ?: $conversation->subject;
                        @endphp
                        <a
                            href="{{ route('admin.email.conversations.show', $conversation) }}"
                            class="block px-12 py-12 transition {{ $isActive ? 'bg-bullstar text-white' : 'bg-white text-black-nike hover:bg-gray-light' }}"
                        >
                            <div class="flex min-w-0 items-start justify-between gap-10">
                                <div class="min-w-0">
                                    <p class="truncate text-14 font-black leading-[18px]">
                                        {{ $conversation->contact_name ?: $conversation->contact_email }}
                                    </p>
                                    <p class="mt-4 truncate text-12 font-bold leading-[16px] {{ $isActive ? 'text-white/80' : 'text-gray' }}">
                                        {{ $conversation->subject }}
                                    </p>
                                </div>
                                @if (! $conversation->is_seen)
                                    <span class="mt-2 h-10 w-10 shrink-0 rounded-full {{ $isActive ? 'bg-white' : 'bg-bullstar' }}"></span>
                                @endif
                            </div>
                            @if ($preview)
                                <p class="admin-line-clamp-2 mt-8 text-12 font-semibold leading-[18px] {{ $isActive ? 'text-white/80' : 'text-gray' }}">
                                    {{ $preview }}
                                </p>
                            @endif
                            <div class="mt-8 flex items-center justify-between gap-8 text-11 font-extrabold uppercase tracking-normal {{ $isActive ? 'text-white/70' : 'text-gray' }}">
                                <span>{{ $conversation->last_message_at?->format('d/m/Y H:i') }}</span>
                                @if ($conversation->lead)
                                    <span class="truncate">Lead #{{ $conversation->lead->id }}</span>
                                @endif
                            </div>
                        </a>
                    @endforeach
                </div>

                @if (method_exists($conversations, 'links'))
                    <div class="border-t border-gray-mid px-12 py-10">
                        {{ $conversations->links() }}
                    </div>
                @endif
            @endif
        </aside>

        <section class="overflow-hidden rounded-10 border border-gray-mid bg-white">
            @if (! $selectedConversation)
                <div class="flex min-h-[640px] items-center justify-center p-24 text-center">
                    <div>
                        <p class="text-12 font-extrabold uppercase tracking-normal text-bullstar">Email</p>
                        <h2 class="mt-6 text-24 font-black leading-none tracking-normal">Seleziona o crea una conversazione</h2>
                        <p class="mt-10 max-w-[420px] text-14 font-semibold leading-[22px] text-gray">
                            Le email inviate dal pannello verranno salvate qui e potranno essere associate a un lead.
                        </p>
                    </div>
                </div>
            @else
                <div class="border-b border-gray-mid px-16 py-16">
                    <div class="flex flex-col gap-8 md:flex-row md:items-start md:justify-between">
                        <div class="min-w-0">
                            <p class="text-12 font-extrabold uppercase tracking-normal text-bullstar">{{ $selectedConversation->contact_email }}</p>
                            <h2 class="mt-5 truncate text-20 font-black leading-none tracking-normal">{{ $selectedConversation->subject }}</h2>
                            @if ($selectedConversation->lead)
                                <a href="{{ route('admin.leads.index', ['lead' => $selectedConversation->lead]) }}" class="mt-8 inline-flex rounded-full bg-gray-light px-10 py-5 text-11 font-extrabold uppercase tracking-normal text-black-nike transition hover:text-bullstar">
                                    Lead #{{ $selectedConversation->lead->id }}
                                </a>
                            @endif
                        </div>
                        <span class="inline-flex w-fit rounded-full bg-gray-light px-10 py-6 text-11 font-extrabold uppercase tracking-normal text-black-nike">
                            {{ $selectedConversation->messages->count() }} messaggi
                        </span>
                    </div>
                </div>

                <div class="max-h-[680px] overflow-y-auto bg-gray-light px-16 py-16">
                    <div class="flex flex-col gap-12">
                        @forelse ($selectedMessages as $message)
                            @php
                                $isOutbound = $message->direction === 'outbound';
                                $hasHtmlBody = filled($message->body_html);
                                $statusLabel = match ($message->status) {
                                    'sent' => 'Accettata dal server',
                                    'failed' => 'Invio fallito',
                                    'pending' => 'Invio in corso',
                                    'received' => 'Ricevuta',
                                    default => $message->status,
                                };
                            @endphp
                            <article class="flex {{ $isOutbound ? 'justify-end' : 'justify-start' }}">
                                <div class="{{ $hasHtmlBody ? 'w-full' : 'max-w-[82%]' }} rounded-10 px-16 py-12 {{ $isOutbound ? 'bg-bullstar text-white' : 'border border-gray-mid bg-white text-black-nike' }}">
                                    <div class="flex flex-wrap items-center gap-8 text-11 font-extrabold uppercase tracking-normal {{ $isOutbound ? 'text-white/75' : 'text-gray' }}">
                                        <span>{{ $isOutbound ? 'Inviata' : 'Ricevuta' }}</span>
                                        <span>{{ ($message->sent_at ?? $message->received_at ?? $message->created_at)?->format('d/m/Y H:i') }}</span>
                                        @if ($isOutbound)
                                            <span>{{ $statusLabel }}</span>
                                        @endif
                                    </div>

                                    @if ($hasHtmlBody)
                                        <iframe
                                            title="Contenuto email: {{ $message->subject }}"
                                            srcdoc="{{ $message->body_html }}"
                                            sandbox="allow-same-origin"
                                            referrerpolicy="no-referrer"
                                            loading="lazy"
                                            data-email-html-frame
                                            class="mt-8 h-[240px] w-full rounded-10 border border-gray-mid bg-white"
                                        ></iframe>
                                    @else
                                        <p class="mt-8 whitespace-pre-wrap text-14 font-semibold leading-[22px]">{{ $message->body_text }}</p>
                                    @endif

                                    @if ($message->attachments->isNotEmpty())
                                        <div class="mt-10 flex flex-col gap-6">
                                            @foreach ($message->attachments as $attachment)
                                                <a
                                                    href="{{ route('admin.email.attachments.download', $attachment) }}"
                                                    class="rounded-10 px-10 py-6 text-12 font-bold underline-offset-4 transition hover:underline {{ $isOutbound ? 'bg-white/15 text-white' : 'bg-gray-light text-black-nike' }}"
                                                >
                                                    {{ $attachment->filename }}
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if ($message->error_message)
                                        <p class="mt-10 rounded-10 bg-red-50 px-10 py-8 text-12 font-bold text-red-700">{{ $message->error_message }}</p>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="rounded-10 border border-dashed border-gray-mid bg-white p-16 text-14 font-semibold text-gray">
                                Nessun messaggio salvato in questa conversazione.
                            </div>
                        @endforelse
                    </div>
                </div>

                @if ($hasAccount && auth('admin')->user()?->hasAdminPermission('email.manage'))
                    <form method="POST" action="{{ route('admin.email.messages.store', $selectedConversation) }}" enctype="multipart/form-data" class="border-t border-gray-mid p-16">
                        @csrf
                        <div class="mb-10 flex flex-wrap gap-6">
                            @foreach ($emailTemplates as $template)
                                <button type="button" data-email-template="{{ $template['message'] }}" data-email-template-target="email-reply-composer" class="rounded-10 border border-gray-mid bg-gray-light px-10 py-6 text-left text-11 font-bold text-black-nike transition hover:border-bullstar hover:text-bullstar">
                                    {{ $template['title'] }}
                                </button>
                            @endforeach
                        </div>
                        <textarea
                            id="email-reply-composer"
                            name="body"
                            rows="5"
                            required
                            class="w-full resize-none rounded-10 border-gray-mid px-12 py-12 text-14 font-semibold text-black-nike placeholder:text-gray focus:border-bullstar focus:ring-bullstar"
                            placeholder="Scrivi una risposta..."
                        >{{ old('body') }}</textarea>
                        <div class="mt-10 flex flex-col gap-10 md:flex-row md:items-center md:justify-between">
                            <label class="flex min-w-0 flex-1 items-center gap-10 rounded-10 border border-gray-mid bg-gray-light px-12 py-10 text-12 font-bold text-gray">
                                <span class="shrink-0 rounded-10 bg-white px-10 py-6 text-black-nike">Allega</span>
                                <input type="file" name="attachments[]" multiple class="min-w-0 flex-1 text-12">
                            </label>
                            <button type="submit" class="rounded-10 bg-bullstar px-20 py-12 text-12 font-extrabold uppercase tracking-normal text-white transition hover:bg-bullstar-hover">
                                Invia risposta
                            </button>
                        </div>
                    </form>
                @endif
            @endif
        </section>

        <aside class="flex flex-col gap-16">
            @if (auth('admin')->user()?->hasAdminPermission('email.manage'))
                <section class="rounded-10 border border-gray-mid bg-white p-16">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-bullstar">Nuova email</p>
                    <form method="POST" action="{{ route('admin.email.conversations.store') }}" enctype="multipart/form-data" class="mt-12 flex flex-col gap-10">
                        @csrf
                        <input name="to_email" type="email" required value="{{ old('to_email') }}" placeholder="Email destinatario" class="rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                        <input name="contact_name" type="text" value="{{ old('contact_name') }}" placeholder="Nome destinatario" class="rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                        <input name="subject" type="text" required value="{{ old('subject') }}" placeholder="Oggetto" class="rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                        <div class="grid grid-cols-2 gap-8">
                            <input name="cc" type="email" value="{{ old('cc') }}" placeholder="CC" class="min-w-0 rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                            <input name="bcc" type="email" value="{{ old('bcc') }}" placeholder="CCN" class="min-w-0 rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                        </div>
                        <select name="lead_id" class="rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                            <option value="">Nessun lead collegato</option>
                            @foreach ($leads as $lead)
                                <option value="{{ $lead->id }}" @selected((string) old('lead_id') === (string) $lead->id)>
                                    #{{ $lead->id }} - {{ $lead->name ?: $lead->club ?: $lead->email }}
                                </option>
                            @endforeach
                        </select>
                        <div class="flex flex-wrap gap-6">
                            @foreach ($emailTemplates as $template)
                                <button type="button" data-email-template="{{ $template['message'] }}" data-email-template-target="email-new-composer" class="rounded-10 border border-gray-mid bg-gray-light px-10 py-6 text-left text-11 font-bold text-black-nike transition hover:border-bullstar hover:text-bullstar">
                                    {{ $template['title'] }}
                                </button>
                            @endforeach
                        </div>
                        <textarea id="email-new-composer" name="body" rows="6" required placeholder="Testo email" class="resize-none rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">{{ old('body') }}</textarea>
                        <label class="rounded-10 border border-gray-mid bg-gray-light px-12 py-10 text-12 font-bold text-gray">
                            Allegati
                            <input type="file" name="attachments[]" multiple class="mt-8 block w-full text-12">
                        </label>
                        <button type="submit" @disabled(! $hasAccount) class="rounded-10 bg-bullstar px-16 py-12 text-12 font-extrabold uppercase tracking-normal text-white transition hover:bg-bullstar-hover disabled:cursor-not-allowed disabled:bg-gray">
                            Invia email
                        </button>
                    </form>
                </section>

                <section class="rounded-10 border border-gray-mid bg-white p-16">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-bullstar">Casella</p>
                    <h3 class="mt-4 text-18 font-black leading-none tracking-normal">{{ $account?->email ?? 'Da configurare' }}</h3>

                    @if ($account)
                        <div class="mt-10 flex items-center justify-between gap-8">
                            <p class="text-11 font-bold uppercase tracking-normal text-gray">
                                {{ $account->last_synced_at ? 'Ultimo sync '.$account->last_synced_at->format('d/m/Y H:i') : 'Mai sincronizzata' }}
                            </p>
                            <form method="POST" action="{{ route('admin.email.sync') }}">
                                @csrf
                                <button type="submit" class="whitespace-nowrap rounded-10 border border-gray-mid bg-white px-8 py-6 text-11 font-extrabold uppercase tracking-normal text-black-nike transition hover:border-bullstar hover:text-bullstar">
                                    Sincronizza ora
                                </button>
                            </form>
                        </div>
                    @endif

                    @if ($account?->last_sync_error)
                        <p class="mt-10 rounded-10 border border-red-200 bg-red-50 px-10 py-8 text-12 font-bold text-red-700">{{ $account->last_sync_error }}</p>
                    @endif

                    <p class="mt-10 text-12 font-semibold leading-[18px] text-gray">
                        La configurazione della casella viene gestita dall'amministratore nella pagina Utenti.
                    </p>
                </section>
            @endif
        </aside>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-email-template]').forEach((button) => {
            button.addEventListener('click', () => {
                const composer = document.getElementById(button.dataset.emailTemplateTarget);

                if (! composer) return;

                composer.value = button.dataset.emailTemplate;
                composer.focus();
            });
        });

        document.querySelectorAll('[data-email-html-frame]').forEach((frame) => {
            const resize = () => {
                const documentHeight = frame.contentDocument?.documentElement?.scrollHeight ?? 240;
                frame.style.height = `${Math.min(Math.max(documentHeight + 8, 180), 900)}px`;
            };

            frame.addEventListener('load', () => {
                resize();
                window.setTimeout(resize, 250);
                window.setTimeout(resize, 1000);
            });
        });
    </script>
@endpush
