<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\CrmProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CrmProductCategoryImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('category_images');
        $this->login(['*']);
    }

    private function login(array $permissions): void
    {
        $this->actingAs(AdminUser::create(['name' => 'Catalogo', 'email' => fake()->unique()->safeEmail(), 'password' => 'password', 'role' => 'custom', 'permissions' => $permissions, 'is_active' => true]), 'admin');
    }

    private function data(array $extra = []): array
    {
        return ['name' => 'Felpe', 'slug' => 'felpe', 'is_active' => 1, ...$extra];
    }

    private function createWithImage(): CrmProductCategory
    {
        $this->post(route('admin.crm-catalog.product-categories.store'), $this->data([
            'image' => UploadedFile::fake()->image('felpe.png', 600, 600), 'image_alt' => 'Felpe con cappuccio',
        ]))->assertSessionHasNoErrors();

        return CrmProductCategory::sole();
    }

    public function test_admin_can_upload_and_preview_a_category_image(): void
    {
        $category = $this->createWithImage();
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+\.png$/', $category->image_path);
        $this->assertSame('Felpe con cappuccio', $category->image_alt);
        Storage::disk('category_images')->assertExists($category->image_path);
        $this->get(route('admin.crm-catalog.product-categories.image', $category))->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('admin.crm-catalog.index'))->assertOk()->assertSee('multipart/form-data')->assertSee('Immagine categoria')->assertSee('Rimuovi immagine attuale')->assertSee('Felpe con cappuccio');
    }

    public function test_metadata_edits_keep_the_image_and_replacing_or_removing_it_cleans_up_old_files(): void
    {
        $category = $this->createWithImage();
        $url = route('admin.crm-catalog.product-categories.update', $category);
        $original = $category->image_path;
        $this->patch($url, $this->data(['description' => 'Nuova descrizione']))->assertSessionHasNoErrors();
        $this->assertSame($original, $category->fresh()->image_path);
        Storage::disk('category_images')->assertExists($original);
        $this->patch($url, $this->data(['image' => UploadedFile::fake()->image('nuova.jpg', 700, 700)]))->assertSessionHasNoErrors();
        $replacement = $category->fresh()->image_path;
        $this->assertNotSame($original, $replacement);
        Storage::disk('category_images')->assertExists($replacement);
        Storage::disk('category_images')->assertMissing($original);
        $this->patch($url, $this->data(['remove_image' => 1]))->assertSessionHasNoErrors();
        $this->assertNull($category->fresh()->image_path);
        $this->assertNull($category->fresh()->image_alt);
        Storage::disk('category_images')->assertMissing($replacement);
        $this->get(route('admin.crm-catalog.product-categories.image', $category))->assertNotFound();
    }

    public function test_invalid_images_or_invalid_category_changes_do_not_replace_the_current_image(): void
    {
        $category = $this->createWithImage();
        $url = route('admin.crm-catalog.product-categories.update', $category);
        $original = $category->image_path;
        foreach ([UploadedFile::fake()->createWithContent('bad.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'), UploadedFile::fake()->image('wide.png', 5001, 1), UploadedFile::fake()->image('large.png')->size(5121)] as $image) {
            $this->patch($url, $this->data(['image' => $image]))->assertSessionHasErrors('image');
        }
        $this->patch($url, $this->data(['parent_id' => $category->id, 'image' => UploadedFile::fake()->image('valid.png')]))->assertSessionHasErrors('parent_id');
        $this->assertSame($original, $category->fresh()->image_path);
        $this->assertSame([$original], Storage::disk('category_images')->allFiles());
    }

    public function test_deletion_keeps_images_when_category_is_in_use_and_removes_them_after_success(): void
    {
        $category = $this->createWithImage();
        $path = $category->image_path;
        $child = CrmProductCategory::create(['name' => 'Zip', 'slug' => 'zip', 'parent_id' => $category->id]);
        $this->delete(route('admin.crm-catalog.product-categories.destroy', $category))->assertSessionHasErrors('product_category');
        Storage::disk('category_images')->assertExists($path);
        $child->delete();
        $this->delete(route('admin.crm-catalog.product-categories.destroy', $category))->assertSessionHasNoErrors();
        Storage::disk('category_images')->assertMissing($path);
    }

    public function test_catalog_viewers_can_preview_images_but_cannot_upload_or_remove_them(): void
    {
        $category = $this->createWithImage();
        $this->login(['crm_catalog.view']);
        $this->get(route('admin.crm-catalog.product-categories.image', $category))->assertOk();
        $this->patch(route('admin.crm-catalog.product-categories.update', $category), $this->data(['remove_image' => 1]))->assertForbidden();
        $this->post(route('admin.crm-catalog.product-categories.store'), $this->data(['image' => UploadedFile::fake()->image('no.png')]))->assertForbidden();
        Storage::disk('category_images')->assertExists($category->image_path);
    }
}
