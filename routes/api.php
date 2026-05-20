<?php

use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

Route::match(['get', 'post'], '/webhook/test-whatsapp-api', [WebhookController::class, 'handle'])
    ->name('whatsapp.webhook');

Route::match(['get', 'post'], '/deal_change_status', function (Request $request) {
    $expectedToken = config('services.brevo.webhook_token_inbound');
    $receivedToken = $request->bearerToken()
        ?: $request->input('token')
        ?: $request->header('token')
        ?: $request->header('X-Brevo-Token');

    if (! $expectedToken || ! $receivedToken || ! hash_equals($expectedToken, $receivedToken)) {
        Log::warning('mockup_sent_invalid_token');

        return response()->json(['message' => 'Unauthorized'], 401);
    }

    $data = $request->except('token');

    Log::info('mockup_sent_received', $data);

    return response()->json([
        'success' => true,
        'received' => $data,
    ]);
})->name('brevo.deal-change-status');
