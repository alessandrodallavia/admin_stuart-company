<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrmProduct;
use App\Models\CrmProductColor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CrmProductMockupController extends Controller
{
    public function index(Request $request, CrmProduct $product): View
    {
        $product->load('colors');
        $selected = $request->query('color');
        $color = $selected !== null ? $product->colors->firstWhere('id', $selected) : $product->colors->first();
        abort_if($selected !== null && ! $color, 404);
        $library = collect(Storage::disk('live_mockup_assets')->allFiles())
            ->filter(fn ($path) => in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'webp'], true))
            ->sort()->map(fn ($path) => ['path' => $path, 'url' => route('admin.crm-catalog.mockup-assets.show', ['path' => $path])])->values();

        return view('admin.crm-catalog.mockups', compact('product', 'color', 'library'));
    }

    public function asset(Request $request): StreamedResponse
    {
        $data = $request->validate(['path' => ['required', 'string', 'max:1024']]);
        $image = $this->image($data['path']);
        abort_unless($image, 404);

        return Storage::disk('live_mockup_assets')->response($data['path'], null, [
            'Content-Type' => $image['mime'], 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function update(Request $request, CrmProduct $product, CrmProductColor $color): RedirectResponse
    {
        abort_unless($color->crm_product_id === $product->id, 404);
        $rules = [];
        foreach (['front', 'back'] as $side) {
            $rules[$side.'_action'] = ['required', Rule::in(['keep', 'library', 'upload', 'clear'])];
            $rules[$side.'_asset'] = ['nullable', 'string', 'max:1024'];
            $rules[$side.'_file'] = [Rule::requiredIf($request->input($side.'_action') === 'upload'), 'nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:8192', 'dimensions:max_width=5000,max_height=5000'];
        }
        $data = $request->validate($rules);
        $paths = [];
        foreach (['front', 'back'] as $side) {
            if ($data[$side.'_action'] === 'library') {
                $path = $data[$side.'_asset'] ?? '';
                if (! $this->image($path)) {
                    throw ValidationException::withMessages([$side.'_asset' => 'Seleziona un’immagine valida dalla libreria.']);
                }
                $paths[$side] = $path;
            }
        }
        $created = [];
        try {
            DB::transaction(function () use ($request, $color, $product, $data, $paths, &$created) {
                $locked = CrmProductColor::whereKey($color->id)->where('crm_product_id', $product->id)->lockForUpdate()->firstOrFail();
                foreach (['front', 'back'] as $side) {
                    $column = 'mockup_'.$side.'_path';
                    switch ($data[$side.'_action']) {
                        case 'clear': $locked->{$column} = null;
                            break;
                        case 'library': $locked->{$column} = $paths[$side];
                            break;
                        case 'upload':
                            $path = $request->file($side.'_file')->store('crm', 'live_mockup_assets');
                            if (! $path) {
                                throw new RuntimeException('Impossibile salvare il mockup.');
                            }
                            $created[] = $path;
                            $locked->{$column} = $path;
                            break;
                    }
                }
                if ($data['front_action'] !== 'keep' || $data['back_action'] !== 'keep') {
                    $locked->mockup_customized = true;
                }
                $locked->save();
            });
        } catch (Throwable $e) {
            Storage::disk('live_mockup_assets')->delete($created);
            throw $e;
        }

        // Library assets may be reused by other products: unlinking never deletes them.
        return redirect()->route('admin.crm-catalog.products.mockups.index', ['product' => $product, 'color' => $color->id])->with('status', 'Mockup del colore salvati.');
    }

    private function image(string $path): array|false
    {
        if (! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'webp'], true) || $path === '' || str_contains($path, "\0") || str_contains($path, '\\') || str_starts_with($path, '/') || in_array('..', explode('/', $path), true)) {
            return false;
        }
        $disk = Storage::disk('live_mockup_assets');
        $root = realpath($disk->path(''));
        $file = realpath($disk->path($path));
        if (! $root || ! $file || ! str_starts_with($file, $root.DIRECTORY_SEPARATOR) || ! is_file($file)) {
            return false;
        }
        $image = @getimagesize($file);

        return $image && in_array($image['mime'], ['image/png', 'image/jpeg', 'image/webp'], true) ? $image : false;
    }
}
