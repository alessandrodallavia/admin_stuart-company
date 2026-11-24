<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\CrmProduct;
use App\Models\CrmProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmProductCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function login(array $permissions = ['*']): void
    {
        $this->actingAs(AdminUser::create([
            'name' => 'Catalogo', 'email' => fake()->unique()->safeEmail(), 'password' => 'password',
            'role' => 'custom', 'permissions' => $permissions, 'is_active' => true,
        ]), 'admin');
    }

    public function test_categories_start_empty_and_can_be_created_edited_reordered_and_deactivated(): void
    {
        $this->login();
        $this->assertDatabaseCount('crm_product_categories', 0);
        $this->get(route('admin.crm-catalog.index', ['section' => 'product-categories']))
            ->assertOk()->assertSee('Categorie prodotti')->assertSee('Nuova categoria prodotto')->assertSee('Categorie lead');
        $this->post(route('admin.crm-catalog.product-categories.store'), ['name' => 'Felpe personalizzate', 'is_active' => 1])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.crm-catalog.index', ['section' => 'product-categories']));
        $category = CrmProductCategory::sole();
        $this->assertSame('felpe-personalizzate', $category->slug);
        $this->patch(route('admin.crm-catalog.product-categories.update', $category), [
            'name' => 'Felpe', 'sort_order' => 12, 'description' => 'La collezione', 'is_active' => 0,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('crm_product_categories', ['id' => $category->id, 'name' => 'Felpe', 'slug' => 'felpe-personalizzate', 'sort_order' => 12, 'is_active' => false]);
        $this->get(route('admin.crm-catalog.index'))->assertOk()->assertSee('La collezione')->assertSee('Disattivata');
    }

    public function test_hierarchy_rejects_cycles_and_missing_parents(): void
    {
        $this->login();
        $parent = CrmProductCategory::create(['name' => 'Felpe', 'slug' => 'felpe']);
        $this->post(route('admin.crm-catalog.product-categories.store'), ['name' => 'Zip', 'parent_id' => $parent->id, 'is_active' => 1])->assertSessionHasNoErrors();
        $child = CrmProductCategory::where('slug', 'zip')->firstOrFail();
        $grandchild = CrmProductCategory::create(['name' => 'Mezza zip', 'slug' => 'mezza-zip', 'parent_id' => $child->id]);
        foreach ([$parent->id, $child->id, $grandchild->id, 999999] as $invalidParent) {
            $this->patch(route('admin.crm-catalog.product-categories.update', $parent), ['name' => 'Felpe', 'parent_id' => $invalidParent, 'is_active' => 1])->assertSessionHasErrors('parent_id');
        }
        $this->assertNull($parent->fresh()->parent_id);
        $this->patch(route('admin.crm-catalog.product-categories.update', $child), ['name' => 'Zip', 'parent_id' => null, 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertNull($child->fresh()->parent_id);
    }

    public function test_slugs_must_be_unique_and_url_safe(): void
    {
        $this->login();
        CrmProductCategory::create(['name' => 'Felpe', 'slug' => 'felpe']);
        foreach (['felpe', '../felpe', 'Felpe con zip'] as $slug) {
            $this->post(route('admin.crm-catalog.product-categories.store'), ['name' => 'Altra', 'slug' => $slug, 'is_active' => 1])->assertSessionHasErrors('slug');
        }
        $this->post(route('admin.crm-catalog.product-categories.store'), ['name' => '   ', 'is_active' => 1])->assertSessionHasErrors('name');
        $this->assertDatabaseCount('crm_product_categories', 1);
    }

    public function test_products_can_be_assigned_reassigned_and_cleared_without_changing_prices(): void
    {
        $this->login();
        $category = CrmProductCategory::create(['name' => 'Felpe', 'slug' => 'felpe']);
        $other = CrmProductCategory::create(['name' => 'Zip', 'slug' => 'zip']);
        $data = ['code' => 'ZIP01', 'name' => 'Felpa zip', 'unit_cost' => 12];
        $this->post(route('admin.crm-catalog.products.store'), [...$data, 'crm_product_category_id' => $category->id])->assertSessionHasNoErrors();
        $product = CrmProduct::sole();
        $tier = $product->priceTiers()->create(['min_quantity' => 10, 'unit_cost' => 12, 'unit_price' => 20]);
        $this->assertSame($category->id, $product->category->id);
        $this->patch(route('admin.crm-catalog.products.update', $product), [...$data, 'crm_product_category_id' => $other->id])->assertSessionHasNoErrors();
        $this->assertSame($other->id, $product->fresh()->category->id);
        // Legacy requests without the new field preserve the category.
        $this->patch(route('admin.crm-catalog.products.update', $product), $data)->assertSessionHasNoErrors();
        $this->assertSame($other->id, $product->fresh()->category->id);
        $this->get(route('admin.crm-catalog.index'))->assertOk()->assertSee('name="crm_product_category_id"', false);
        $this->patch(route('admin.crm-catalog.products.update', $product), [...$data, 'crm_product_category_id' => ''])->assertSessionHasNoErrors();
        $this->assertNull($product->fresh()->category);
        $this->assertSame('20.0000', $tier->fresh()->unit_price);
        $this->patch(route('admin.crm-catalog.products.update', $product), [...$data, 'crm_product_category_id' => 999999])->assertSessionHasErrors('crm_product_category_id');
    }

    public function test_used_categories_cannot_be_deleted_and_empty_categories_can(): void
    {
        $this->login();
        $parent = CrmProductCategory::create(['name' => 'Felpe', 'slug' => 'felpe']);
        $child = CrmProductCategory::create(['name' => 'Zip', 'slug' => 'zip', 'parent_id' => $parent->id]);
        $product = CrmProduct::create(['code' => 'F01', 'name' => 'Felpa', 'unit_cost' => 10, 'crm_product_category_id' => $child->id]);
        foreach ([$parent, $child] as $category) {
            $this->delete(route('admin.crm-catalog.product-categories.destroy', $category))->assertSessionHasErrors('product_category');
        }
        $product->update(['crm_product_category_id' => null]);
        $this->delete(route('admin.crm-catalog.product-categories.destroy', $child))->assertSessionHasNoErrors();
        $this->delete(route('admin.crm-catalog.product-categories.destroy', $parent))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('crm_product_categories', 0);
    }

    public function test_view_only_users_cannot_change_categories_and_guests_cannot_manage_them(): void
    {
        $category = CrmProductCategory::create(['name' => 'Felpe', 'slug' => 'felpe']);
        $this->post(route('admin.crm-catalog.product-categories.store'), ['name' => 'Zip', 'is_active' => 1])->assertRedirect(route('admin.login'));
        $this->login(['crm_catalog.view']);
        $this->get(route('admin.crm-catalog.index'))->assertOk()->assertSee('Categorie prodotti')->assertDontSee('data-catalog-open="product-category-new"', false);
        $this->post(route('admin.crm-catalog.product-categories.store'), ['name' => 'Zip', 'is_active' => 1])->assertForbidden();
        $this->patch(route('admin.crm-catalog.product-categories.update', $category), ['name' => 'Zip', 'is_active' => 1])->assertForbidden();
        $this->delete(route('admin.crm-catalog.product-categories.destroy', $category))->assertForbidden();
    }

    public function test_migration_preserves_existing_products_and_related_prices(): void
    {
        $migration = require database_path('migrations/2026_11_24_000001_create_crm_product_categories.php');
        $migration->down();
        $product = CrmProduct::create(['code' => 'F01', 'name' => 'Felpa', 'unit_cost' => 10]);
        $tier = $product->priceTiers()->create(['min_quantity' => 10, 'unit_cost' => 10, 'unit_price' => 20]);
        $migration->up();
        $this->assertSame('F01', $product->fresh()->code);
        $this->assertNull($product->fresh()->crm_product_category_id);
        $this->assertSame('20.0000', $tier->fresh()->unit_price);
    }
}
