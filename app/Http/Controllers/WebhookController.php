<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsappWebhookJob;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        // 🔐 VERIFICA META (GET)
        if ($request->isMethod('get')) {
            return $this->verify($request);
        }

        // 📩 EVENTO (POST)
        $data = $request->all();

        // ⚡ dispatch async
        ProcessWhatsappWebhookJob::dispatch($data)->onQueue('admin');

        return response('EVENT_RECEIVED', 200);
    }

    private function verify(Request $request)
    {
        $verify_token = config('services.whatsapp.webhook_token');

        if ($request->input('hub_verify_token') === $verify_token) {
            return response($request->input('hub_challenge'), 200);
        }

        return response('Token non valido', 403);
    }
}
