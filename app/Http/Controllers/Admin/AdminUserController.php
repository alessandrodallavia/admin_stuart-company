<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\EmailAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(): View
    {
        $selectedUser = request()->integer('edit');

        return view('admin.users.index', [
            'adminUsers' => AdminUser::query()->with('emailAccount')->orderBy('name')->get(),
            'selectedAdminUser' => $selectedUser ? AdminUser::with('emailAccount')->find($selectedUser) : null,
            'roles' => config('admin_permissions.roles'),
            'permissions' => config('admin_permissions.permissions'),
            'permissionsByGroup' => collect(config('admin_permissions.permissions'))
                ->groupBy(fn ($permission) => $permission['group'], preserveKeys: true)
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateUser($request);
        $currentUser = Auth::guard('admin')->user();

        if ($data['role'] === 'owner' && ! $currentUser?->isOwner() && AdminUser::query()->where('role', 'owner')->exists()) {
            return back()->withErrors(['message' => 'Solo un amministratore può creare altri amministratori.'])->withInput();
        }

        AdminUser::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'permissions' => $this->permissionsForRole($data['role'], $data['permissions'] ?? []),
            'is_active' => $request->boolean('is_active'),
            'training_mode_enabled' => $request->boolean('training_mode_enabled'),
        ]);

        $currentUser?->refresh();

        return redirect()
            ->route($this->homeRouteFor($currentUser))
            ->with('status', $currentUser?->canManageAdminUsers()
                ? 'Utente admin creato.'
                : 'Utente amministratore creato. Esci e accedi con quel profilo per gestire gli altri utenti.');
    }

    public function update(Request $request, AdminUser $adminUser): RedirectResponse
    {
        $data = $this->validateUser($request, $adminUser);
        $currentUser = Auth::guard('admin')->user();

        if ($adminUser->isOwner() && ! $currentUser?->isOwner()) {
            return back()->withErrors(['message' => 'Solo un amministratore può modificare un altro amministratore.']);
        }

        if ($data['role'] === 'owner' && ! $currentUser?->isOwner() && AdminUser::query()->where('role', 'owner')->exists()) {
            return back()->withErrors(['message' => 'Solo un amministratore può assegnare il ruolo amministratore.'])->withInput();
        }

        if ($currentUser?->id === $adminUser->id && ! $request->boolean('is_active')) {
            return back()->withErrors(['message' => 'Non puoi disattivare il tuo account mentre sei collegato.']);
        }

        $attributes = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'permissions' => $this->permissionsForRole($data['role'], $data['permissions'] ?? []),
            'is_active' => $request->boolean('is_active'),
            'training_mode_enabled' => $request->boolean('training_mode_enabled'),
            'training_mode_active' => $request->boolean('training_mode_enabled')
                ? $adminUser->training_mode_active
                : false,
        ];

        if (! empty($data['password'])) {
            $attributes['password'] = $data['password'];
        }

        $adminUser->fill($attributes)->save();
        $currentUser?->refresh();

        return redirect()
            ->route($this->homeRouteFor($currentUser))
            ->with('status', 'Utente admin aggiornato.');
    }

    public function updateEmailAccount(Request $request, AdminUser $adminUser): RedirectResponse
    {
        $validated = $request->validate([
            'email_account.email' => ['required', 'email:rfc', 'max:255'],
            'email_account.from_name' => ['nullable', 'string', 'max:255'],
            'email_account.username' => ['required', 'string', 'max:255'],
            'email_account.password' => ['nullable', 'string', 'max:255'],
            'email_account.imap_host' => ['required', 'string', 'max:255'],
            'email_account.imap_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'email_account.imap_encryption' => ['required', Rule::in(['ssl', 'tls', 'none'])],
            'email_account.smtp_host' => ['required', 'string', 'max:255'],
            'email_account.smtp_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'email_account.smtp_encryption' => ['required', Rule::in(['ssl', 'tls', 'none'])],
            'email_account.sync_folder' => ['required', 'string', 'max:255'],
            'email_account.is_active' => ['nullable', 'boolean'],
        ]);
        $data = $validated['email_account'];

        $account = EmailAccount::firstOrNew(['admin_user_id' => $adminUser->id]);

        if (! $account->exists && empty($data['password'])) {
            return back()->withErrors(['password' => 'Inserisci la password della casella email.'])->withInput();
        }

        $account->fill([
            ...collect($data)->except('password')->all(),
            'admin_user_id' => $adminUser->id,
            'is_active' => $request->boolean('email_account.is_active'),
        ]);
        $account->setPassword($data['password'] ?? null);
        $account->save();

        return redirect()
            ->route('admin.users.index', ['edit' => $adminUser])
            ->with('status', "Casella email di {$adminUser->name} salvata.");
    }

    private function validateUser(Request $request, ?AdminUser $adminUser = null): array
    {
        $roles = array_keys(config('admin_permissions.roles'));
        $passwordRules = $adminUser
            ? ['nullable', 'string', 'min:10', 'confirmed']
            : ['required', 'string', 'min:10', 'confirmed'];

        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('admin_users', 'email')->ignore($adminUser?->id)],
            'password' => $passwordRules,
            'role' => ['required', Rule::in($roles)],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'training_mode_enabled' => ['nullable', 'boolean'],
        ]);
    }

    private function permissionsForRole(string $role, array $permissions): array
    {
        if ($role === 'owner') {
            return ['*'];
        }

        return array_values(array_intersect($permissions, array_keys(config('admin_permissions.permissions'))));
    }

    private function homeRouteFor(?AdminUser $user): string
    {
        if ($user?->canManageAdminUsers()) {
            return 'admin.users.index';
        }

        if ($user?->hasAdminPermission('whatsapp.view')) {
            return 'admin.dashboard';
        }

        if ($user?->hasAdminPermission('leads.view')) {
            return 'admin.leads.index';
        }

        if ($user?->hasAdminPermission('documents.view')) {
            return 'admin.documents.index';
        }

        return 'admin.login';
    }
}
