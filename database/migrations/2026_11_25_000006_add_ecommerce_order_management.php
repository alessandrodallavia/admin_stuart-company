<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('ecommerce_orders', function (Blueprint $table) {
   $table->string('order_status')->default('new');
   $table->string('fulfillment_method')->default('shipping');
   $table->string('fulfillment_status')->default('pending');
   $table->string('fulfillment_carrier')->nullable();
   $table->string('fulfillment_tracking')->nullable();
   $table->text('fulfillment_notes')->nullable();
   $table->text('internal_notes')->nullable();
   $table->timestamp('fulfilled_at')->nullable();
   $table->unsignedBigInteger('invoice_document_id')->nullable()->index();
   $table->unsignedBigInteger('delivery_document_id')->nullable()->index();
  });
  Schema::create('ecommerce_order_events', function (Blueprint $table) {
   $table->id(); $table->unsignedBigInteger('ecommerce_order_id')->index(); $table->unsignedBigInteger('admin_user_id')->nullable();
   $table->string('kind'); $table->json('details'); $table->string('notification_status')->nullable(); $table->text('notification_error')->nullable(); $table->timestamp('notified_at')->nullable(); $table->timestamps();
  });
 }
 public function down(): void {
  Schema::dropIfExists('ecommerce_order_events');
  Schema::table('ecommerce_orders', fn (Blueprint $table) => $table->dropColumn(['order_status','fulfillment_method','fulfillment_status','fulfillment_carrier','fulfillment_tracking','fulfillment_notes','internal_notes','fulfilled_at','invoice_document_id','delivery_document_id']));
 }
};
