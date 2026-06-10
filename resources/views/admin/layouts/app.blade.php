<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $metaRobots = trim($__env->yieldContent('meta_robots'));

        if ($metaRobots === '') {
            $metaRobots = 'noindex, nofollow';
        }
    @endphp
    <meta name="robots" content="{{ $metaRobots }}">
    <title>@yield('title', 'Stuart Admin')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('head')
</head>
<body class="min-h-screen bg-gray-light text-black-nike antialiased">
    <div class="flex min-h-screen flex-col">
        <header class="border-b border-gray-mid bg-white">
            <div class="mx-auto flex max-w-[1440px] flex-col gap-16 px-20 py-16 md:flex-row md:items-center md:justify-between md:px-32">
                <div class="flex items-center gap-16">
                    <img src="{{ asset('assets/logos/logo-stuart.png') }}" alt="Stuart" class="h-36 w-auto">
                    <div class="hidden h-32 w-px bg-gray-mid sm:block"></div>
                    <div>
                        <p class="text-12 font-extrabold uppercase tracking-normal text-bullstar">Admin</p>
                        <h1 class="text-24 font-black leading-none tracking-normal">@yield('page_title', 'Admin')</h1>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-8">
                    @php
                        $activeNav = trim($__env->yieldContent('active_nav'));
                        $adminUser = auth('admin')->user();
                    @endphp

                    @if ($adminUser?->hasAdminPermission('whatsapp.view'))
                        <a
                            href="{{ route('admin.dashboard') }}"
                            class="rounded-10 border px-12 py-10 text-12 font-extrabold uppercase tracking-normal transition {{ $activeNav === 'whatsapp' ? 'border-bullstar bg-bullstar text-white' : 'border-gray-mid bg-white text-black-nike hover:border-black-nike' }}"
                        >
                            WhatsApp
                        </a>
                    @endif
                    @if ($adminUser?->hasAdminPermission('leads.view'))
                        <a
                            href="{{ route('admin.leads.index') }}"
                            class="rounded-10 border px-12 py-10 text-12 font-extrabold uppercase tracking-normal transition {{ $activeNav === 'leads' ? 'border-bullstar bg-bullstar text-white' : 'border-gray-mid bg-white text-black-nike hover:border-black-nike' }}"
                        >
                            Leads
                        </a>
                    @endif
                    @if ($adminUser?->hasAdminPermission('email.view'))
                        <a
                            href="{{ route('admin.email.index') }}"
                            class="rounded-10 border px-12 py-10 text-12 font-extrabold uppercase tracking-normal transition {{ $activeNav === 'email' ? 'border-bullstar bg-bullstar text-white' : 'border-gray-mid bg-white text-black-nike hover:border-black-nike' }}"
                        >
                            Email
                        </a>
                    @endif
                    @if ($adminUser?->hasAdminPermission('documents.view') && ! $adminUser->training_mode_active)
                        <a
                            href="{{ route('admin.documents.index') }}"
                            class="rounded-10 border px-12 py-10 text-12 font-extrabold uppercase tracking-normal transition {{ $activeNav === 'documents' ? 'border-bullstar bg-bullstar text-white' : 'border-gray-mid bg-white text-black-nike hover:border-black-nike' }}"
                        >
                            Documenti
                        </a>
                    @endif
                    @if ($adminUser?->hasAdminPermission('shipments.view') && ! $adminUser->training_mode_active)
                        <a
                            href="{{ route('admin.shipments.index') }}"
                            class="rounded-10 border px-12 py-10 text-12 font-extrabold uppercase tracking-normal transition {{ $activeNav === 'shipments' ? 'border-bullstar bg-bullstar text-white' : 'border-gray-mid bg-white text-black-nike hover:border-black-nike' }}"
                        >
                            Spedizioni
                        </a>
                    @endif
                    @if ($adminUser?->canManageAdminUsers() && ! $adminUser->training_mode_active)
                        <a
                            href="{{ route('admin.users.index') }}"
                            class="rounded-10 border px-12 py-10 text-12 font-extrabold uppercase tracking-normal transition {{ $activeNav === 'settings' ? 'border-bullstar bg-bullstar text-white' : 'border-gray-mid bg-white text-black-nike hover:border-black-nike' }}"
                        >
                            Utenti
                        </a>
                    @endif
                    @if ($adminUser?->training_mode_enabled)
                        <a
                            href="{{ route('admin.training.index') }}"
                            class="rounded-10 border px-12 py-10 text-12 font-extrabold uppercase tracking-normal transition {{ $activeNav === 'training' ? 'border-bullstar bg-bullstar text-white' : 'border-gray-mid bg-white text-black-nike hover:border-bullstar hover:text-bullstar' }}"
                        >
                            Formazione
                        </a>
                        <form method="POST" action="{{ route('admin.training.toggle') }}">
                            @csrf
                            <button
                                type="submit"
                                class="rounded-10 border px-12 py-10 text-12 font-extrabold uppercase tracking-normal transition {{ $adminUser->training_mode_active ? 'border-bullstar bg-bullstar text-white hover:bg-bullstar-hover' : 'border-bullstar bg-white text-bullstar hover:bg-bullstar hover:text-white' }}"
                            >
                                {{ $adminUser->training_mode_active ? 'Esci formazione' : 'Avvia formazione' }}
                            </button>
                        </form>
                    @endif
                    @if ($adminUser && ! $adminUser->training_mode_active)
                        @php($unreadNotificationsCount = $adminUser->unreadNotifications()->count())
                        <a
                            href="{{ route('admin.notifications.index') }}"
                            class="inline-flex items-center gap-6 rounded-10 border px-12 py-10 text-12 font-extrabold uppercase tracking-normal transition {{ $activeNav === 'notifications' ? 'border-bullstar bg-bullstar text-white' : 'border-gray-mid bg-white text-black-nike hover:border-black-nike' }}"
                        >
                            Notifiche
                            @if ($unreadNotificationsCount > 0)
                                <span class="inline-flex h-20 min-w-20 shrink-0 items-center justify-center rounded-full px-6 text-11 font-black leading-none {{ $activeNav === 'notifications' ? 'bg-black-nike text-white' : 'bg-bullstar text-white' }}">
                                    {{ $unreadNotificationsCount > 99 ? '99+' : $unreadNotificationsCount }}
                                </span>
                            @endif
                        </a>
                    @endif
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
            @if (auth('admin')->user()?->training_mode_active)
                <div class="mb-16 flex flex-col gap-8 rounded-10 border border-red-300 border-l-4 border-l-red-600 bg-red-50 px-16 py-12 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-12 font-extrabold uppercase tracking-normal text-red-700">Attenzione: formazione WhatsApp con invii reali</p>
                        <p class="mt-3 text-14 font-bold text-black-nike">La prima risposta automatica è simulata. Tutti i messaggi WhatsApp successivi inviati dal pannello sono reali. Dopo l’aggancio iniziale, i messaggi in ingresso dal numero usato per il test vengono ignorati e non salvati fino all’uscita dalla formazione.</p>
                    </div>
                    <a href="{{ route('admin.training.index') }}" class="text-12 font-extrabold uppercase tracking-normal text-red-700 hover:underline">Gestisci scenari</a>
                </div>
            @endif
            @if (session('status'))
                <div class="mb-16 rounded-10 border border-whatsapp/20 bg-whatsapp/10 px-16 py-12 text-14 font-bold text-whatsapp">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-16 rounded-10 border border-red-200 bg-red-50 px-16 py-12 text-14 font-bold text-red-700">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-16 rounded-10 border border-red-200 bg-red-50 px-16 py-12 text-14 font-bold text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    @stack('scripts')
    @livewireScripts
</body>
</html>
