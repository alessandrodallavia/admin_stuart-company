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
    @php
        $activeNav = trim($__env->yieldContent('active_nav'));
        $adminUser = auth('admin')->user();
        $unreadWhatsappCount = $adminUser?->hasAdminPermission('whatsapp.view')
            ? \App\Models\WhatsappMessage::query()
                ->whereHas('conversation')
                ->where('direction', 'inbound')
                ->whereNull('admin_read_at')
                ->count()
            : 0;
        $unreadNotificationsCount = $adminUser && ! $adminUser->training_mode_active
            ? $adminUser->unreadNotifications()->count()
            : 0;
        $routeDocument = request()->route('document');
        $currentDocumentType = request()->string('type')->toString();

        if ($routeDocument instanceof \App\Models\AdminDocument) {
            $currentDocumentType = $routeDocument->type;
        }

        $documentNavigationItems = [
            ['label' => 'Ordini offline', 'type' => 'offline_order'],
            ['label' => 'Fatture', 'type' => 'invoice'],
            ['label' => 'Documenti di trasporto', 'type' => 'delivery_note'],
            ['label' => 'Preventivi', 'type' => 'quote'],
            ['label' => 'Proforma', 'type' => 'proforma'],
        ];

        $documentNavigationItems = collect($documentNavigationItems)
            ->map(fn ($item) => array_merge($item, [
                'route' => route('admin.documents.index', ['type' => $item['type']]),
                'active' => $activeNav === 'documents' && $currentDocumentType === $item['type'],
            ]))
            ->push([
                'label' => 'Scadenze pagamenti',
                'route' => route('admin.documents.payments'),
                'active' => request()->routeIs('admin.documents.payments'),
            ])
            ->all();

        $navGroups = [
            [
                'label' => null,
                'items' => array_values(array_filter([
                    $adminUser?->hasAdminPermission('leads.view') ? [
                        'label' => 'Dashboard',
                        'route' => route('admin.dashboard'),
                        'active' => $activeNav === 'crm-dashboard',
                    ] : null,
                    $adminUser?->hasAdminPermission('whatsapp.view') ? [
                        'label' => 'WhatsApp',
                        'route' => route('admin.whatsapp.index'),
                        'active' => $activeNav === 'whatsapp',
                        'badge' => $unreadWhatsappCount,
                    ] : null,
                    $adminUser?->hasAdminPermission('leads.view') ? [
                        'label' => 'Leads',
                        'route' => route('admin.leads.index'),
                        'active' => $activeNav === 'leads',
                    ] : null,
                    $adminUser?->hasAdminPermission('email.view') ? [
                        'label' => 'Email',
                        'route' => route('admin.email.index'),
                        'active' => $activeNav === 'email',
                    ] : null,
                ])),
            ],
            [
                'label' => 'Operazioni',
                'items' => array_values(array_filter([
                    $adminUser?->hasAdminPermission('documents.view') && ! $adminUser->training_mode_active ? [
                        'label' => 'Documenti',
                        'route' => route('admin.documents.index', ['type' => 'offline_order']),
                        'active' => $activeNav === 'documents',
                        'children' => $documentNavigationItems,
                    ] : null,
                    $adminUser?->hasAdminPermission('shipments.view') && ! $adminUser->training_mode_active ? [
                        'label' => 'Spedizioni',
                        'route' => route('admin.shipments.index'),
                        'active' => $activeNav === 'shipments',
                    ] : null,
                ])),
            ],
            [
                'label' => 'Admin',
                'items' => array_values(array_filter([
                    $adminUser && ! $adminUser->training_mode_active ? [
                        'label' => 'Notifiche',
                        'route' => route('admin.notifications.index'),
                        'active' => $activeNav === 'notifications',
                        'badge' => $unreadNotificationsCount,
                    ] : null,
                    $adminUser?->canManageAdminUsers() && ! $adminUser->training_mode_active ? [
                        'label' => 'Utenti',
                        'route' => route('admin.users.index'),
                        'active' => $activeNav === 'settings',
                    ] : null,
                    $adminUser?->hasAdminPermission('admin_users.manage') && ! $adminUser->training_mode_active ? [
                        'label' => 'Catalogo CRM',
                        'route' => route('admin.crm-catalog.index'),
                        'active' => $activeNav === 'crm-catalog',
                    ] : null,
                    $adminUser?->training_mode_enabled ? [
                        'label' => 'Formazione',
                        'route' => route('admin.training.index'),
                        'active' => $activeNav === 'training',
                    ] : null,
                ])),
            ],
        ];

        $navGroups = array_values(array_filter($navGroups, fn ($group) => count($group['items']) > 0));
    @endphp

    <div class="min-h-screen" data-admin-shell>
        <header class="sticky top-0 z-40 border-b border-gray-mid bg-white font-montserrat lg:hidden">
            <div class="flex items-center justify-between gap-12 px-16 py-12">
                <div class="flex min-w-0 items-center gap-12">
                    <div class="flex shrink-0 flex-col items-center">
                        <img src="{{ asset('assets/logos/logo-stuart.png') }}" alt="Stuart" class="h-24 max-w-[124px] object-contain">
                        <p class="mt-4 text-10 font-semibold uppercase leading-none tracking-normal text-bullstar">Admin</p>
                    </div>
                    <div class="min-w-0">
                        <h1 class="truncate text-14 font-semibold leading-tight">@yield('page_title', 'Admin')</h1>
                    </div>
                </div>

                <button
                    type="button"
                    data-admin-menu-open
                    class="rounded-10 border border-gray-mid bg-white px-12 py-8 text-12 font-semibold uppercase tracking-normal transition hover:border-black-nike hover:bg-gray-light"
                >
                    Menu
                </button>
            </div>
        </header>

        <aside data-admin-sidebar class="fixed inset-y-0 left-0 z-40 hidden w-240 flex-col border-r border-gray-mid bg-white font-montserrat transition-all lg:flex">
            <div class="border-b border-gray-mid px-16 py-20">
                <div class="flex items-start justify-between gap-12">
                    <div class="flex min-w-0 flex-col items-start">
                        <img data-admin-sidebar-logo src="{{ asset('assets/logos/logo-stuart.png') }}" alt="Stuart" class="h-32 max-w-[158px] shrink-0 object-contain">
                        <p data-admin-sidebar-expanded class="mt-8 text-12 font-semibold uppercase leading-none text-bullstar">Admin</p>
                    </div>
                    <button
                        type="button"
                        data-admin-sidebar-toggle
                        class="hidden h-32 w-32 items-center justify-center rounded-full border border-gray-mid bg-white text-14 font-semibold text-gray transition hover:border-bullstar lg:inline-flex"
                        title="Riduci menu"
                    >
                        <svg data-admin-sidebar-expanded class="h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 6l-6 6 6 6" />
                        </svg>
                        <svg data-admin-sidebar-collapsed class="hidden h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6" />
                        </svg>
                    </button>
                </div>
                <div data-admin-sidebar-expanded class="mt-16">
                    <h1 class="truncate text-16 font-semibold leading-tight">@yield('page_title', 'Admin')</h1>
                </div>
            </div>

            <nav class="flex-1 overflow-y-auto px-12 py-8">
                @foreach ($navGroups as $group)
                    <div class="{{ $loop->first ? '' : 'mt-16 border-t border-gray-mid pt-12' }}">
                        @if ($group['label'])
                            <p data-admin-sidebar-expanded class="px-12 pb-8 text-11 font-semibold uppercase tracking-normal text-gray">{{ $group['label'] }}</p>
                            <div data-admin-sidebar-collapsed class="mx-auto mb-8 hidden h-px w-24 bg-gray-mid"></div>
                        @endif

                        <div class="space-y-4">
                            @foreach ($group['items'] as $item)
                                <a
                                    href="{{ $item['route'] }}"
                                    class="flex items-center justify-between gap-12 rounded-[8px] px-12 py-8 text-14 transition {{ $item['active'] ? 'bg-bullstar text-white' : 'text-black-nike hover:bg-gray-light' }}"
                                >
                                    <span data-admin-sidebar-expanded class="truncate font-medium">{{ $item['label'] }}</span>
                                    <span data-admin-sidebar-collapsed class="hidden text-13 font-semibold">{{ \Illuminate\Support\Str::substr($item['label'], 0, 1) }}</span>
                                    @if (($item['badge'] ?? 0) > 0)
                                        <span data-admin-sidebar-expanded class="inline-flex h-20 min-w-20 shrink-0 items-center justify-center rounded-full px-6 text-11 font-semibold leading-none {{ $item['active'] ? 'bg-white text-bullstar' : 'bg-whatsapp text-white' }}">
                                            {{ $item['badge'] > 99 ? '99+' : $item['badge'] }}
                                        </span>
                                    @endif
                                </a>
                                @if (! empty($item['children']))
                                    <div data-admin-sidebar-expanded class="ml-12 mt-2 space-y-1 border-l border-gray-mid pl-12">
                                        @foreach ($item['children'] as $child)
                                            <a
                                                href="{{ $child['route'] }}"
                                                class="block rounded-[8px] px-12 py-6 text-12 transition {{ $child['active'] ? 'bg-gray-light font-semibold text-bullstar' : 'font-normal text-gray hover:bg-gray-light hover:text-black-nike' }}"
                                            >
                                                {{ $child['label'] }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>

            <div class="border-t border-gray-mid p-12">
                @if ($adminUser?->training_mode_enabled)
                    <form method="POST" action="{{ route('admin.training.toggle') }}" class="mb-8">
                        @csrf
                        <button
                            type="submit"
                            class="w-full rounded-[8px] border px-12 py-8 text-left text-13 font-semibold uppercase tracking-normal transition {{ $adminUser->training_mode_active ? 'border-bullstar bg-bullstar text-white hover:bg-bullstar-hover' : 'border-bullstar bg-white text-bullstar hover:bg-bullstar hover:text-white' }}"
                        >
                            {{ $adminUser->training_mode_active ? 'Esci formazione' : 'Avvia formazione' }}
                        </button>
                    </form>
                @endif
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button
                        type="submit"
                        class="w-full rounded-[8px] border border-gray-mid bg-white px-12 py-8 text-left text-14 font-medium text-black-nike transition hover:border-black-nike hover:bg-gray-light"
                    >
                        Esci
                    </button>
                </form>
            </div>
        </aside>

        <div data-admin-menu-backdrop class="fixed inset-0 z-50 hidden bg-black/40 lg:hidden"></div>

        <aside data-admin-menu class="fixed inset-y-0 left-0 z-50 flex w-300 max-w-[85vw] -translate-x-full flex-col border-r border-gray-mid bg-white font-montserrat transition-transform duration-200 lg:hidden">
            <div class="flex items-center justify-between gap-12 border-b border-gray-mid px-16 py-16">
                <div class="flex items-center gap-12">
                    <div class="flex flex-col items-center">
                        <img src="{{ asset('assets/logos/logo-stuart.png') }}" alt="Stuart" class="h-24 max-w-[124px] shrink-0 object-contain">
                        <p class="mt-4 text-10 font-semibold uppercase leading-none tracking-normal text-bullstar">Admin</p>
                    </div>
                </div>
                <button
                    type="button"
                    data-admin-menu-close
                    class="rounded-[8px] border border-gray-mid px-12 py-8 text-12 font-semibold uppercase tracking-normal transition hover:border-black-nike hover:bg-gray-light"
                >
                    Chiudi
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto px-12 py-8">
                @foreach ($navGroups as $group)
                    <div class="{{ $loop->first ? '' : 'mt-16 border-t border-gray-mid pt-12' }}">
                        @if ($group['label'])
                            <p class="px-12 pb-8 text-11 font-semibold uppercase tracking-normal text-gray">{{ $group['label'] }}</p>
                        @endif

                        <div class="space-y-4">
                            @foreach ($group['items'] as $item)
                                <a
                                    href="{{ $item['route'] }}"
                                    class="flex items-center justify-between gap-12 rounded-[8px] px-12 py-8 text-14 transition {{ $item['active'] ? 'bg-bullstar text-white' : 'text-black-nike hover:bg-gray-light' }}"
                                >
                                    <span class="truncate font-medium">{{ $item['label'] }}</span>
                                    @if (($item['badge'] ?? 0) > 0)
                                        <span class="inline-flex h-20 min-w-20 shrink-0 items-center justify-center rounded-full px-6 text-11 font-semibold leading-none {{ $item['active'] ? 'bg-white text-bullstar' : 'bg-whatsapp text-white' }}">
                                            {{ $item['badge'] > 99 ? '99+' : $item['badge'] }}
                                        </span>
                                    @endif
                                </a>
                                @if (! empty($item['children']))
                                    <div class="ml-12 mt-2 space-y-1 border-l border-gray-mid pl-12">
                                        @foreach ($item['children'] as $child)
                                            <a
                                                href="{{ $child['route'] }}"
                                                class="block rounded-[8px] px-12 py-6 text-12 transition {{ $child['active'] ? 'bg-gray-light font-semibold text-bullstar' : 'font-normal text-gray hover:bg-gray-light hover:text-black-nike' }}"
                                            >
                                                {{ $child['label'] }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>

            <div class="border-t border-gray-mid p-12">
                @if ($adminUser?->training_mode_enabled)
                    <form method="POST" action="{{ route('admin.training.toggle') }}" class="mb-8">
                        @csrf
                        <button
                            type="submit"
                            class="w-full rounded-[8px] border px-12 py-8 text-left text-13 font-semibold uppercase tracking-normal {{ $adminUser->training_mode_active ? 'border-bullstar bg-bullstar text-white' : 'border-bullstar bg-white text-bullstar' }}"
                        >
                            {{ $adminUser->training_mode_active ? 'Esci formazione' : 'Avvia formazione' }}
                        </button>
                    </form>
                @endif
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button
                        type="submit"
                        class="w-full rounded-[8px] border border-gray-mid bg-white px-12 py-8 text-left text-14 font-medium text-black-nike"
                    >
                        Esci
                    </button>
                </form>
            </div>
        </aside>

        <main data-admin-main class="mx-auto flex w-full max-w-[1440px] flex-1 flex-col px-12 py-16 pb-24 transition-all md:px-24 md:py-24 lg:ml-240 lg:max-w-[calc(100%-240px)] lg:px-28">
            @if (auth('admin')->user()?->training_mode_active)
                <div class="mb-16 flex flex-col gap-8 rounded-10 border border-red-300 border-l-4 border-l-red-600 bg-red-50 px-16 py-12 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-12 font-extrabold uppercase tracking-normal text-red-700">Attenzione: formazione WhatsApp con invii reali</p>
                        <p class="mt-3 text-14 font-bold text-black-nike">Tutti i messaggi WhatsApp, sia in ingresso sia in uscita, funzionano realmente e restano isolati nei dati formativi. I link pagamento usano Stripe sandbox.</p>
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

    <script>
        (() => {
            const menu = document.querySelector('[data-admin-menu]');
            const backdrop = document.querySelector('[data-admin-menu-backdrop]');
            const openButtons = document.querySelectorAll('[data-admin-menu-open]');
            const closeButtons = document.querySelectorAll('[data-admin-menu-close]');
            const sidebar = document.querySelector('[data-admin-sidebar]');
            const sidebarToggle = document.querySelector('[data-admin-sidebar-toggle]');
            const main = document.querySelector('[data-admin-main]');

            if (!menu || !backdrop) {
                return;
            }

            const open = () => {
                menu.classList.remove('-translate-x-full');
                backdrop.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            };

            const close = () => {
                menu.classList.add('-translate-x-full');
                backdrop.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            };

            openButtons.forEach((button) => button.addEventListener('click', open));
            closeButtons.forEach((button) => button.addEventListener('click', close));
            backdrop.addEventListener('click', close);
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    close();
                }
            });

            sidebarToggle?.addEventListener('click', () => {
                const collapsed = sidebar?.classList.toggle('w-80');

                sidebar?.classList.toggle('w-240', !collapsed);
                main?.classList.toggle('lg:ml-80', collapsed);
                main?.classList.toggle('lg:max-w-[calc(100%-80px)]', collapsed);
                main?.classList.toggle('lg:ml-240', !collapsed);
                main?.classList.toggle('lg:max-w-[calc(100%-240px)]', !collapsed);
                sidebarToggle.title = collapsed ? 'Espandi menu' : 'Riduci menu';

                sidebar?.querySelectorAll('[data-admin-sidebar-expanded]').forEach((element) => {
                    element.classList.toggle('hidden', collapsed);
                });

                sidebar?.querySelectorAll('[data-admin-sidebar-collapsed]').forEach((element) => {
                    element.classList.toggle('hidden', !collapsed);
                });

                sidebar?.querySelectorAll('nav a').forEach((link) => {
                    link.classList.toggle('justify-center', collapsed);
                    link.classList.toggle('justify-between', !collapsed);
                    link.classList.toggle('gap-12', !collapsed);
                });

                const logo = sidebar?.querySelector('[data-admin-sidebar-logo]');
                logo?.classList.toggle('max-w-[48px]', collapsed);
                logo?.classList.toggle('max-w-[158px]', !collapsed);
            });
        })();
    </script>
    @stack('scripts')
    @livewireScripts
</body>
</html>
