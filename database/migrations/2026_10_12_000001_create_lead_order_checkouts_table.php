<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_order_checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_quote_pdf_id')->nullable()->constrained('lead_quote_pdfs')->nullOnDelete();
            $table->foreignId('lead_sales_sheet_id')->constrained('lead_sales_sheets')->cascadeOnDelete();
            $table->string('token', 80)->unique();
            $table->string('proposal_number', 100);
            $table->string('order_number', 100)->nullable();
            $table->string('status', 30)->default('draft');
            $table->string('payment_type', 20)->default('full');
            $table->string('payment_label', 100)->default('Pagamento completo');
            $table->string('currency', 3)->default('EUR');
            $table->decimal('vat_rate', 5, 2)->default(22);
            $table->decimal('product_subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('rounding_adjustment', 12, 2)->default(0);
            $table->decimal('shipping_amount', 12, 2)->default(0);
            $table->decimal('order_taxable_amount', 12, 2)->default(0);
            $table->decimal('order_vat_amount', 12, 2)->default(0);
            $table->decimal('order_total_amount', 12, 2)->default(0);
            $table->decimal('taxable_amount', 12, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->json('items');
            $table->string('contact_email')->nullable();
            $table->string('contact_first_name')->nullable();
            $table->string('contact_last_name')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('shipping_company')->nullable();
            $table->string('shipping_first_name')->nullable();
            $table->string('shipping_last_name')->nullable();
            $table->string('shipping_address')->nullable();
            $table->string('shipping_postal_code', 20)->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_province', 10)->nullable();
            $table->string('billing_customer_type', 20)->nullable();
            $table->string('billing_first_name')->nullable();
            $table->string('billing_last_name')->nullable();
            $table->string('billing_company_name')->nullable();
            $table->string('billing_tax_code', 40)->nullable();
            $table->string('billing_vat_number', 40)->nullable();
            $table->string('billing_recipient_code', 7)->nullable();
            $table->string('billing_pec')->nullable();
            $table->string('billing_address')->nullable();
            $table->string('billing_postal_code', 20)->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_province', 10)->nullable();
            $table->text('notes')->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->timestamp('details_completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_order_checkouts');
    }
};
