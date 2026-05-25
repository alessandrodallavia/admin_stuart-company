<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_document_admin_shipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_document_id')->constrained('admin_documents')->cascadeOnDelete();
            $table->foreignId('admin_shipment_id')->constrained('admin_shipments')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['admin_document_id', 'admin_shipment_id'], 'admin_doc_ship_unique');
        });

        DB::table('admin_shipments')
            ->whereNotNull('admin_document_id')
            ->orderBy('id')
            ->select(['id', 'admin_document_id', 'created_at', 'updated_at'])
            ->chunk(100, function ($shipments) {
                foreach ($shipments as $shipment) {
                    DB::table('admin_document_admin_shipment')->insertOrIgnore([
                        'admin_document_id' => $shipment->admin_document_id,
                        'admin_shipment_id' => $shipment->id,
                        'created_at' => $shipment->created_at,
                        'updated_at' => $shipment->updated_at,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_document_admin_shipment');
    }
};
