<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrmPrintType;
use App\Models\CrmProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CrmProductPersonalizationController extends Controller
{
    public function index(CrmProduct $product): View
    {
        $rules = DB::table('crm_product_position_print_type')->where('crm_product_id', $product->id)->get()->groupBy('position')->map(fn ($rows) => $rows->pluck('crm_print_type_id')->all())->all();
        $printTypes = CrmPrintType::where('technique', '!=', 'setup')->with('priceTiers')->orderBy('technique')->orderBy('name')->get();

        return view('admin.crm-catalog.product-personalizations', compact('product', 'rules', 'printTypes'));
    }

    public function update(Request $request, CrmProduct $product): RedirectResponse
    {
        $data = $request->validate([
            'positions' => ['sometimes', 'array:front,back,sleeve'],
            'positions.*' => ['array', 'max:100'],
            'positions.*.*' => ['integer', 'exists:crm_print_types,id'],
        ]);
        $positions = $data['positions'] ?? [];
        $ids = collect($positions)->flatten()->unique()->values();
        $types = CrmPrintType::whereIn('id', $ids)->get();
        foreach ($types as $type) {
            if (! $type->is_active || $type->technique === 'setup') {
                throw ValidationException::withMessages(['positions' => 'Seleziona lavorazioni attive. Gli impianti si aggiungono come dipendenza del ricamo, non come posizione.']);
            }
            if ($type->technique === 'embroidery' || $type->setup_print_type_id) {
                $setup = CrmPrintType::find($type->setup_print_type_id);
                if (! $setup || ! $setup->is_active || $setup->technique !== 'setup' || $setup->fixed_price === null) {
                    throw ValidationException::withMessages(['positions' => 'Configura prima l’impianto richiesto da '.$type->name.'.']);
                }
            }
        }
        DB::transaction(function () use ($positions, $product) {
            CrmProduct::whereKey($product->id)->lockForUpdate()->firstOrFail();
            DB::table('crm_product_position_print_type')->where('crm_product_id', $product->id)->delete();
            foreach ($positions as $position => $typeIds) {
                foreach (array_unique($typeIds) as $id) {
                    DB::table('crm_product_position_print_type')->insert(['crm_product_id' => $product->id, 'position' => $position, 'crm_print_type_id' => $id]);
                }
            }
        });

        return redirect()->route('admin.crm-catalog.products.personalizations.index', $product)->with('status', 'Personalizzazioni per posizione salvate.');
    }
}
