<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrmCalculatorSlot;
use App\Models\CrmPrintType;
use App\Models\CrmProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CrmCalculatorSlotController extends Controller
{
    public function index(): View
    {
        return view('admin.crm-catalog.calculator', [
            'slots' => CrmCalculatorSlot::with(['product.priceTiers', 'printTypes'])->orderBy('id')->get(),
            'products' => CrmProduct::orderBy('code')->get(),
            'printTypes' => CrmPrintType::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, CrmCalculatorSlot $slot): RedirectResponse
    {
        $data = $request->validate([
            'crm_product_id' => ['nullable', 'integer', 'exists:crm_products,id'],
            'is_enabled_on_site' => ['required', 'boolean'],
            'print_type_ids' => ['sometimes', 'array', 'max:100'],
            'print_type_ids.*' => ['required', 'integer', 'distinct', 'exists:crm_print_types,id'],
        ]);
        DB::transaction(function () use ($slot, $data) {
            $locked = CrmCalculatorSlot::whereKey($slot->id)->lockForUpdate()->firstOrFail();
            $product = isset($data['crm_product_id']) ? CrmProduct::with('priceTiers')->findOrFail($data['crm_product_id']) : null;
            $printIds = $data['print_type_ids'] ?? [];
            $prints = CrmPrintType::with('priceTiers')->whereIn('id', $printIds)->get();
            if ($prints->contains(fn ($print) => ! $print->is_active)) {
                throw ValidationException::withMessages(['print_type_ids' => 'Seleziona soltanto lavorazioni attive.']);
            }
            if ($data['is_enabled_on_site']) {
                if (! $product || ! $product->is_active || ! $product->is_visible_on_site || $product->priceTiers->isEmpty()) {
                    throw ValidationException::withMessages(['crm_product_id' => 'Per abilitare questa opzione scegli un prodotto attivo, visibile sul sito e con fasce prezzo.']);
                }
                if ($prints->isEmpty() || $prints->contains(fn ($print) => $print->priceTiers->isEmpty())) {
                    throw ValidationException::withMessages(['print_type_ids' => 'Per abilitare questa opzione seleziona lavorazioni con fasce prezzo.']);
                }
            }
            $locked->fill(['crm_product_id' => $product?->id, 'is_enabled_on_site' => $data['is_enabled_on_site']])->save();
            $locked->printTypes()->sync($printIds);
        });

        return redirect()->route('admin.crm-catalog.calculator.index')->with('status', 'Associazione salvata.');
    }
}
