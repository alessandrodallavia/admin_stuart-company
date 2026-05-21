<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 10)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        foreach ([
            ['code' => 'MP01', 'name' => 'Contanti'],
            ['code' => 'MP02', 'name' => 'Assegno'],
            ['code' => 'MP03', 'name' => 'Assegno circolare'],
            ['code' => 'MP05', 'name' => 'Bonifico bancario'],
            ['code' => 'MP08', 'name' => 'Carta di pagamento'],
            ['code' => 'MP12', 'name' => 'RIBA'],
        ] as $method) {
            DB::table('documents_payment_methods')->insert([
                ...$method,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('admin_documents', function (Blueprint $table) {
            $table->string('payment_conditions', 10)->nullable()->after('payment_status');
            $table->string('payment_method', 10)->nullable()->after('payment_conditions');
            $table->string('bank_name')->nullable()->after('payment_method');
            $table->string('bank_iban', 34)->nullable()->after('bank_name');
            $table->string('bank_bic', 11)->nullable()->after('bank_iban');
        });

        Schema::table('admin_document_payment_schedules', function (Blueprint $table) {
            $table->string('payment_method_code', 10)->nullable()->after('method');
        });

        DB::table('admin_document_payment_schedules')
            ->whereNull('payment_method_code')
            ->update(['payment_method_code' => 'MP05']);

        DB::table('admin_documents')
            ->whereNull('payment_conditions')
            ->update(['payment_conditions' => 'TP02']);
    }

    public function down(): void
    {
        Schema::table('admin_document_payment_schedules', function (Blueprint $table) {
            $table->dropColumn('payment_method_code');
        });

        Schema::table('admin_documents', function (Blueprint $table) {
            $table->dropColumn([
                'payment_conditions',
                'payment_method',
                'bank_name',
                'bank_iban',
                'bank_bic',
            ]);
        });

        Schema::dropIfExists('documents_payment_methods');
    }
};
