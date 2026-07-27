<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_sales_items', function (Blueprint $table) {
            $table->decimal('final_unit_price', 12, 4)->nullable()->after('product_unit_price');
            $table->boolean('final_price_overridden')->default(false)->after('final_unit_price');
            $table->json('colors')->nullable()->after('final_price_overridden');
            $table->text('notes')->nullable()->after('colors');
        });

        Schema::create('lead_sales_item_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_sales_item_id')->constrained()->cascadeOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_order_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_sales_sheet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->foreignId('email_message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_name');
            $table->unsignedInteger('version');
            $table->string('filename');
            $table->string('to_email');
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['lead_sales_sheet_id', 'order_name', 'version'], 'lead_order_dispatch_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_order_dispatches');
        Schema::dropIfExists('lead_sales_item_attachments');

        Schema::table('lead_sales_items', function (Blueprint $table) {
            $table->dropColumn(['final_unit_price', 'final_price_overridden', 'colors', 'notes']);
        });
    }
};
