<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('ecommerce_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('submission_key', 64)->unique();
            $table->string('status')->default('received');
            $table->string('email');
            $table->string('customer_name');
            $table->json('customer');
            $table->json('items');
            $table->decimal('net', 12, 2);
            $table->decimal('vat', 12, 2);
            $table->decimal('gross', 12, 2);
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('ecommerce_orders'); }
};
