<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $columns = [
            'calculator_used' => fn (Blueprint $table) => $table->boolean('calculator_used')->default(false),
            'calculator_requires_quote' => fn (Blueprint $table) => $table->boolean('calculator_requires_quote')->default(false),
            'calculator_model' => fn (Blueprint $table) => $table->string('calculator_model')->nullable(),
            'calculator_quantity_band' => fn (Blueprint $table) => $table->string('calculator_quantity_band')->nullable(),
            'calculator_quantity' => fn (Blueprint $table) => $table->unsignedInteger('calculator_quantity')->nullable(),
            'calculator_personalization' => fn (Blueprint $table) => $table->string('calculator_personalization')->nullable(),
            'calculator_unit_price_ex_vat' => fn (Blueprint $table) => $table->decimal('calculator_unit_price_ex_vat', 10, 2)->nullable(),
            'calculator_subtotal_ex_vat' => fn (Blueprint $table) => $table->decimal('calculator_subtotal_ex_vat', 10, 2)->nullable(),
            'calculator_shipping_cost' => fn (Blueprint $table) => $table->decimal('calculator_shipping_cost', 10, 2)->nullable(),
            'calculator_total_inc_vat' => fn (Blueprint $table) => $table->decimal('calculator_total_inc_vat', 10, 2)->nullable(),
            'calculator_shipping_days' => fn (Blueprint $table) => $table->unsignedSmallInteger('calculator_shipping_days')->nullable(),
            'calculator_whatsapp_click' => fn (Blueprint $table) => $table->boolean('calculator_whatsapp_click')->default(false),
            'live_mockup_used' => fn (Blueprint $table) => $table->boolean('live_mockup_used')->default(false),
            'live_mockup_color' => fn (Blueprint $table) => $table->string('live_mockup_color', 50)->nullable(),
            'live_mockup_front_file' => fn (Blueprint $table) => $table->text('live_mockup_front_file')->nullable(),
            'live_mockup_back_file' => fn (Blueprint $table) => $table->text('live_mockup_back_file')->nullable(),
            'live_mockup_configured_at' => fn (Blueprint $table) => $table->timestamp('live_mockup_configured_at')->nullable(),
            'live_mockup_whatsapp_click' => fn (Blueprint $table) => $table->boolean('live_mockup_whatsapp_click')->default(false),
        ];

        foreach ($columns as $column => $definition) {
            if (! Schema::hasColumn('leads', $column)) {
                Schema::table('leads', $definition);
            }
        }
    }

    public function down(): void
    {
        $columns = [
            'calculator_used', 'calculator_requires_quote', 'calculator_model',
            'calculator_quantity_band', 'calculator_quantity', 'calculator_personalization',
            'calculator_unit_price_ex_vat', 'calculator_subtotal_ex_vat',
            'calculator_shipping_cost', 'calculator_total_inc_vat',
            'calculator_shipping_days', 'calculator_whatsapp_click',
            'live_mockup_used', 'live_mockup_color', 'live_mockup_front_file',
            'live_mockup_back_file', 'live_mockup_configured_at', 'live_mockup_whatsapp_click',
        ];

        foreach ($columns as $column) {
            if (Schema::hasColumn('leads', $column)) {
                Schema::table('leads', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
