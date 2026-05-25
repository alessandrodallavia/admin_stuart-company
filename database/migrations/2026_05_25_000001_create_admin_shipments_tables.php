<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_document_id')->nullable()->constrained('admin_documents')->nullOnDelete();
            $table->string('carrier', 20);
            $table->string('status', 30)->default('draft');
            $table->string('reference')->nullable();

            $table->string('recipient_name');
            $table->string('recipient_email')->nullable();
            $table->string('recipient_phone', 40)->nullable();
            $table->string('recipient_address');
            $table->string('recipient_street_number', 30)->nullable();
            $table->string('recipient_city', 120);
            $table->string('recipient_province', 10)->nullable();
            $table->string('recipient_postal_code', 20);
            $table->string('recipient_country', 2)->default('IT');

            $table->unsignedInteger('parcels_count')->default(1);
            $table->decimal('weight_kg', 8, 2)->default(0.5);
            $table->decimal('volume_m3', 8, 3)->default(0.01);
            $table->decimal('cash_on_delivery', 10, 2)->nullable();

            $table->string('tracking_number')->nullable();
            $table->string('carrier_reference')->nullable();
            $table->json('carrier_response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamps();

            $table->index(['carrier', 'status']);
            $table->index('tracking_number');
            $table->index('reference');
            $table->index('shipped_at');
        });

        Schema::create('admin_shipment_parcels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_shipment_id')->constrained('admin_shipments')->cascadeOnDelete();
            $table->unsignedInteger('parcel_number')->default(1);
            $table->string('parcel_id')->nullable();
            $table->string('tracking_number')->nullable();
            $table->longText('label_stream')->nullable();
            $table->decimal('weight_kg', 8, 2)->nullable();
            $table->decimal('volume_m3', 8, 3)->nullable();
            $table->timestamps();

            $table->index('tracking_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_shipment_parcels');
        Schema::dropIfExists('admin_shipments');
    }
};
