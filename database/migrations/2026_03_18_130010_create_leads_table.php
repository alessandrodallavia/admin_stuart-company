<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();

            // dati lead
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('club')->nullable();
            $table->string('city')->nullable();
            $table->text('message')->nullable();

            // consensi GDPR
            $table->boolean('privacy_consent')->default(false);
            $table->boolean('marketing_consent')->default(false);

            // UTM tracking
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();

            // Ads click id
            $table->string('gclid')->nullable();
            $table->string('fbclid')->nullable();

            // contesto visita
            $table->string('landing_page')->nullable();
            $table->string('entry_page')->nullable();
            $table->text('referrer')->nullable();

            // device info
            $table->string('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
