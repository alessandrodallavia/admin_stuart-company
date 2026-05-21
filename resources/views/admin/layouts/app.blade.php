<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Bullstar Admin')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen bg-gray-light text-black-nike antialiased">
    <div class="flex min-h-screen flex-col">
        <header class="border-b border-gray-mid bg-white">
            <div class="mx-auto flex max-w-[1440px] flex-col gap-16 px-20 py-16 md:flex-row md:items-center md:justify-between md:px-32">
                <div class="flex items-center gap-16">
                    <img src="{{ asset('assets/logos/logo-stuart.png') }}" alt="Bullstar" class="h-36 w-auto">
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
                    @if ($adminUser?->canManageAdminUsers())
                        <a
                            href="{{ route('admin.users.index') }}"
                            class="rounded-10 border px-12 py-10 text-12 font-extrabold uppercase tracking-normal transition {{ $activeNav === 'settings' ? 'border-bullstar bg-bullstar text-white' : 'border-gray-mid bg-white text-black-nike hover:border-black-nike' }}"
                        >
                            Utenti
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

            @yield('content')
        </main>
    </div>

    @stack('scripts')
</body>
</html>
