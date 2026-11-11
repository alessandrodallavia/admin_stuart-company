<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\CrmProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CrmProductMockupTest extends TestCase
{
    use RefreshDatabase;

    private function setupCatalog(): array
    {
        Storage::fake('live_mockup_assets');
        $owner = AdminUser::create(['name' => 'Owner', 'email' => fake()->unique()->safeEmail(), 'password' => 'password', 'role' => 'owner', 'is_active' => true]);
        $this->actingAs($owner, 'admin');
        $product = CrmProduct::create(['code' => 'TS1', 'name' => 'T-shirt', 'unit_cost' => 3, 'is_active' => true]);
        $color = $product->colors()->create(['code' => 'BK', 'name' => 'Black']);
        Storage::disk('live_mockup_assets')->put('basic/black-front.png', UploadedFile::fake()->image('front.png', 60, 80)->get());

        return [$product, $color];
    }

    public function test_page_and_library_preview_are_available_to_catalog_viewers(): void
    {
        [$product] = $this->setupCatalog();
        $this->get(route('admin.crm-catalog.products.mockups.index', $product))->assertOk()->assertSee('Mockup per colore')->assertSee('Black')->assertSee('Scegli dalla libreria')->assertSee('black-front.png');
        $this->get(route('admin.crm-catalog.mockup-assets.show', ['path' => 'basic/black-front.png']))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('admin.crm-catalog.mockup-assets.show', ['path' => '../outside.png']))->assertNotFound();
        $this->get(route('admin.crm-catalog.products.mockups.index', ['product' => $product, 'color' => 999]))->assertNotFound();
    }

    public function test_can_associate_existing_and_uploaded_images_without_changing_product_prices(): void
    {
        [$product, $color] = $this->setupCatalog();
        $product->priceTiers()->create(['min_quantity' => 15, 'max_quantity' => null, 'unit_cost' => 3, 'unit_price' => 6]);
        $url = route('admin.crm-catalog.products.mockups.update', [$product, $color]);
        $this->patch($url, ['front_action' => 'library', 'front_asset' => 'basic/black-front.png', 'back_action' => 'upload', 'back_file' => UploadedFile::fake()->image('back.png', 60, 80)])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.crm-catalog.products.mockups.index', ['product' => $product, 'color' => $color->id]));
        $color->refresh();
        $this->assertSame('basic/black-front.png', $color->mockup_front_path);
        $this->assertStringStartsWith('crm/', $color->mockup_back_path);
        $this->assertTrue((bool) $color->mockup_customized);
        Storage::disk('live_mockup_assets')->assertExists($color->mockup_back_path);
        $this->assertSame('3.0000', $product->fresh()->unit_cost);
        $this->assertSame('6.0000', $product->priceTiers->first()->unit_price);
        $back = $color->mockup_back_path;
        $this->patch($url, ['front_action' => 'clear', 'back_action' => 'keep'])->assertSessionHasNoErrors();
        $this->assertNull($color->fresh()->mockup_front_path);
        $this->assertSame($back, $color->fresh()->mockup_back_path);
        Storage::disk('live_mockup_assets')->assertExists('basic/black-front.png');
        $this->patch($url, ['front_action' => 'keep', 'back_action' => 'clear'])->assertSessionHasNoErrors();
        Storage::disk('live_mockup_assets')->assertExists($back);
    }

    public function test_rejects_missing_or_nonimage_library_paths_and_invalid_uploads_atomically(): void
    {
        [$product, $color] = $this->setupCatalog();
        $url = route('admin.crm-catalog.products.mockups.update', [$product, $color]);
        Storage::disk('live_mockup_assets')->put('script.png', '<?php echo "bad";');
        Storage::disk('live_mockup_assets')->put('image.php', UploadedFile::fake()->image('photo.png', 20, 20)->get());
        foreach (['image.php', '../outside.png', 'https://example.com/front.png', 'missing.png', 'script.png'] as $path) {
            $this->patch($url, ['front_action' => 'library', 'front_asset' => $path, 'back_action' => 'keep'])->assertSessionHasErrors('front_asset');
        }
        $this->patch($url, ['front_action' => 'library', 'front_asset' => 'basic/black-front.png', 'back_action' => 'upload', 'back_file' => UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml')])->assertSessionHasErrors('back_file');
        $this->assertNull($color->fresh()->mockup_front_path);
        $this->assertFalse((bool) $color->fresh()->mockup_customized);
    }

    public function test_cannot_edit_another_products_color_or_save_without_permission(): void
    {
        [$product, $color] = $this->setupCatalog();
        $other = CrmProduct::create(['code' => 'TS2', 'name' => 'Other', 'unit_cost' => 1, 'is_active' => true]);
        $this->patch(route('admin.crm-catalog.products.mockups.update', [$other, $color]), ['front_action' => 'clear', 'back_action' => 'clear'])->assertNotFound();
        config(['admin_permissions.role_permissions.operator' => []]);
        $viewer = AdminUser::create(['name' => 'Viewer', 'email' => fake()->unique()->safeEmail(), 'password' => 'password', 'role' => 'operator', 'permissions' => ['crm_catalog.view'], 'is_active' => true]);
        $this->actingAs($viewer, 'admin')->get(route('admin.crm-catalog.products.mockups.index', $product))->assertOk()->assertDontSee('Salva mockup del colore');
        $this->patch(route('admin.crm-catalog.products.mockups.update', [$product, $color]), ['front_action' => 'clear', 'back_action' => 'clear'])->assertForbidden();
    }
}
