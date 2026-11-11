<?php

namespace App\Services;

use App\Models\CrmBrand;
use App\Models\CrmProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class CrmProductCatalogService
{
    public function save(Request $request, ?CrmProduct $product = null): CrmProduct
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:100', Rule::unique('crm_products', 'code')->ignore($product)],
            'name' => ['required', 'string', 'max:255'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'crm_brand_id' => ['nullable', 'integer', 'exists:crm_brands,id'],
            'supplier_ids' => ['sometimes', 'array', 'max:100'],
            'supplier_ids.*' => ['required', 'integer', 'distinct', 'exists:crm_suppliers,id'],
            'is_visible_on_site' => ['sometimes', 'boolean'],
            'supplier_product_url' => ['nullable', 'url:http,https', 'max:2048'],
            'colors_text' => ['sometimes', 'nullable', 'string', 'max:30000'],
            'sizes_text' => ['sometimes', 'nullable', 'string', 'max:6000'],
            'colors' => ['sometimes', 'array', 'max:200'],
            'colors.*' => ['array:code,name,hex,sizes_restricted,sizes'],
            'colors.*.code' => ['nullable', 'string', 'max:100'],
            'colors.*.name' => ['required', 'string', 'max:100'],
            'colors.*.sizes_restricted' => ['sometimes', 'boolean'],
            'colors.*.sizes' => ['sometimes', 'array', 'max:100'],
            'colors.*.sizes.*' => ['required', 'string', 'max:50'],
            'colors.*.hex' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sizes' => ['sometimes', 'array', 'max:100'],
            'sizes.*' => ['required', 'string', 'max:50'],
            'variants_present' => ['sometimes', 'boolean'],
            'datasheet' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'remove_datasheet' => ['sometimes', 'boolean'],
        ]);
        $product ??= new CrmProduct(['is_active' => true]);
        $suppliers = array_key_exists('supplier_ids', $data) ? $data['supplier_ids'] : null;
        // An HTML form sends no values for an empty checkbox group.
        if ($request->boolean('metadata_present')) {
            $suppliers ??= [];
            $data['is_visible_on_site'] = $request->boolean('is_visible_on_site');
        }
        $brandId = array_key_exists('crm_brand_id', $data) ? $data['crm_brand_id'] : $product->crm_brand_id;
        $selectedSuppliers = $suppliers ?? ($product->exists ? $product->suppliers()->pluck('crm_suppliers.id')->all() : []);
        // Keep the legacy text format compatible with existing integrations;
        // the catalog UI now sends structured colors and individual sizes.
        $colors = array_key_exists('colors', $data) || $request->boolean('variants_present')
            ? $this->structuredColors($data['colors'] ?? [])
            : (array_key_exists('colors_text', $data) ? $this->colors($data['colors_text'] ?? '') : null);
        $sizes = array_key_exists('sizes', $data) || $request->boolean('variants_present')
            ? $this->structuredSizes($data['sizes'] ?? [])
            : (array_key_exists('sizes_text', $data) ? $this->sizes($data['sizes_text'] ?? '') : null);
        $oldFile = $product->datasheet_path;
        $newFile = null;

        try {
            DB::transaction(function () use ($request, &$product, &$newFile, $data, $brandId, $selectedSuppliers, $suppliers, $colors, $sizes) {
                $brand = $brandId ? CrmBrand::lockForUpdate()->findOrFail($brandId) : null;
                $allowed = $brand?->suppliers()->pluck('crm_suppliers.id')->all() ?? [];
                if (array_diff($selectedSuppliers, $allowed)) {
                    throw ValidationException::withMessages(['supplier_ids' => 'Seleziona un brand e fornitori associati a quel brand.']);
                }
                $product->fill(collect($data)->only(['code', 'name', 'unit_cost', 'crm_brand_id', 'is_visible_on_site', 'supplier_product_url'])->all());
                if ($request->boolean('remove_datasheet')) {
                    $product->datasheet_path = null;
                    $product->datasheet_filename = null;
                }
                if ($request->hasFile('datasheet')) {
                    $newFile = $request->file('datasheet')->store('crm-catalog/datasheets', 'local');
                    if (! $newFile) {
                        throw new RuntimeException('Impossibile salvare la scheda tecnica.');
                    }
                    $product->datasheet_path = $newFile;
                    $product->datasheet_filename = mb_substr($request->file('datasheet')->getClientOriginalName(), 0, 255);
                }
                $product->save();
                if ($suppliers !== null) {
                    $product->suppliers()->sync($suppliers);
                }
                if ($colors !== null) {
                    $ids = [];
                    foreach ($colors as $color) {
                        $attributes = collect($color)->except(['sizes', 'matrix_present', 'input_index'])->all();
                        $ids[] = $product->colors()->updateOrCreate(['code' => $color['code']], $attributes)->id;
                    }
                    $product->colors()->whereNotIn('id', $ids)->delete();
                }
                if ($sizes !== null) {
                    $ids = [];
                    foreach ($sizes as $size) {
                        $ids[] = $product->sizes()->updateOrCreate(['name' => $size['name']], $size)->id;
                    }
                    $product->sizes()->whereNotIn('id', $ids)->delete();
                }
                if ($colors !== null || $sizes !== null) {
                    $availableSizes = $product->sizes()->get();
                    $submitted = collect($colors ?? [])->keyBy(fn ($color) => mb_strtolower($color['code']));
                    foreach ($product->colors()->with('sizes')->get() as $color) {
                        $input = $submitted->get(mb_strtolower($color->code));
                        if ($input && ($input['matrix_present'] ?? false)) {
                            $names = array_map('trim', $input['sizes']);
                            $known = $availableSizes->keyBy(fn ($size) => mb_strtolower($size->name));
                            foreach ($names as $name) {
                                if (! $known->has(mb_strtolower($name))) {
                                    throw ValidationException::withMessages(['colors.'.$input['input_index'].'.sizes' => 'La taglia '.$name.' non è tra le taglie del prodotto.']);
                                }
                            }
                            $chosen = collect($names)->map(fn ($name) => $known->get(mb_strtolower($name))->id)->all();
                        } else {
                            // Metadata-only or legacy requests must preserve imported restrictions.
                            $chosen = $color->sizes->modelKeys();
                        }
                        $color->sizes()->sync($color->sizes_restricted ? $chosen : $availableSizes->modelKeys());
                    }
                }
            });
        } catch (Throwable $error) {
            if ($newFile) {
                Storage::disk('local')->delete($newFile);
            }
            throw $error;
        }
        if ($oldFile && $oldFile !== $product->datasheet_path) {
            Storage::disk('local')->delete($oldFile);
        }

        return $product;
    }

    private function structuredColors(array $colors): array
    {
        $rows = [];
        $seen = [];
        foreach ($colors as $index => $color) {
            $name = trim($color['name']);
            $code = trim($color['code'] ?? '');
            if ($code === '') {
                $code = $name;
            }
            $key = mb_strtolower($code);
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(['colors.'.$index.'.code' => 'Codice colore duplicato: '.$code]);
            }
            $seen[$key] = true;
            if ($name === '') {
                throw ValidationException::withMessages(['colors.'.$index.'.name' => 'Inserisci il nome del colore.']);
            }
            $row = ['code' => $code, 'name' => $name, 'hex' => ! empty($color['hex']) ? strtoupper($color['hex']) : null, 'sort_order' => count($rows), 'input_index' => $index];
            if (array_key_exists('sizes_restricted', $color)) {
                $row['sizes_restricted'] = (bool) $color['sizes_restricted'];
                $row['matrix_present'] = true;
                $row['sizes'] = array_map('trim', $color['sizes'] ?? []);
                $keys = array_map(fn ($size) => mb_strtolower($size), $row['sizes']);
                if (in_array('', $keys, true) || count(array_unique($keys)) !== count($keys)) {
                    throw ValidationException::withMessages(['colors.'.$index.'.sizes' => 'Seleziona taglie valide e senza duplicati per questo colore.']);
                }
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function structuredSizes(array $sizes): array
    {
        $rows = [];
        $seen = [];
        foreach ($sizes as $index => $size) {
            $name = trim($size);
            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(['sizes.'.$index => 'Taglia duplicata: '.$name]);
            }
            $seen[$key] = true;
            $rows[] = ['name' => $name, 'sort_order' => count($rows)];
        }

        return $rows;
    }

    private function colors(string $text): array
    {
        $rows = [];
        $seen = [];
        foreach (preg_split('/\R/u', $text) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $code = $parts[0];
            $name = $parts[1] ?? $code;
            $hex = ($parts[2] ?? '') !== '' ? strtoupper($parts[2]) : null;
            if (count($parts) > 3 || $code === '' || $name === '' || mb_strlen($code) > 100 || mb_strlen($name) > 100 || ($hex !== null && ! preg_match('/^#[0-9A-F]{6}$/', $hex))) {
                throw ValidationException::withMessages(['colors_text' => 'Inserisci un colore per riga: codice | nome | #RRGGBB. Il campione esadecimale è facoltativo.']);
            }
            $key = mb_strtolower($code);
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(['colors_text' => 'Codice colore duplicato: '.$code]);
            }
            $seen[$key] = true;
            $rows[] = ['code' => $code, 'name' => $name, 'hex' => $hex, 'sort_order' => count($rows)];
        }
        if (count($rows) > 200) {
            throw ValidationException::withMessages(['colors_text' => 'Puoi inserire al massimo 200 colori per prodotto.']);
        }

        return $rows;
    }

    private function sizes(string $text): array
    {
        $rows = [];
        $seen = [];
        foreach (preg_split('/[,;\r\n]+/u', $text) as $value) {
            $name = trim($value);
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            if (mb_strlen($name) > 50 || isset($seen[$key])) {
                throw ValidationException::withMessages(['sizes_text' => 'Taglia duplicata o troppo lunga: '.$name]);
            }
            $seen[$key] = true;
            $rows[] = ['name' => $name, 'sort_order' => count($rows)];
        }
        if (count($rows) > 100) {
            throw ValidationException::withMessages(['sizes_text' => 'Puoi inserire al massimo 100 taglie per prodotto.']);
        }

        return $rows;
    }
}
