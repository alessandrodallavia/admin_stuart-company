<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Accesso admin - Bullstar</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-black-nike text-white antialiased">
    <main class="grid min-h-screen lg:grid-cols-[minmax(0,1fr)_500px]">
        <section class="relative hidden overflow-hidden bg-black-nike lg:block">
            <div class="absolute inset-0 bg-[linear-gradient(135deg,rgba(31,31,33,1)_0%,rgba(31,31,33,.96)_38%,rgba(32,106,233,.82)_100%)]"></div>
            <div class="absolute -bottom-120 -right-160 h-[360px] w-[680px] rotate-[-14deg] bg-white/8"></div>
            <div class="absolute -bottom-160 -right-200 h-[260px] w-[620px] rotate-[-14deg] bg-bullstar/35"></div>

            <div class="relative flex h-full flex-col justify-between p-48 xl:p-64">
                <a href="{{ route('admin.login') }}" class="inline-flex w-fit">
                    <img
                        src="{{ asset('assets/logos/logo-stuart.png') }}"
                        alt="Bullstar"
                        class="h-48 w-auto"
                    >
                </a>

                <div class="max-w-[600px]">
                    <p class="mb-16 text-14 font-extrabold uppercase tracking-normal text-white/70">
                        Area riservata
                    </p>
                    <h1 class="text-[clamp(42px,4vw,64px)] font-black uppercase leading-none tracking-normal">
                        Admin WhatsApp
                    </h1>
                    <div class="mt-28 grid max-w-[500px] grid-cols-3 gap-12">
                        <div class="h-8 rounded-full bg-white"></div>
                        <div class="h-8 rounded-full bg-bullstar"></div>
                        <div class="h-8 rounded-full bg-whatsapp"></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="flex min-h-screen items-center justify-center bg-black-nike px-24 py-40 text-white sm:px-40">
            <div class="w-full max-w-[360px]">
                <div class="mb-32 lg:hidden">
                    <a href="{{ route('admin.login') }}" class="inline-flex">
                        <img
                            src="{{ asset('assets/logos/logo-stuart.png') }}"
                            alt="Bullstar"
                            class="h-40 w-auto"
                        >
                    </a>
                </div>

                <div class="mb-28">
                    <p class="mb-8 text-14 font-extrabold uppercase tracking-normal text-bullstar">
                        Login admin
                    </p>
                    <h1 class="text-30 font-black leading-none tracking-normal text-white">
                        Bentornato.
                    </h1>
                </div>

                <form method="POST" action="{{ route('admin.login.store') }}" class="space-y-16">
                    @csrf

                    <div>
                        <label for="email" class="mb-6 block text-12 font-extrabold uppercase tracking-normal text-white/70">
                            Email
                        </label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            autocomplete="email"
                            required
                            autofocus
                            class="w-full rounded-10 border border-white/10 bg-white px-12 py-10 text-14 font-semibold leading-[18px] text-black-nike outline-none transition placeholder:text-gray focus:border-bullstar focus:ring-4 focus:ring-bullstar/20"
                            placeholder="admin@bullstar.it"
                        >
                        @error('email')<p class="mt-8 text-14 font-bold text-red-300">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="password" class="mb-6 block text-12 font-extrabold uppercase tracking-normal text-white/70">
                            Password
                        </label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="w-full rounded-10 border border-white/10 bg-white px-12 py-10 text-14 font-semibold leading-[18px] text-black-nike outline-none transition placeholder:text-gray focus:border-bullstar focus:ring-4 focus:ring-bullstar/20"
                            placeholder="Password"
                        >
                        @error('password')<p class="mt-8 text-14 font-bold text-red-300">{{ $message }}</p>@enderror
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-10 bg-bullstar px-20 py-12 text-14 font-extrabold uppercase tracking-normal text-white transition hover:bg-bullstar-hover focus:outline-none focus:ring-4 focus:ring-bullstar/30"
                    >
                        Accedi
                    </button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
