<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrmBrand;
use App\Models\CrmPrintPriceTier;
use App\Models\CrmPrintType;
use App\Models\CrmProduct;
use App\Models\CrmProductPriceTier;
use App\Models\CrmSupplier;
use App\Models\LeadCategory;
use App\Services\CrmProductCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrmCatalogController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('q')->toString());

        return view('admin.crm-catalog.index', [
            'categories' => LeadCategory::orderBy('sort_order')->orderBy('name')->get(),
            'brands' => CrmBrand::with('suppliers')->orderBy('name')->get(),
            'suppliers' => CrmSupplier::with('brands')->orderBy('name')->get(),
            'products' => CrmProduct::with(['priceTiers', 'brand', 'suppliers', 'colors', 'sizes'])
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")
                    ->orWhereHas('brand', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('suppliers', fn ($query) => $query->where('name', 'like', "%{$search}%"))))
                ->orderBy('name')->get(),
            'printTypes' => CrmPrintType::with('priceTiers')
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")))
                ->orderBy('name')->get(),
            'search' => $search,
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255', 'unique:lead_categories,name'], 'sort_order' => ['nullable', 'integer', 'min:0']]);
        LeadCategory::create($data + ['is_active' => true]);

        return back()->with('status', 'Categoria salvata.');
    }

    public function toggleCategory(LeadCategory $category): RedirectResponse
    {
        $category->update(['is_active' => ! $category->is_active]);

        return back()->with('status', $category->is_active ? 'Categoria riattivata.' : 'Categoria disattivata.');
    }

    public function destroyCategory(LeadCategory $category): RedirectResponse
    {
        if ($category->leads()->exists()) {
            return back()->withErrors(['category' => 'La categoria è già associata a uno o più lead: puoi disattivarla, ma non eliminarla.']);
        }
        $category->delete();

        return back()->with('status', 'Categoria eliminata definitivamente.');
    }

    public function storeProduct(Request $request, CrmProductCatalogService $catalog): RedirectResponse
    {
        $catalog->save($request);

        return back()->with('status', 'Prodotto salvato.');
    }

    public function updateProduct(Request $request, CrmProduct $product, CrmProductCatalogService $catalog): RedirectResponse
    {
        $catalog->save($request, $product);

        return back()->with('status', 'Prodotto modificato.');
    }

    public function destroyProduct(CrmProduct $product): RedirectResponse
    {
        $datasheet = $product->datasheet_path;
        $product->delete();
        if ($datasheet) {
            Storage::disk('local')->delete($datasheet);
        }

        return back()->with('status', 'Prodotto eliminato.');
    }

    public function datasheet(CrmProduct $product): StreamedResponse
    {
        abort_unless($product->datasheet_path && Storage::disk('local')->exists($product->datasheet_path), 404);

        return Storage::disk('local')->download($product->datasheet_path, $product->datasheet_filename ?: 'scheda-prodotto.pdf', ['Content-Type' => 'application/pdf']);
    }

    public function storeProductTier(Request $request, CrmProduct $product): RedirectResponse
    {
        $data = $this->productTierData($request);
        $this->ensureNoOverlap($product->priceTiers(), $data);
        $product->priceTiers()->create($data);

        return back()->with('status', 'Fascia economica prodotto salvata.');
    }

    public function updateProductTier(Request $request, CrmProductPriceTier $tier): RedirectResponse
    {
        $request->mergeIfMissing(['unit_cost' => $tier->unit_cost ?? $tier->product->unit_cost]);
        $data = $this->productTierData($request);
        $this->ensureNoOverlap($tier->product->priceTiers(), $data, $tier->id);
        $tier->update($data);

        return back()->with('status', 'Fascia economica prodotto modificata.');
    }

    public function destroyProductTier(CrmProductPriceTier $tier): RedirectResponse
    {
        $tier->delete();

        return back()->with('status', 'Fascia prodotto eliminata.');
    }

    public function storePrintType(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:100', 'unique:crm_print_types,code'], 'name' => ['required', 'string', 'max:255']]);
        CrmPrintType::create($data + ['is_active' => true]);

        return back()->with('status', 'Stampa salvata.');
    }

    public function updatePrintType(Request $request, CrmPrintType $printType): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:100', Rule::unique('crm_print_types', 'code')->ignore($printType)], 'name' => ['required', 'string', 'max:255']]);
        $printType->update($data);

        return back()->with('status', 'Stampa modificata.');
    }

    public function destroyPrintType(CrmPrintType $printType): RedirectResponse
    {
        $printType->delete();

        return back()->with('status', 'Stampa eliminata.');
    }

    public function storePrintTier(Request $request, CrmPrintType $printType): RedirectResponse
    {
        $data = $this->printTierData($request);
        $this->ensureNoOverlap($printType->priceTiers(), $data);
        $printType->priceTiers()->create($data);

        return back()->with('status', 'Fascia stampa salvata.');
    }

    public function updatePrintTier(Request $request, CrmPrintPriceTier $tier): RedirectResponse
    {
        $data = $this->printTierData($request);
        $this->ensureNoOverlap($tier->newQuery()->where('crm_print_type_id', $tier->crm_print_type_id), $data, $tier->id);
        $tier->update($data);

        return back()->with('status', 'Fascia stampa modificata.');
    }

    public function destroyPrintTier(CrmPrintPriceTier $tier): RedirectResponse
    {
        $tier->delete();

        return back()->with('status', 'Fascia stampa eliminata.');
    }

    private function productTierData(Request $request): array
    {
        return $request->validate([
            'min_quantity' => ['required', 'numeric', 'min:0.01'],
            'max_quantity' => ['nullable', 'numeric', 'gte:min_quantity'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
        ]);
    }

    private function printTierData(Request $request): array
    {
        return $request->validate([
            'min_quantity' => ['required', 'numeric', 'min:0.01'],
            'max_quantity' => ['nullable', 'numeric', 'gte:min_quantity'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'unit_price' => ['required', 'numeric', 'min:0'],
        ]);
    }

    private function ensureNoOverlap($query, array $data, ?int $ignoreId = null): void
    {
        $min = (float) $data['min_quantity'];
        $max = isset($data['max_quantity']) ? (float) $data['max_quantity'] : null;
        $overlaps = (clone $query)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where(fn ($query) => $query->whereNull('max_quantity')->orWhere('max_quantity', '>=', $min))
            ->when($max !== null, fn ($query) => $query->where('min_quantity', '<=', $max))
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages(['min_quantity' => 'La fascia si sovrappone a una fascia già configurata.']);
        }
    }
}
