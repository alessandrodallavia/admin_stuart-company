<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappConversation;
use App\Services\HoodieQuoteCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class HoodieQuoteController extends Controller
{
    public function catalog(WhatsappConversation $conversation, HoodieQuoteCalculator $calculator): JsonResponse
    {
        abort_unless($conversation->mode === 'manual', 403);
        try {
            $c = $calculator->catalog();

            return response()->json([
                'models' => $c['models'], 'qualities' => $c['qualities'],
                'personalizations' => $c['personalizations'], 'personalization_models' => $c['personalization_models'],
                'minimum_quantity' => $c['minimum_quantity'], 'maximum_quantity' => $c['custom_quote_above_quantity'],
                'defaults' => $calculator->defaults($conversation->lead),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }
    }

    public function preview(Request $request, WhatsappConversation $conversation, HoodieQuoteCalculator $calculator): JsonResponse
    {
        abort_unless($conversation->mode === 'manual', 403);
        try {
            $c = $calculator->catalog();
            $data = $request->validate([
                'model' => ['required', Rule::in(array_keys($c['models']))],
                'quantity' => ['required', 'integer', 'min:'.$c['minimum_quantity'], 'max:'.$c['custom_quote_above_quantity']],
                'personalization' => ['required', Rule::in(array_keys($c['personalizations']))],
                'qualities' => ['required', 'array', 'min:1', 'max:'.count($c['qualities'])],
                'qualities.*' => ['required', 'distinct', Rule::in(array_keys($c['qualities']))],
            ], ['quantity.max' => 'Oltre 3.000 pezzi serve una quotazione dedicata.']);

            return response()->json($calculator->generate($data));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }
    }
}
