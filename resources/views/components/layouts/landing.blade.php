@props(['theme' => 'light'])
<!DOCTYPE html>
<html lang="it">
<head>
    @include('partials.head')
</head>
<body class="font-sans antialiased">
    <!-- Google Tag Manager (noscript) -->
    <noscript>
        <iframe src="https://www.googletagmanager.com/ns.html?id=GTM-TSP4RQ2"
        height="0" width="0" style="display:none;visibility:hidden"></iframe>
    </noscript>
    <!-- End Google Tag Manager (noscript) -->

    <x-layouts.landing-header />

    <main class="relative">
        {{ $slot }}
    </main>

    <x-layouts.landing-footer />

    @livewireScripts(['defer' => true])
    @stack('scripts')
</body>
</html>