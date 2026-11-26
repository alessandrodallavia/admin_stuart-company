<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrmProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    public function image(CrmProductCategory $productCategory): BinaryFileResponse
    {
        $path = $productCategory->image_path;
        abort_unless($this->isImagePath($path), 404);
        $disk = Storage::disk('category_images');
        $root = realpath($disk->path(''));
        $file = realpath($disk->path($path));
        abort_unless($root && $file && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file), 404);

        return response()->file($file, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-cache']);
    }

    private function isImagePath(?string $path): bool
    {
        return $path !== null && preg_match('/\A[a-zA-Z0-9_-]+\.(?:png|jpe?g|webp)\z/i', $path) === 1;
    }

    private function deleteUnusedImage(?string $path): void
    {
        if ($this->isImagePath($path) && ! CrmProductCategory::where('image_path', $path)->exists()) {
            Storage::disk('category_images')->delete($path);
        }
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
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=5000,max_height=5000'],
            'image_alt' => ['nullable', 'string', 'max:255'],
            'remove_image' => ['sometimes', 'boolean'],
        ]);
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $newPath = null;
        $oldPath = null;
        try {
            DB::transaction(function () use ($request, $category, $data, &$newPath, &$oldPath) {
                $categories = CrmProductCategory::orderBy('id')->lockForUpdate()->get()->keyBy('id');
                if ($category->exists) {
                    $current = $categories->get($category->id);
                    abort_unless($current, 404);
                    $category->setRawAttributes($current->getAttributes(), true);
                }
                $parentId = $data['parent_id'] ?? null;
                $seen = [];
                while ($parentId) {
                    if (($category->exists && (int) $parentId === $category->id) || isset($seen[$parentId])) {
                        throw ValidationException::withMessages(['parent_id' => 'Una categoria non può essere sottocategoria di se stessa o di una sua discendente.']);
                    }
                    $seen[$parentId] = true;
                    $parentId = $categories->get($parentId)?->parent_id;
                }
                $oldPath = $category->image_path;
                $category->fill(collect($data)->except(['image', 'remove_image'])->all());
                if ($request->hasFile('image')) {
                    $newPath = $request->file('image')->store('', 'category_images');
                    if (! $newPath) throw new RuntimeException('Impossibile salvare l’immagine della categoria.');
                    $category->image_path = $newPath;
                } elseif ($request->boolean('remove_image')) {
                    $category->image_path = null;
                    $category->image_alt = null;
                }
                $category->save();
            });
        } catch (Throwable $exception) {
            if ($newPath) Storage::disk('category_images')->delete($newPath);
            throw $exception;
        }
        if ($oldPath !== $category->image_path) $this->deleteUnusedImage($oldPath);

        return to_route('admin.crm-catalog.index', ['section' => 'product-categories'])->with('status', 'Categoria prodotto salvata.');
    }

    public function destroy(CrmProductCategory $productCategory): RedirectResponse
    {
        $path = null;
        DB::transaction(function () use ($productCategory, &$path) {
            $category = CrmProductCategory::lockForUpdate()->findOrFail($productCategory->id);
            if ($category->children()->exists() || $category->products()->exists()) {
                throw ValidationException::withMessages(['product_category' => 'La categoria contiene prodotti o sottocategorie: riassegnali prima di eliminarla, oppure disattivala.']);
            }
            $path = $category->image_path;
            $category->delete();
        });

        $this->deleteUnusedImage($path);

        return to_route('admin.crm-catalog.index', ['section' => 'product-categories'])->with('status', 'Categoria prodotto eliminata.');
    }
}
