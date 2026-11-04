<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\CrmBrand;
use App\Models\CrmPrintType;
use App\Models\CrmProduct;
use App\Models\CrmSupplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CrmProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_can_have_multiple_suppliers_and_products_store_structured_details(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin(), 'admin');
        foreach (['Distributore A', 'Distributore B'] as $name) {
            $this->post(route('admin.crm-catalog.suppliers.store'), ['name' => $name, 'website' => 'https://example.com'])->assertSessionHasNoErrors();
        }
        $suppliers = CrmSupplier::pluck('id')->all();
        $this->post(route('admin.crm-catalog.brands.store'), ['name' => 'Brand test', 'supplier_ids' => $suppliers])->assertSessionHasNoErrors();
        $brand = CrmBrand::firstOrFail();
        $this->assertCount(2, $brand->suppliers);

        $this->post(route('admin.crm-catalog.products.store'), [
            ...$this->productData(), 'crm_brand_id' => $brand->id, 'supplier_ids' => $suppliers,
            'metadata_present' => 1, 'is_visible_on_site' => 1,
            'colors_text' => "BK | Nero | #000000\nWH | Bianco\nBlu navy",
            'sizes_text' => "XS, S, M\nL; XL; 3/4 anni", 'supplier_product_url' => 'https://example.com/product',
            'datasheet' => UploadedFile::fake()->create('scheda.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $product = CrmProduct::with(['colors', 'sizes', 'suppliers'])->firstOrFail();
        $this->assertSame($brand->id, $product->crm_brand_id);
        $this->assertTrue($product->is_visible_on_site);
        $this->assertCount(2, $product->suppliers);
        $this->assertSame(['BK', 'WH', 'Blu navy'], $product->colors->pluck('code')->all());
        $this->assertSame('#000000', $product->colors->first()->hex);
        $this->assertSame(['XS', 'S', 'M', 'L', 'XL', '3/4 anni'], $product->sizes->pluck('name')->all());
        Storage::disk('local')->assertExists($product->datasheet_path);
        $this->get(route('admin.crm-catalog.products.datasheet', $product))->assertOk()->assertDownload('scheda.pdf');
        $this->get(route('admin.crm-catalog.index'))->assertOk()->assertSee('Brand test')->assertSee('Distributore A')->assertSee('Blu navy')->assertSee('3/4 anni');
        $this->get(route('admin.crm-catalog.index', ['q' => 'Distributore B']))->assertOk()->assertSee('TS01');
        $this->get(route('admin.crm-catalog.index', ['q' => 'Brand test']))->assertOk()->assertSee('TS01');
    }

    public function test_invalid_supplier_color_size_and_url_are_rejected_without_partial_products(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $brand = CrmBrand::create(['name' => 'Brand']);
        $supplier = CrmSupplier::create(['name' => 'Non associato']);
        $this->post(route('admin.crm-catalog.products.store'), [
            ...$this->productData(), 'crm_brand_id' => $brand->id, 'supplier_ids' => [$supplier->id],
        ])->assertSessionHasErrors('supplier_ids');
        $this->post(route('admin.crm-catalog.products.store'), [
            ...$this->productData(), 'supplier_ids' => [$supplier->id],
        ])->assertSessionHasErrors('supplier_ids');
        foreach (["BK | Nero\nbk | Altro", 'BK | Nero | red'] as $colors) {
            $this->post(route('admin.crm-catalog.products.store'), [...$this->productData(), 'colors_text' => $colors])->assertSessionHasErrors('colors_text');
        }
        $this->post(route('admin.crm-catalog.products.store'), [...$this->productData(), 'sizes_text' => 'S,s'])->assertSessionHasErrors('sizes_text');
        $this->post(route('admin.crm-catalog.products.store'), [...$this->productData(), 'supplier_product_url' => 'javascript:alert(1)'])->assertSessionHasErrors('supplier_product_url');
        $this->assertDatabaseCount('crm_products', 0);
    }

    public function test_metadata_updates_preserve_prices_and_replace_or_remove_the_private_datasheet(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin(), 'admin');
        $brand = CrmBrand::create(['name' => 'Brand']);
        $supplier = CrmSupplier::create(['name' => 'Fornitore']);
        $brand->suppliers()->attach($supplier);
        $this->post(route('admin.crm-catalog.products.store'), [
            ...$this->productData(), 'crm_brand_id' => $brand->id, 'supplier_ids' => [$supplier->id],
            'colors_text' => "BK | Nero\nWH | Bianco", 'sizes_text' => 'S,M,L', 'is_visible_on_site' => 1,
            'datasheet' => UploadedFile::fake()->create('prima.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $product = CrmProduct::firstOrFail();
        $tier = $product->priceTiers()->create(['min_quantity' => 1, 'unit_cost' => 4, 'unit_price' => 9]);
        $oldFile = $product->datasheet_path;
        $colorId = $product->colors()->where('code', 'BK')->firstOrFail()->id;

        // Legacy/basic updates must not wipe the new metadata.
        $this->patch(route('admin.crm-catalog.products.update', $product), $this->productData())->assertSessionHasNoErrors();
        $this->assertCount(2, $product->fresh()->colors);
        $this->assertCount(1, $product->fresh()->suppliers);
        $this->assertTrue($product->fresh()->is_visible_on_site);
        $this->assertSame($oldFile, $product->fresh()->datasheet_path);

        $this->post(route('admin.crm-catalog.products.update', $product), [
            '_method' => 'PATCH', ...$this->productData(), 'metadata_present' => 1,
            'colors_text' => 'BK | Nero aggiornato | #112233', 'sizes_text' => 'M,XL',
            'datasheet' => UploadedFile::fake()->create('nuova.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $product->refresh();
        $this->assertFalse($product->is_visible_on_site);
        $this->assertCount(0, $product->suppliers);
        $this->assertSame([$colorId], $product->colors->modelKeys());
        $this->assertSame(['M', 'XL'], $product->sizes->pluck('name')->all());
        $this->assertSame('9.0000', $tier->fresh()->unit_price);
        Storage::disk('local')->assertMissing($oldFile);
        Storage::disk('local')->assertExists($product->datasheet_path);

        $newFile = $product->datasheet_path;
        $this->patch(route('admin.crm-catalog.products.update', $product), [...$this->productData(), 'remove_datasheet' => 1])->assertSessionHasNoErrors();
        $this->assertNull($product->fresh()->datasheet_path);
        Storage::disk('local')->assertMissing($newFile);
        $this->get(route('admin.crm-catalog.products.datasheet', $product))->assertNotFound();
    }

    public function test_brand_supplier_links_used_by_products_cannot_be_removed(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $brand = CrmBrand::create(['name' => 'Brand']);
        $supplier = CrmSupplier::create(['name' => 'Fornitore']);
        $brand->suppliers()->attach($supplier);
        $product = CrmProduct::create([...$this->productData(), 'crm_brand_id' => $brand->id]);
        $product->suppliers()->attach($supplier);
        $this->patch(route('admin.crm-catalog.brands.update', $brand), ['name' => 'Nuovo brand'])->assertSessionHasErrors('supplier_ids');
        $this->assertSame('Brand', $brand->fresh()->name);
        $this->delete(route('admin.crm-catalog.brands.destroy', $brand))->assertSessionHasErrors('brand');
        $this->delete(route('admin.crm-catalog.suppliers.destroy', $supplier))->assertSessionHasErrors('supplier');
        $this->patch(route('admin.crm-catalog.brands.update', $brand), ['name' => 'Nuovo brand', 'supplier_ids' => [$supplier->id]])->assertSessionHasNoErrors();
        $this->patch(route('admin.crm-catalog.suppliers.update', $supplier), ['name' => 'Nuovo fornitore', 'website' => 'https://example.com'])->assertSessionHasNoErrors();
        $this->delete(route('admin.crm-catalog.products.destroy', $product))->assertSessionHasNoErrors();
        $this->delete(route('admin.crm-catalog.brands.destroy', $brand))->assertSessionHasNoErrors();
        $this->delete(route('admin.crm-catalog.suppliers.destroy', $supplier))->assertSessionHasNoErrors();
    }

    public function test_read_only_users_can_download_datasheets_but_cannot_manage_directories(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('crm-catalog/datasheets/test.pdf', '%PDF-1.4');
        $product = CrmProduct::create([...$this->productData(), 'datasheet_path' => 'crm-catalog/datasheets/test.pdf', 'datasheet_filename' => 'test.pdf']);
        $this->actingAs($this->admin('operator', ['crm_catalog.view']), 'admin');
        $this->get(route('admin.crm-catalog.index'))->assertOk()->assertDontSee('Aggiungi brand')->assertDontSee('Aggiungi fornitore');
        $this->get(route('admin.crm-catalog.products.datasheet', $product))->assertOk();
        $this->post(route('admin.crm-catalog.brands.store'), ['name' => 'Vietato'])->assertForbidden();
        $this->post(route('admin.crm-catalog.suppliers.store'), ['name' => 'Vietato'])->assertForbidden();
        $this->patch(route('admin.crm-catalog.products.update', $product), $this->productData())->assertForbidden();
        $this->actingAs($this->admin('operator', []), 'admin');
        $this->get(route('admin.crm-catalog.products.datasheet', $product))->assertForbidden();
    }

    public function test_deleting_a_product_removes_its_datasheet_and_variants(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin(), 'admin');
        $this->post(route('admin.crm-catalog.products.store'), [
            ...$this->productData(), 'colors_text' => 'BK | Nero', 'sizes_text' => 'S,M',
            'datasheet' => UploadedFile::fake()->create('scheda.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $product = CrmProduct::firstOrFail();
        $path = $product->datasheet_path;
        $this->delete(route('admin.crm-catalog.products.destroy', $product))->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseCount('crm_product_colors', 0);
        $this->assertDatabaseCount('crm_product_sizes', 0);
    }

    public function test_migration_keeps_existing_products_and_price_tiers(): void
    {
        $migration = require database_path('migrations/2026_11_04_000001_extend_crm_product_catalog.php');
        $migration->down();
        $product = CrmProduct::create($this->productData());
        $tier = $product->priceTiers()->create(['min_quantity' => 10, 'unit_cost' => 4, 'unit_price' => 9]);
        $migration->up();
        $this->assertSame('TS01', $product->fresh()->code);
        $this->assertFalse($product->fresh()->is_visible_on_site);
        $this->assertNull($product->fresh()->crm_brand_id);
        $this->assertSame('9.0000', $tier->fresh()->unit_price);
    }

    public function test_catalog_renders_existing_product_and_print_tiers_and_restores_failed_dialogs(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $product = CrmProduct::create($this->productData());
        $product->priceTiers()->create(['min_quantity' => 10, 'max_quantity' => 19.99, 'unit_cost' => 4, 'unit_price' => 9]);
        $print = CrmPrintType::create(['code' => 'CUORE', 'name' => 'Stampa cuore']);
        $print->priceTiers()->create(['min_quantity' => 10, 'max_quantity' => 19.99, 'unit_cost' => 0.1, 'unit_price' => 0.82]);
        $this->get(route('admin.crm-catalog.index'))->assertOk()->assertSee('Anagrafica prodotto')->assertSee('9,0000')->assertSee('0,8200');

        $response = $this->followingRedirects()->from(route('admin.crm-catalog.index'))->post(route('admin.crm-catalog.products.store'), [
            'code' => '', 'name' => 'Nome da conservare', 'unit_cost' => 5,
            'catalog_dialog' => 'product-new', 'catalog_section' => 'products', 'catalog_product_key' => 'new',
            'variants_present' => 1, 'colors' => [['code' => 'BK', 'name' => 'Nero', 'hex' => '#000000']], 'sizes' => ['S', 'M'],
        ]);
        $response->assertOk()
            ->assertSee('data-restore-dialog="product-new"', false)
            ->assertSee('value="Nome da conservare"', false)
            ->assertSee('"code":"BK"', false)
            ->assertSee('Controlla i dati inseriti');
    }

    public function test_structured_variants_save_replace_and_clear_without_changing_the_price_list(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->post(route('admin.crm-catalog.products.store'), [
            ...$this->productData(), 'variants_present' => 1,
            'colors' => [['code' => 'BK', 'name' => 'Nero', 'hex' => '#aabbcc'], ['code' => '', 'name' => 'Bianco', 'hex' => null]],
            'sizes' => ['XS', 'S', '3/4 anni'],
        ])->assertSessionHasNoErrors();
        $product = CrmProduct::firstOrFail();
        $this->assertSame(['BK', 'Bianco'], $product->colors->pluck('code')->all());
        $this->assertSame(['#AABBCC', null], $product->colors->pluck('hex')->all());
        $this->assertSame(['XS', 'S', '3/4 anni'], $product->sizes->pluck('name')->all());
        $colorId = $product->colors->first()->id;
        $tier = $product->priceTiers()->create(['min_quantity' => 10, 'unit_cost' => 4, 'unit_price' => 9]);
        $this->get(route('admin.crm-catalog.index'))->assertOk()->assertDontSee('name="colors_text"', false)->assertDontSee('name="sizes_text"', false)->assertSee('Aggiungi colore')->assertSee('Aggiungi taglia');

        $this->patch(route('admin.crm-catalog.products.update', $product), [
            ...$this->productData(), 'variants_present' => 1,
            'colors' => [['code' => 'BK', 'name' => 'Nero aggiornato', 'hex' => '#112233']], 'sizes' => ['M'],
        ])->assertSessionHasNoErrors();
        $this->assertSame([$colorId], $product->fresh()->colors->modelKeys());
        $this->assertSame('#112233', $product->fresh()->colors->first()->hex);
        $this->assertSame(['M'], $product->fresh()->sizes->pluck('name')->all());
        $this->assertSame('9.0000', $tier->fresh()->unit_price);

        $this->patch(route('admin.crm-catalog.products.update', $product), [...$this->productData(), 'variants_present' => 1])->assertSessionHasNoErrors();
        $this->assertCount(0, $product->fresh()->colors);
        $this->assertCount(0, $product->fresh()->sizes);
        $this->assertSame('9.0000', $tier->fresh()->unit_price);
    }

    public function test_closed_catalog_forms_do_not_add_editable_fields_to_the_live_document(): void
    {
        $this->actingAs($this->admin(), 'admin');
        foreach (range(1, 12) as $index) {
            $product = CrmProduct::create(['code' => 'FOCUS-'.$index, 'name' => 'Prodotto '.$index, 'unit_cost' => 4]);
            foreach ([10, 20, 50, 100, 250, 500, 1000, 2000] as $min) {
                $product->priceTiers()->create(['min_quantity' => $min, 'unit_cost' => 4, 'unit_price' => 9]);
            }
        }
        $response = $this->get(route('admin.crm-catalog.index'))->assertOk();
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($response->getContent());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new \DOMXPath($document);
        $liveFields = $xpath->query('//*[@data-crm-catalog]//*[self::input or self::select or self::textarea][not(@type="hidden") and not(ancestor::template)]');
        $this->assertSame(4, $liveFields->length);
        $this->assertSame(0, $xpath->query('//dialog[not(ancestor::template)]')->length);
        $this->assertNotNull($xpath->query('//template[@data-catalog-dialog-template="product-1"]')->item(0));
        $this->assertNotNull($xpath->query('//template[@data-catalog-dialog-template="product-tier-1"]')->item(0));
    }

    public function test_structured_variants_reject_invalid_swatches_and_case_insensitive_duplicates(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->post(route('admin.crm-catalog.products.store'), [
            ...$this->productData(), 'colors' => [['code' => 'BK', 'name' => 'Nero', 'hex' => 'red']],
        ])->assertSessionHasErrors('colors.0.hex');
        $this->post(route('admin.crm-catalog.products.store'), [
            ...$this->productData(), 'colors' => [['code' => 'BK', 'name' => 'Nero'], ['code' => 'bk', 'name' => 'Altro nero']],
        ])->assertSessionHasErrors('colors.1.code');
        $this->post(route('admin.crm-catalog.products.store'), [
            ...$this->productData(), 'colors' => [['name' => 'Nero'], ['name' => 'nero']],
        ])->assertSessionHasErrors('colors.1.code');
        $this->post(route('admin.crm-catalog.products.store'), [...$this->productData(), 'sizes' => ['XL', 'xl']])->assertSessionHasErrors('sizes.1');
        $this->post(route('admin.crm-catalog.products.store'), [...$this->productData(), 'colors' => [['code' => 'BK']]])->assertSessionHasErrors('colors.0.name');
        $this->assertDatabaseCount('crm_products', 0);
    }

    private function productData(): array
    {
        return ['code' => 'TS01', 'name' => 'T-shirt test', 'unit_cost' => 4];
    }

    private function admin(string $role = 'owner', array $permissions = []): AdminUser
    {
        return AdminUser::create(['name' => 'Catalogo', 'email' => fake()->unique()->safeEmail(), 'password' => 'password', 'role' => $role, 'permissions' => $permissions, 'is_active' => true]);
    }
}
