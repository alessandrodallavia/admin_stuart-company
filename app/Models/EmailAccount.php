<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class EmailAccount extends Model
{
    protected $fillable = [
        'admin_user_id',
        'email',
        'from_name',
        'username',
        'password_encrypted',
        'imap_host',
        'imap_port',
        'imap_encryption',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'sync_folder',
        'is_active',
        'last_synced_at',
        'last_sync_error',
    ];

    protected $casts = [
        'imap_port' => 'integer',
        'smtp_port' => 'integer',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(EmailConversation::class);
    }

    public function setPassword(?string $password): void
    {
        if ($password !== null && $password !== '') {
            $this->password_encrypted = Crypt::encryptString($password);
        }
    }

    public function password(): ?string
    {
        return $this->password_encrypted ? Crypt::decryptString($this->password_encrypted) : null;
    }
}
