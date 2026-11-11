<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrmBrand;
use App\Models\CrmSupplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CrmCatalogDirectoryController extends Controller
{
    public function storeBrand(Request $request): RedirectResponse
    {
        return $this->saveBrand($request, new CrmBrand);
    }

    public function updateBrand(Request $request, CrmBrand $brand): RedirectResponse
    {
        return $this->saveBrand($request, $brand);
    }

    private function saveBrand(Request $request, CrmBrand $brand): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('crm_brands', 'name')->ignore($brand)],
            'supplier_ids' => ['sometimes', 'array', 'max:100'],
            'supplier_ids.*' => ['required', 'integer', 'distinct', 'exists:crm_suppliers,id'],
        ]);
        DB::transaction(function () use ($brand, $data) {
            if ($brand->exists) {
                CrmBrand::whereKey($brand->id)->lockForUpdate()->firstOrFail();
                $used = DB::table('crm_product_supplier')->join('crm_products', 'crm_products.id', '=', 'crm_product_supplier.crm_product_id')
                    ->where('crm_products.crm_brand_id', $brand->id)->pluck('crm_supplier_id')->all();
                if (array_diff($used, $data['supplier_ids'] ?? [])) {
                    throw ValidationException::withMessages(['supplier_ids' => 'Non puoi scollegare un fornitore ancora associato ai prodotti di questo brand.']);
                }
            }
            $brand->fill(['name' => $data['name']])->save();
            $brand->suppliers()->sync($data['supplier_ids'] ?? []);
        });

        return back()->with('status', 'Brand salvato.');
    }

    public function destroyBrand(CrmBrand $brand): RedirectResponse
    {
        if ($brand->products()->exists()) {
            return back()->withErrors(['brand' => 'Il brand è associato a prodotti e non può essere eliminato.']);
        }
        $brand->delete();

        return back()->with('status', 'Brand eliminato.');
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        return $this->saveSupplier($request, new CrmSupplier);
    }

    public function updateSupplier(Request $request, CrmSupplier $supplier): RedirectResponse
    {
        return $this->saveSupplier($request, $supplier);
    }

    private function saveSupplier(Request $request, CrmSupplier $supplier): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('crm_suppliers', 'name')->ignore($supplier)],
            'website' => ['nullable', 'url:http,https', 'max:2048'],
        ]);
        $supplier->fill($data)->save();

        return back()->with('status', 'Fornitore salvato.');
    }

    public function destroySupplier(CrmSupplier $supplier): RedirectResponse
    {
        if ($supplier->products()->exists()) {
            return back()->withErrors(['supplier' => 'Il fornitore è associato a prodotti e non può essere eliminato.']);
        }
        $supplier->delete();

        return back()->with('status', 'Fornitore eliminato.');
    }
}
