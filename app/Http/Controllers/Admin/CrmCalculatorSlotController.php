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
            'printTypes' => CrmPrintType::where('is_active', true)->where('technique', '!=', 'setup')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, CrmCalculatorSlot $slot): RedirectResponse
    {
        $data = $request->validate([
            'crm_product_id' => ['nullable', 'integer', 'exists:crm_products,id'],
            'is_enabled_on_site' => ['required', 'boolean'],
            'print_type_ids' => ['prohibited'],
        ]);
        DB::transaction(function () use ($slot, $data) {
            $locked = CrmCalculatorSlot::whereKey($slot->id)->lockForUpdate()->firstOrFail();
            $product = isset($data['crm_product_id']) ? CrmProduct::with('priceTiers')->findOrFail($data['crm_product_id']) : null;
            $printIds = $product ? DB::table('crm_product_position_print_type')->where('crm_product_id', $product->id)->pluck('crm_print_type_id')->unique()->all() : [];
            $prints = CrmPrintType::with('priceTiers')->whereIn('id', $printIds)->get();
            if ($prints->contains(fn ($print) => ! $print->is_active)) {
                throw ValidationException::withMessages(['print_type_ids' => 'Seleziona soltanto lavorazioni attive.']);
            }
            if ($data['is_enabled_on_site']) {
                if (! $product || ! $product->is_active || ! $product->is_visible_on_site || $product->priceTiers->isEmpty()) {
                    throw ValidationException::withMessages(['crm_product_id' => 'Per abilitare questa opzione scegli un prodotto attivo, visibile sul sito e con fasce prezzo.']);
                }
                if ($prints->isEmpty() || $prints->contains(fn ($print) => $print->technique !== 'setup' && $print->priceTiers->isEmpty())) {
                    throw ValidationException::withMessages(['print_type_ids' => 'Per abilitare questa opzione configura le personalizzazioni del prodotto per posizione e le relative fasce prezzo.']);
                }
            }
            if ($data['is_enabled_on_site']) {
                foreach ($prints as $print) {
                    if ($print->technique === 'embroidery' || $print->setup_print_type_id) {
                        $setup = CrmPrintType::find($print->setup_print_type_id);
                        if (! $setup || ! $setup->is_active || $setup->technique !== 'setup' || $setup->fixed_price === null) {
                            throw ValidationException::withMessages(['print_type_ids' => 'Configura il prezzo unico dell’impianto richiesto dal ricamo prima di abilitarlo.']);
                        }
                    }
                }
            }
            $locked->fill(['crm_product_id' => $product?->id, 'is_enabled_on_site' => $data['is_enabled_on_site']])->save();
            // Position associations belong to the product and are edited explicitly on its own page.
        });

        return redirect()->route('admin.crm-catalog.calculator.index')->with('status', 'Associazione salvata.');
    }
}
