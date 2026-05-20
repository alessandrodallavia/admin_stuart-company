<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\LeadController as AdminLeadController;
use App\Http\Controllers\Admin\WhatsappConversationController as AdminWhatsappConversationController;
use Illuminate\Support\Facades\Route;

Route::name('admin.')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('login.store');

    Route::middleware('admin.auth')->group(function () {
        Route::get('/', [AdminWhatsappConversationController::class, 'index'])->name('dashboard');
        Route::get('/poll', [AdminWhatsappConversationController::class, 'pollIndex'])->name('dashboard.poll');
        Route::get('/leads/{lead?}', [AdminLeadController::class, 'index'])->name('leads.index');
        Route::patch('/leads/{lead}', [AdminLeadController::class, 'update'])->name('leads.update');
        Route::post('/leads/{lead}/stripe-payment-link', [AdminLeadController::class, 'createStripePaymentLink'])->name('leads.stripe-payment-link');
        Route::get('/leads/{lead}/quote-pdf', [AdminLeadController::class, 'showQuotePdf'])->name('leads.quote-pdf');
        Route::get('/conversations/{conversation}', [AdminWhatsappConversationController::class, 'index'])->name('conversations.show');
        Route::get('/conversations/{conversation}/poll', [AdminWhatsappConversationController::class, 'poll'])->name('conversations.poll');
        Route::patch('/conversations/{conversation}/mode', [AdminWhatsappConversationController::class, 'updateMode'])->name('conversations.mode');
        Route::post('/conversations/{conversation}/messages', [AdminWhatsappConversationController::class, 'sendMessage'])->name('conversations.messages.store');
        Route::get('/messages/{message}/media', [AdminWhatsappConversationController::class, 'showMedia'])->name('messages.media');
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
    });
});
