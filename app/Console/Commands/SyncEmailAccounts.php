<?php

namespace App\Console\Commands;

use App\Models\EmailAccount;
use App\Services\EmailMailboxSyncService;
use Illuminate\Console\Command;

class SyncEmailAccounts extends Command
{
    protected $signature = 'email:sync {account? : ID della casella da sincronizzare}';

    protected $description = 'Sincronizza le caselle email admin tramite IMAP';

    public function handle(EmailMailboxSyncService $sync): int
    {
        $accounts = EmailAccount::query()
            ->where('is_active', true)
            ->when($this->argument('account'), fn ($query, $id) => $query->whereKey($id))
            ->get();

        foreach ($accounts as $account) {
            try {
                $imported = $sync->sync($account);
                $this->info("{$account->email}: {$imported} nuove email.");
            } catch (\Throwable $e) {
                $this->error("{$account->email}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
