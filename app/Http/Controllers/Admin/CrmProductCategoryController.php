<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrmProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CrmProductCategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new CrmProductCategory);
    }

    public function update(Request $request, CrmProductCategory $productCategory): RedirectResponse
    {
        return $this->save($request, $productCategory);
    }

    private function save(Request $request, CrmProductCategory $category): RedirectResponse
    {
        $request->merge([
            'name' => is_string($request->input('name')) ? trim($request->input('name')) : $request->input('name'),
        ]);
        if (! $request->filled('slug') && is_string($request->input('name'))) {
            // Keep an existing URL stable when only the name is changed.
            $request->merge(['slug' => $category->slug ?: Str::slug($request->input('name'))]);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('crm_product_categories', 'slug')->ignore($category)],
            'parent_id' => ['nullable', 'integer', 'exists:crm_product_categories,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'is_active' => ['required', 'boolean'],
        ]);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        DB::transaction(function () use ($category, $data) {
            $categories = CrmProductCategory::orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $parentId = $data['parent_id'] ?? null;
            $seen = [];
            while ($parentId) {
                if (($category->exists && (int) $parentId === $category->id) || isset($seen[$parentId])) {
                    throw ValidationException::withMessages(['parent_id' => 'Una categoria non può essere sottocategoria di se stessa o di una sua discendente.']);
                }
                $seen[$parentId] = true;
                $parentId = $categories->get($parentId)?->parent_id;
            }
            $category->fill($data)->save();
        });

        return to_route('admin.crm-catalog.index', ['section' => 'product-categories'])->with('status', 'Categoria prodotto salvata.');
    }

    public function destroy(CrmProductCategory $productCategory): RedirectResponse
    {
        DB::transaction(function () use ($productCategory) {
            $category = CrmProductCategory::lockForUpdate()->findOrFail($productCategory->id);
            if ($category->children()->exists() || $category->products()->exists()) {
                throw ValidationException::withMessages(['product_category' => 'La categoria contiene prodotti o sottocategorie: riassegnali prima di eliminarla, oppure disattivala.']);
            }
            $category->delete();
        });

        return to_route('admin.crm-catalog.index', ['section' => 'product-categories'])->with('status', 'Categoria prodotto eliminata.');
    }
}
