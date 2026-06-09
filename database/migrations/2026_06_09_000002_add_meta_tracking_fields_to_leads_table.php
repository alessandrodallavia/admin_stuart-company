<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'meta_marketing_consent')) {
                $table->boolean('meta_marketing_consent')->default(false)->after('fbclid');
            }

            if (! Schema::hasColumn('leads', 'meta_fbp')) {
                $table->string('meta_fbp')->nullable()->after('meta_marketing_consent');
            }

            if (! Schema::hasColumn('leads', 'meta_fbc')) {
                $table->string('meta_fbc')->nullable()->after('meta_fbp');
            }

            foreach (['contact', 'initiate_checkout', 'purchase'] as $event) {
                if (! Schema::hasColumn('leads', "meta_{$event}_sent_at")) {
                    $table->timestamp("meta_{$event}_sent_at")->nullable();
                }

                if (! Schema::hasColumn('leads', "meta_{$event}_status")) {
                    $table->string("meta_{$event}_status")->nullable();
                }

                if (! Schema::hasColumn('leads', "meta_{$event}_error")) {
                    $table->text("meta_{$event}_error")->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            foreach ([
                'meta_purchase_error',
                'meta_purchase_status',
                'meta_purchase_sent_at',
                'meta_initiate_checkout_error',
                'meta_initiate_checkout_status',
                'meta_initiate_checkout_sent_at',
                'meta_contact_error',
                'meta_contact_status',
                'meta_contact_sent_at',
                'meta_fbc',
                'meta_fbp',
                'meta_marketing_consent',
            ] as $column) {
                if (Schema::hasColumn('leads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
