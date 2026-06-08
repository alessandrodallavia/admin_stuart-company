@extends('admin.layouts.app')

@section('title', 'Utenti admin - Bullstar Admin')
@section('page_title', 'Utenti admin')
@section('active_nav', 'settings')

@section('content')
            @php
                $currentAdmin = auth('admin')->user();
                $hasOwner = $adminUsers->contains(fn ($user) => $user->role === 'owner');
            @endphp

            @unless ($hasOwner)
                <div class="mb-16 rounded-10 border border-brand/20 bg-brand/5 px-16 py-12 text-14 font-bold text-brand">
                    Nessun amministratore principale configurato. Crea o promuovi un utente con ruolo Amministratore per chiudere l'accesso bootstrap.
                </div>
            @endunless

            <section class="mb-16 overflow-hidden rounded-10 border border-gray-mid bg-white">
                <div class="border-b border-gray-mid px-16 py-14">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Ruoli e concessioni</p>
                    <p class="mt-4 text-14 font-bold text-black-nike">Permessi inclusi automaticamente in ogni ruolo.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-[760px] w-full text-left">
                        <thead class="border-b border-gray-mid bg-gray-light text-11 font-extrabold uppercase tracking-normal text-gray">
                            <tr>
                                <th class="px-12 py-12">Ruolo</th>
                                <th class="px-12 py-12">Concessioni incluse</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-mid">
                            @foreach ($roles as $roleKey => $roleLabel)
                                @php
                                    $rolePermissions = config("admin_permissions.role_permissions.{$roleKey}", []);
                                @endphp
                                <tr>
                                    <td class="px-12 py-12">
                                        <span class="rounded-full bg-black-nike px-10 py-6 text-11 font-extrabold uppercase tracking-normal text-white">
                                            {{ $roleLabel }}
                                        </span>
                                    </td>
                                    <td class="px-12 py-12">
                                        @if (in_array('*', $rolePermissions, true))
                                            <span class="rounded-full bg-bullstar/10 px-8 py-5 text-11 font-extrabold uppercase tracking-normal text-bullstar">
                                                Tutti i permessi
                                            </span>
                                        @elseif (count($rolePermissions) > 0)
                                            <div class="flex flex-wrap gap-6">
                                                @foreach ($rolePermissions as $permissionKey)
                                                    <span class="rounded-full bg-gray-light px-8 py-5 text-11 font-bold text-gray">
                                                        {{ $permissions[$permissionKey]['label'] ?? $permissionKey }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-12 font-semibold text-gray">Nessun permesso base. Usa i permessi extra.</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="mb-16 overflow-hidden rounded-10 border border-gray-mid bg-white">
                <div class="border-b border-gray-mid px-16 py-14">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Nuovo utente</p>
                    <p class="mt-4 text-14 font-bold text-black-nike">Crea accessi separati con ruolo e permessi dedicati.</p>
                </div>

                <form method="POST" action="{{ route('admin.users.store') }}" class="grid gap-12 p-16 xl:grid-cols-[1fr_1fr_180px]">
                    @csrf
                    <label class="block">
                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Nome</span>
                        <input name="name" value="{{ old('name') }}" type="text" maxlength="100" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                    </label>
                    <label class="block">
                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Email</span>
                        <input name="email" value="{{ old('email') }}" type="email" maxlength="255" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                    </label>
                    <label class="block">
                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Ruolo</span>
                        <select name="role" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                            @foreach ($roles as $value => $label)
                                <option value="{{ $value }}" @selected(old('role', 'operator') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block">
                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Password</span>
                        <input name="password" type="password" autocomplete="new-password" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                    </label>
                    <label class="block">
                        <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Conferma password</span>
                        <input name="password_confirmation" type="password" autocomplete="new-password" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                    </label>
                    <label class="flex items-end gap-8 pb-2 text-12 font-extrabold uppercase tracking-normal text-gray">
                        <input name="is_active" value="1" type="checkbox" checked class="rounded border-gray-mid text-bullstar focus:ring-bullstar">
                        Attivo
                    </label>

                    <div class="xl:col-span-3">
                        <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Permessi aggiuntivi</p>
                        <div class="mt-8 grid gap-10 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ($permissionsByGroup as $group => $groupPermissions)
                                <div class="rounded-10 border border-gray-mid bg-gray-light p-12">
                                    <p class="text-12 font-black uppercase tracking-normal text-black-nike">{{ $group }}</p>
                                    <div class="mt-8 space-y-7">
                                        @foreach ($groupPermissions as $permissionKey => $permission)
                                            <label class="flex gap-8 text-12 font-bold leading-[18px] text-gray">
                                                <input name="permissions[]" value="{{ $permissionKey }}" type="checkbox" class="mt-2 rounded border-gray-mid text-bullstar focus:ring-bullstar">
                                                <span>{{ $permission['label'] }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="xl:col-span-3">
                        <button type="submit" class="rounded-10 bg-bullstar px-16 py-10 text-12 font-extrabold uppercase tracking-normal text-white transition hover:bg-bullstar-hover">
                            Crea utente
                        </button>
                    </div>
                </form>
            </section>

            <section class="mb-16 overflow-hidden rounded-10 border border-gray-mid bg-white">
                <div class="border-b border-gray-mid px-16 py-14">
                    <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Utenti esistenti</p>
                    <p class="mt-4 text-14 font-bold text-black-nike">{{ $adminUsers->count() }} profili admin configurati</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-[920px] w-full text-left">
                        <thead class="border-b border-gray-mid bg-gray-light text-11 font-extrabold uppercase tracking-normal text-gray">
                            <tr>
                                <th class="px-12 py-12">Utente</th>
                                <th class="px-12 py-12">Ruolo</th>
                                <th class="px-12 py-12">Stato</th>
                                <th class="px-12 py-12">Permessi</th>
                                <th class="px-12 py-12">Casella email</th>
                                <th class="px-12 py-12">Ultimo accesso</th>
                                <th class="px-12 py-12"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-mid">
                            @foreach ($adminUsers as $adminUser)
                                @php
                                    $effectivePermissions = $adminUser->effectivePermissions();
                                    $rolePermissions = config("admin_permissions.role_permissions.{$adminUser->role}", []);
                                    $savedPermissions = $adminUser->permissions ?? [];
                                    $canEditOwner = ! $adminUser->isOwner() || $currentAdmin?->isOwner();
                                    $isSelected = ($selectedAdminUser?->id ?? null) === $adminUser->id;
                                @endphp
                                <tr class="{{ $isSelected ? 'bg-bullstar/5' : 'bg-white' }}">
                                    <td class="px-12 py-12">
                                        <p class="max-w-[240px] truncate text-14 font-black leading-tight">{{ $adminUser->name }}</p>
                                        <p class="mt-4 max-w-[240px] truncate text-12 font-semibold text-gray">{{ $adminUser->email }}</p>
                                    </td>
                                    <td class="px-12 py-12">
                                        <span class="rounded-full bg-black-nike px-10 py-6 text-11 font-extrabold uppercase tracking-normal text-white">
                                            {{ $roles[$adminUser->role] ?? $adminUser->role }}
                                        </span>
                                    </td>
                                    <td class="px-12 py-12">
                                        <span class="rounded-full px-10 py-6 text-11 font-extrabold uppercase tracking-normal {{ $adminUser->is_active ? 'bg-whatsapp/10 text-whatsapp' : 'bg-gray-mid text-black-nike' }}">
                                            {{ $adminUser->is_active ? 'Attivo' : 'Disattivato' }}
                                        </span>
                                    </td>
                                    <td class="px-12 py-12">
                                        <p class="text-12 font-bold text-black-nike">
                                            {{ in_array('*', $effectivePermissions, true) ? 'Tutti i permessi' : count($effectivePermissions) . ' permessi' }}
                                        </p>
                                        @unless (in_array('*', $effectivePermissions, true))
                                            <p class="mt-4 text-11 font-semibold text-gray">
                                                {{ count($rolePermissions) }} da ruolo / {{ count($savedPermissions) }} extra
                                            </p>
                                        @endunless
                                    </td>
                                    <td class="px-12 py-12">
                                        @if ($adminUser->emailAccount)
                                            <p class="max-w-[220px] truncate text-12 font-bold text-black-nike">{{ $adminUser->emailAccount->email }}</p>
                                            <p class="mt-4 text-11 font-semibold {{ $adminUser->emailAccount->is_active ? 'text-whatsapp' : 'text-gray' }}">
                                                {{ $adminUser->emailAccount->is_active ? 'Attiva' : 'Disattivata' }}
                                            </p>
                                        @else
                                            <span class="text-12 font-semibold text-gray">Non configurata</span>
                                        @endif
                                    </td>
                                    <td class="px-12 py-12">
                                        <p class="text-12 font-bold text-black-nike">{{ $adminUser->last_login_at?->format('d/m/Y H:i') ?: '-' }}</p>
                                    </td>
                                    <td class="px-12 py-12 text-right">
                                        @if ($canEditOwner)
                                            <a href="{{ route('admin.users.index', ['edit' => $adminUser]) }}" class="rounded-10 border border-gray-mid px-12 py-8 text-12 font-extrabold uppercase tracking-normal transition hover:border-bullstar hover:text-bullstar">
                                                Modifica
                                            </a>
                                        @else
                                            <span class="rounded-10 border border-gray-mid bg-gray-light px-12 py-8 text-12 font-extrabold uppercase tracking-normal text-gray">
                                                Protetto
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            @if ($selectedAdminUser)
                @php
                    $effectivePermissions = $selectedAdminUser->effectivePermissions();
                    $rolePermissions = config("admin_permissions.role_permissions.{$selectedAdminUser->role}", []);
                    $isCurrentUser = $currentAdmin?->id === $selectedAdminUser->id;
                    $canEditOwner = ! $selectedAdminUser->isOwner() || $currentAdmin?->isOwner();
                @endphp

                <section class="overflow-hidden rounded-10 border border-gray-mid bg-white">
                    <div class="flex flex-col gap-10 border-b border-gray-mid px-16 py-14 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Modifica utente</p>
                            <p class="mt-4 text-18 font-black leading-tight">{{ $selectedAdminUser->name }}</p>
                        </div>
                        <a href="{{ route('admin.users.index') }}" class="rounded-10 border border-gray-mid px-12 py-8 text-12 font-extrabold uppercase tracking-normal transition hover:border-black-nike">
                            Chiudi
                        </a>
                    </div>

                    <form method="POST" action="{{ route('admin.users.update', $selectedAdminUser) }}" class="grid gap-12 p-16 xl:grid-cols-[1fr_1fr_180px_120px]">
                        @csrf
                        @method('PATCH')

                        <label class="block">
                            <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Nome</span>
                            <input name="name" value="{{ old('name', $selectedAdminUser->name) }}" type="text" maxlength="100" @disabled(! $canEditOwner) class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar disabled:bg-gray-light disabled:text-gray">
                        </label>
                        <label class="block">
                            <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Email</span>
                            <input name="email" value="{{ old('email', $selectedAdminUser->email) }}" type="email" maxlength="255" @disabled(! $canEditOwner) class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar disabled:bg-gray-light disabled:text-gray">
                        </label>
                        <label class="block">
                            <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Ruolo</span>
                            <select name="role" @disabled(! $canEditOwner) class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar disabled:bg-gray-light disabled:text-gray">
                                @foreach ($roles as $value => $label)
                                    <option value="{{ $value }}" @selected(old('role', $selectedAdminUser->role) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <div class="flex items-end">
                            @if ($isCurrentUser)
                                <input type="hidden" name="is_active" value="1">
                            @endif
                            <label class="flex gap-8 pb-3 text-12 font-extrabold uppercase tracking-normal text-gray">
                                <input name="is_active" value="1" type="checkbox" @checked(old('is_active', $selectedAdminUser->is_active)) @disabled($isCurrentUser || ! $canEditOwner) class="rounded border-gray-mid text-bullstar focus:ring-bullstar">
                                Attivo
                            </label>
                        </div>

                        <label class="block">
                            <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Nuova password</span>
                            <input name="password" type="password" autocomplete="new-password" @disabled(! $canEditOwner) class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar disabled:bg-gray-light disabled:text-gray">
                        </label>
                        <label class="block">
                            <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Conferma nuova password</span>
                            <input name="password_confirmation" type="password" autocomplete="new-password" @disabled(! $canEditOwner) class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar disabled:bg-gray-light disabled:text-gray">
                        </label>

                        <div class="xl:col-span-4">
                            <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Permessi</p>
                            <div class="mt-8 grid gap-10 md:grid-cols-2 xl:grid-cols-3">
                                @foreach ($permissionsByGroup as $group => $groupPermissions)
                                    <div class="rounded-10 border border-gray-mid bg-gray-light p-12">
                                        <p class="text-12 font-black uppercase tracking-normal text-black-nike">{{ $group }}</p>
                                        <div class="mt-8 space-y-7">
                                            @foreach ($groupPermissions as $permissionKey => $permission)
                                                @php
                                                    $permissionFromRole = in_array('*', $rolePermissions, true) || in_array($permissionKey, $rolePermissions, true);
                                                    $permissionIsEffective = in_array('*', $effectivePermissions, true) || in_array($permissionKey, $effectivePermissions, true);
                                                @endphp
                                                <label class="flex gap-8 text-12 font-bold leading-[18px] text-gray">
                                                    @if ($permissionFromRole)
                                                        <input type="checkbox" checked disabled class="mt-2 rounded border-gray-mid text-bullstar focus:ring-bullstar">
                                                    @else
                                                        <input name="permissions[]" value="{{ $permissionKey }}" type="checkbox" @checked($permissionIsEffective) @disabled($selectedAdminUser->isOwner() || ! $canEditOwner) class="mt-2 rounded border-gray-mid text-bullstar focus:ring-bullstar">
                                                    @endif
                                                    <span>
                                                        {{ $permission['label'] }}
                                                        @if ($permissionFromRole && ! $selectedAdminUser->isOwner())
                                                            <span class="block text-11 font-semibold text-gray">Incluso nel ruolo</span>
                                                        @endif
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="xl:col-span-4">
                            @if ($canEditOwner)
                                <button type="submit" class="rounded-10 bg-bullstar px-16 py-10 text-12 font-extrabold uppercase tracking-normal text-white transition hover:bg-bullstar-hover">
                                    Salva utente
                                </button>
                            @else
                                <span class="rounded-10 border border-gray-mid bg-gray-light px-12 py-9 text-12 font-extrabold uppercase tracking-normal text-gray">
                                    Solo amministratore
                                </span>
                            @endif
                        </div>
                    </form>
                </section>

                @php($emailAccount = $selectedAdminUser->emailAccount)
                <section class="mt-16 overflow-hidden rounded-10 border border-gray-mid bg-white">
                    <div class="border-b border-gray-mid px-16 py-12">
                        <p class="text-12 font-extrabold uppercase tracking-normal text-gray">Casella email</p>
                        <p class="mt-4 text-14 font-bold text-black-nike">
                            Configurazione IMAP e SMTP personale di {{ $selectedAdminUser->name }}.
                        </p>
                    </div>

                    <form method="POST" action="{{ route('admin.users.email-account.update', $selectedAdminUser) }}" class="grid gap-12 p-16 md:grid-cols-2 xl:grid-cols-4">
                        @csrf
                        @method('PUT')

                        <label class="block">
                            <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Indirizzo email</span>
                            <input name="email_account[email]" type="email" required value="{{ old('email_account.email', $emailAccount?->email ?? $selectedAdminUser->email) }}" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                        </label>
                        <label class="block">
                            <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Nome mittente</span>
                            <input name="email_account[from_name]" type="text" value="{{ old('email_account.from_name', $emailAccount?->from_name ?? $selectedAdminUser->name) }}" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                        </label>
                        <label class="block">
                            <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Username</span>
                            <input name="email_account[username]" type="text" required value="{{ old('email_account.username', $emailAccount?->username ?? $selectedAdminUser->email) }}" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                        </label>
                        <label class="block">
                            <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Password casella</span>
                            <input name="email_account[password]" type="password" autocomplete="new-password" placeholder="{{ $emailAccount ? 'Lascia vuoto per non cambiare' : '' }}" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                        </label>

                        <label class="block">
                            <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Host SMTP</span>
                            <input name="email_account[smtp_host]" type="text" required value="{{ old('email_account.smtp_host', $emailAccount?->smtp_host ?? 'stuart-company.com') }}" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                        </label>
                        <label class="block">
                            <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Porta SMTP</span>
                            <input name="email_account[smtp_port]" type="number" required value="{{ old('email_account.smtp_port', $emailAccount?->smtp_port ?? 465) }}" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                        </label>
                        <label class="block">
                            <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Cifratura SMTP</span>
                            <select name="email_account[smtp_encryption]" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                                @foreach (['ssl' => 'SSL', 'tls' => 'TLS', 'none' => 'Nessuna'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('email_account.smtp_encryption', $emailAccount?->smtp_encryption ?? 'ssl') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block">
                            <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Host IMAP</span>
                            <input name="email_account[imap_host]" type="text" required value="{{ old('email_account.imap_host', $emailAccount?->imap_host ?? 'stuart-company.com') }}" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                        </label>
                        <label class="block">
                            <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Porta IMAP</span>
                            <input name="email_account[imap_port]" type="number" required value="{{ old('email_account.imap_port', $emailAccount?->imap_port ?? 993) }}" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                        </label>
                        <label class="block">
                            <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Cifratura IMAP</span>
                            <select name="email_account[imap_encryption]" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                                @foreach (['ssl' => 'SSL', 'tls' => 'TLS', 'none' => 'Nessuna'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('email_account.imap_encryption', $emailAccount?->imap_encryption ?? 'ssl') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-12 font-extrabold uppercase tracking-normal text-gray">Cartella sincronizzata</span>
                            <input name="email_account[sync_folder]" type="text" required value="{{ old('email_account.sync_folder', $emailAccount?->sync_folder ?? 'INBOX') }}" class="mt-6 w-full rounded-10 border-gray-mid px-12 py-10 text-14 font-semibold focus:border-bullstar focus:ring-bullstar">
                        </label>
                        <label class="flex items-end gap-8 pb-2 text-12 font-extrabold uppercase tracking-normal text-gray">
                            <input name="email_account[is_active]" value="1" type="checkbox" @checked(old('email_account.is_active', $emailAccount?->is_active ?? true)) class="rounded border-gray-mid text-bullstar focus:ring-bullstar">
                            Casella attiva
                        </label>

                        <div class="md:col-span-2 xl:col-span-4">
                            <button type="submit" class="rounded-10 bg-bullstar px-16 py-10 text-12 font-extrabold uppercase tracking-normal text-white transition hover:bg-bullstar-hover">
                                Salva casella email
                            </button>
                        </div>
                    </form>
                </section>
            @endif
@endsection
