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
    public function catalog(Request $request, WhatsappConversation $conversation, HoodieQuoteCalculator $calculator): JsonResponse
    {
        abort_unless($conversation->mode === 'manual', 403);
        try {
            $data = $request->validate(['family' => ['nullable', Rule::in(['hoodie', 'tshirt'])]]);
            $family = $data['family'] ?? ($conversation->lead?->calculator_used && ! $conversation->lead->isHoodieRequest() ? 'tshirt' : 'hoodie');
            $c = $calculator->catalog($family);

            return response()->json([
                'family' => $family,
                'models' => $family === 'tshirt' ? ['tshirt' => 'T-shirt'] : $c['models'], 'qualities' => $c['qualities'] ?? $c['models'],
                'products' => collect($c['crm_products'])->map(fn ($qualities) => collect($qualities)->map(fn ($product) => ['code' => $product['code'], 'name' => $product['name']])),
                'personalizations' => $c['personalizations'], 'personalization_models' => $c['personalization_models'],
                'minimum_quantity' => $c['minimum_quantity'], 'maximum_quantity' => $c['custom_quote_above_quantity'],
                'defaults' => $calculator->defaults($conversation->lead, $family),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }
    }

    public function preview(Request $request, WhatsappConversation $conversation, HoodieQuoteCalculator $calculator): JsonResponse
    {
        abort_unless($conversation->mode === 'manual', 403);
        try {
            $familyData = $request->validate(['family' => ['nullable', Rule::in(['hoodie', 'tshirt'])]]);
            $family = $familyData['family'] ?? 'hoodie';
            $c = $calculator->catalog($family);
            $qualities = $c['qualities'] ?? $c['models'];
            $data = $request->validate([
                'family' => ['sometimes', Rule::in(['hoodie', 'tshirt'])],
                'model' => ['required', Rule::in($family === 'tshirt' ? ['tshirt'] : array_keys($c['models']))],
                'quantity' => ['required', 'integer', 'min:'.$c['minimum_quantity'], 'max:'.$c['custom_quote_above_quantity']],
                'personalization' => ['required', Rule::in(array_keys($c['personalizations']))],
                'qualities' => ['required', 'array', 'min:1', 'max:'.count($qualities)],
                'qualities.*' => ['required', 'distinct', Rule::in(array_keys($qualities))],
            ], ['quantity.max' => 'Oltre '.number_format($c['custom_quote_above_quantity'], 0, ',', '.').' pezzi serve una quotazione dedicata.']);

            return response()->json($calculator->generate($data));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }
    }
}
