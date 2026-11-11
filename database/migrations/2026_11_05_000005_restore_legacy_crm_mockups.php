<?php

use App\Services\LegacyCrmMockupRestorer;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(LegacyCrmMockupRestorer::class)->restore(true);
    }

    public function down(): void
    {
        // Preserve associations: an operator may already have reviewed or reused them.
    }
};
