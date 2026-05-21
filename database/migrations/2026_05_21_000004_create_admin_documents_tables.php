<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_documents', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            $table->unsignedInteger('number')->nullable();
            $table->unsignedSmallInteger('year');
            $table->string('code', 60)->nullable()->unique();
            $table->date('document_date');
            $table->string('status', 32)->default('draft');
            $table->string('payment_status', 32)->default('unpaid');
            $table->string('currency', 3)->default('EUR');

            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 40)->nullable();
            $table->string('customer_tax_code', 40)->nullable();
            $table->string('customer_vat_number', 40)->nullable();
            $table->string('customer_address')->nullable();
            $table->string('customer_city', 120)->nullable();
            $table->string('customer_postal_code', 20)->nullable();
            $table->string('customer_country', 2)->default('IT');

            $table->foreignId('source_document_id')->nullable()->constrained('admin_documents')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('vat_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['type', 'year', 'number']);
            $table->index(['type', 'status']);
            $table->index('payment_status');
        });

        Schema::create('admin_document_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_document_id')->constrained('admin_documents')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(1);
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(22);
            $table->decimal('line_subtotal', 12, 2)->default(0);
            $table->decimal('line_vat', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('admin_document_payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_document_id')->constrained('admin_documents')->cascadeOnDelete();
            $table->date('due_date');
            $table->string('method', 60)->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->date('paid_at')->nullable();
            $table->string('status', 32)->default('unpaid');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['due_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_document_payment_schedules');
        Schema::dropIfExists('admin_document_items');
        Schema::dropIfExists('admin_documents');
    }
};
