<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('admin_documents')
            ->where(function ($query) {
                $query->where('payment_method', 'MP05')
                    ->orWhereExists(function ($query) {
                        $query->selectRaw('1')
                            ->from('admin_document_payment_schedules')
                            ->whereColumn('admin_document_payment_schedules.admin_document_id', 'admin_documents.id')
                            ->where('admin_document_payment_schedules.payment_method_code', 'MP05');
                    });
            })
            ->update([
                'payment_method' => 'MP05',
                'bank_name' => config('documents.bank.name'),
                'bank_iban' => config('documents.bank.iban'),
                'bank_bic' => config('documents.bank.bic'),
            ]);

        DB::table('admin_document_payment_schedules')
            ->where('payment_method_code', 'MP05')
            ->update(['method' => 'Bonifico bancario']);
    }

    public function down(): void
    {
        DB::table('admin_documents')
            ->where('payment_method', 'MP05')
            ->where('bank_name', config('documents.bank.name'))
            ->where('bank_iban', config('documents.bank.iban'))
            ->where('bank_bic', config('documents.bank.bic'))
            ->update([
                'bank_name' => null,
                'bank_iban' => null,
                'bank_bic' => null,
            ]);
    }
};
