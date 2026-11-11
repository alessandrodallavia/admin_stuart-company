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

        $product->load('colors');
        $areas = DB::table('crm_product_print_areas')->where('crm_product_id', $product->id)->get()->keyBy('position')->map(fn ($row) => (array) $row)->all();

        return view('admin.crm-catalog.product-personalizations', compact('product', 'rules', 'printTypes', 'areas'));
    }

    public function update(Request $request, CrmProduct $product): RedirectResponse
    {
        $data = $request->validate([
            'areas' => ['sometimes', 'array:front,back,sleeve'],
            'areas.*' => ['array:width_cm,height_cm,x_percent,y_percent,width_percent,height_percent'],
            'areas.*.width_cm' => ['nullable', 'numeric', 'min:0.1', 'max:200'],
            'areas.*.height_cm' => ['nullable', 'numeric', 'min:0.1', 'max:200'],
            'areas.*.x_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'areas.*.y_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'areas.*.width_percent' => ['nullable', 'numeric', 'min:0.1', 'max:100'],
            'areas.*.height_percent' => ['nullable', 'numeric', 'min:0.1', 'max:100'],
            'positions' => ['sometimes', 'array:front,back,sleeve'],
            'positions.*' => ['array', 'max:100'],
            'positions.*.*' => ['integer', 'exists:crm_print_types,id'],
        ]);
        $positions = $data['positions'] ?? [];
        $areas = $data['areas'] ?? null;
        foreach ($areas ?? [] as $side => $area) {
            $filled = array_filter($area, fn ($v) => $v !== null && $v !== '');
            if (! $filled) {
                continue;
            }
            unset($filled['height_percent']);
            if (count($filled) !== 5) {
                throw ValidationException::withMessages(['areas' => 'Completa tutte le misure dell’area.']);
            }
            $area['height_percent'] = round($area['width_percent'] * $area['height_cm'] / $area['width_cm'] * 0.8, 2);
            $areas[$side] = $area;
            if ($area['x_percent'] + $area['width_percent'] > 100 || $area['y_percent'] + $area['height_percent'] > 100) {
                throw ValidationException::withMessages(['areas' => 'Completa tutte le misure dell’area e mantienila entro il 100% della tavola di anteprima.']);
            }
        }
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
        DB::transaction(function () use ($positions, $product, $areas) {
            CrmProduct::whereKey($product->id)->lockForUpdate()->firstOrFail();
            if ($areas !== null) {
                DB::table('crm_product_print_areas')->where('crm_product_id', $product->id)->delete();
                foreach ($areas as $side => $area) {
                    if (! empty($area['width_cm'])) {
                        DB::table('crm_product_print_areas')->insert(['crm_product_id' => $product->id, 'position' => $side, ...$area]);
                    }
                }
            }
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
