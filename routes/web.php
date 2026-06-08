<?php

use App\Http\Controllers\Admin\AdminUserController as AdminAdminUserController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DocumentController as AdminDocumentController;
use App\Http\Controllers\Admin\EmailController as AdminEmailController;
use App\Http\Controllers\Admin\LeadController as AdminLeadController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\ShipmentController as AdminShipmentController;
use App\Http\Controllers\Admin\WhatsappConversationController as AdminWhatsappConversationController;
use Illuminate\Support\Facades\Route;

Route::name('admin.')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('login.store');

    Route::middleware('admin.auth')->group(function () {
        Route::get('/notifications', [AdminNotificationController::class, 'index'])->name('notifications.index');
        Route::patch('/notifications/read-all', [AdminNotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::get('/notifications/{notification}', [AdminNotificationController::class, 'open'])->name('notifications.open');

        Route::middleware('admin.permission:whatsapp.view')->group(function () {
            Route::get('/', [AdminWhatsappConversationController::class, 'index'])->name('dashboard');
            Route::get('/poll', [AdminWhatsappConversationController::class, 'pollIndex'])->name('dashboard.poll');
            Route::get('/conversations/{conversation}', [AdminWhatsappConversationController::class, 'index'])->name('conversations.show');
            Route::get('/conversations/{conversation}/poll', [AdminWhatsappConversationController::class, 'poll'])->name('conversations.poll');
            Route::get('/messages/{message}/media', [AdminWhatsappConversationController::class, 'showMedia'])->name('messages.media');
        });

        Route::middleware('admin.permission:whatsapp.manage')->group(function () {
            Route::patch('/conversations/{conversation}/mode', [AdminWhatsappConversationController::class, 'updateMode'])->name('conversations.mode');
            Route::patch('/conversations/{conversation}/mark-unread', [AdminWhatsappConversationController::class, 'markAsUnread'])->name('conversations.mark-unread');
            Route::patch('/conversations/{conversation}/follow-up-exclusion', [AdminWhatsappConversationController::class, 'updateFollowUpExclusion'])->name('conversations.follow-up-exclusion');
            Route::post('/conversations/{conversation}/follow-ups', [AdminWhatsappConversationController::class, 'storeFollowUp'])->name('conversations.follow-ups.store');
            Route::patch('/conversations/{conversation}/follow-ups/{followUp}/cancel', [AdminWhatsappConversationController::class, 'cancelFollowUp'])->name('conversations.follow-ups.cancel');
            Route::post('/conversations/{conversation}/messages', [AdminWhatsappConversationController::class, 'sendMessage'])->name('conversations.messages.store');
        });

        Route::middleware('admin.permission:leads.view')->group(function () {
            Route::get('/leads/board', [AdminLeadController::class, 'board'])->name('leads.board');
            Route::get('/leads/{lead?}', [AdminLeadController::class, 'index'])->name('leads.index');
            Route::get('/leads/{lead}/quote-pdf', [AdminLeadController::class, 'showQuotePdf'])->name('leads.quote-pdf');
        });

        Route::middleware('admin.permission:leads.manage')->group(function () {
            Route::patch('/leads/{lead}', [AdminLeadController::class, 'update'])->name('leads.update');
            Route::post('/leads/{lead}/quote-pdf/whatsapp', [AdminLeadController::class, 'sendQuotePdfWhatsapp'])->name('leads.quote-pdf.whatsapp');
            Route::post('/leads/{lead}/stripe-payment-link', [AdminLeadController::class, 'createStripePaymentLink'])->name('leads.stripe-payment-link');
            Route::post('/leads/{lead}/stripe-payment-link/whatsapp', [AdminLeadController::class, 'sendStripePaymentLinkWhatsapp'])->name('leads.stripe-payment-link.whatsapp');
        });

        Route::middleware(['admin.permission:leads.manage', 'admin.permission:email.manage'])->group(function () {
            Route::post('/leads/{lead}/quote-pdf/email', [AdminLeadController::class, 'sendQuotePdfEmail'])->name('leads.quote-pdf.email');
            Route::post('/leads/{lead}/stripe-payment-link/email', [AdminLeadController::class, 'sendStripePaymentLinkEmail'])->name('leads.stripe-payment-link.email');
        });

        Route::middleware('admin.permission:email.view')->group(function () {
            Route::get('/email', [AdminEmailController::class, 'index'])->name('email.index');
            Route::get('/email/conversations/{conversation}', [AdminEmailController::class, 'index'])->name('email.conversations.show');
            Route::get('/email/attachments/{attachment}', [AdminEmailController::class, 'downloadAttachment'])->name('email.attachments.download');
        });

        Route::middleware('admin.permission:email.manage')->group(function () {
            Route::post('/email/sync', [AdminEmailController::class, 'sync'])->name('email.sync');
            Route::post('/email/conversations', [AdminEmailController::class, 'storeConversation'])->name('email.conversations.store');
            Route::post('/email/conversations/{conversation}/messages', [AdminEmailController::class, 'sendMessage'])->name('email.messages.store');
        });

        Route::middleware('admin.permission:documents.view')->group(function () {
            Route::get('/documents/payments', [AdminDocumentController::class, 'payments'])->name('documents.payments');
            Route::get('/documents/import-xml', [AdminDocumentController::class, 'importXml'])->name('documents.import-xml');
            Route::get('/documents/export-sdi', [AdminDocumentController::class, 'exportSdi'])->name('documents.export-sdi');
            Route::get('/documents', [AdminDocumentController::class, 'index'])->name('documents.index');
            Route::get('/documents/create', [AdminDocumentController::class, 'create'])->name('documents.create');
            Route::get('/documents/{document}', [AdminDocumentController::class, 'show'])->name('documents.show');
            Route::get('/documents/{document}/preview', [AdminDocumentController::class, 'preview'])->name('documents.preview');
            Route::get('/documents/{document}/xml', [AdminDocumentController::class, 'exportXml'])->name('documents.xml');
            Route::get('/documents/{document}/edit', [AdminDocumentController::class, 'edit'])->name('documents.edit');
        });

        Route::middleware('admin.permission:documents.manage')->group(function () {
            Route::post('/documents', [AdminDocumentController::class, 'store'])->name('documents.store');
            Route::patch('/documents/{document}', [AdminDocumentController::class, 'update'])->name('documents.update');
            Route::delete('/documents/{document}', [AdminDocumentController::class, 'destroy'])->name('documents.destroy');
            Route::post('/documents/{document}/duplicate', [AdminDocumentController::class, 'duplicate'])->name('documents.duplicate');
            Route::post('/documents/{document}/relations', [AdminDocumentController::class, 'storeRelation'])->name('documents.relations.store');
            Route::delete('/documents/{document}/relations/{relation}', [AdminDocumentController::class, 'destroyRelation'])->name('documents.relations.destroy');
            Route::delete('/documents/{document}/source-links/{linkedDocument}', [AdminDocumentController::class, 'destroySourceLink'])->name('documents.source-links.destroy');
            Route::patch('/documents/{document}/payments', [AdminDocumentController::class, 'updatePayment'])->name('documents.payments.update');
        });

        Route::middleware('admin.permission:shipments.manage')->group(function () {
            Route::get('/shipments/documents/search', [AdminShipmentController::class, 'documentSearch'])->name('shipments.documents.search');
            Route::get('/shipments/create', [AdminShipmentController::class, 'create'])->name('shipments.create');
            Route::post('/shipments', [AdminShipmentController::class, 'store'])->name('shipments.store');
            Route::post('/shipments/bordero', [AdminShipmentController::class, 'bordero'])->name('shipments.bordero');
            Route::post('/shipments/{shipment}/retry', [AdminShipmentController::class, 'retry'])->name('shipments.retry');
            Route::post('/shipments/{shipment}/parcels/{parcel}/label', [AdminShipmentController::class, 'label'])->name('shipments.parcels.label');
        });

        Route::middleware('admin.permission:shipments.view')->group(function () {
            Route::get('/shipments', [AdminShipmentController::class, 'index'])->name('shipments.index');
            Route::get('/shipments/{shipment}', [AdminShipmentController::class, 'show'])->name('shipments.show');
        });

        Route::middleware('admin.permission:admin_users.manage')->group(function () {
            Route::get('/settings/users', [AdminAdminUserController::class, 'index'])->name('users.index');
            Route::post('/settings/users', [AdminAdminUserController::class, 'store'])->name('users.store');
            Route::patch('/settings/users/{adminUser}', [AdminAdminUserController::class, 'update'])->name('users.update');
            Route::put('/settings/users/{adminUser}/email-account', [AdminAdminUserController::class, 'updateEmailAccount'])->name('users.email-account.update');
        });

        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
    });
});
