<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_list_and_search_products(): void
    {
        $category = Category::factory()->create(['slug' => 'electronics']);
        Product::factory(3)->create(['category_id' => $category->id]);
        Product::factory()->create(['is_active' => false]);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data', 'links', 'meta']);

        $this->getJson('/api/products?category=electronics')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->getJson('/api/products?category=unknown-slug')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_guest_can_view_single_product_by_slug(): void
    {
        $product = Product::factory()->create(['slug' => 'awesome-phone']);

        $this->getJson('/api/products/awesome-phone')
            ->assertOk()
            ->assertJsonPath('data.name', $product->name)
            ->assertJsonPath('data.category.id', $product->category_id);
    }

    public function test_inactive_product_returns_404(): void
    {
        Product::factory()->create(['slug' => 'old-phone', 'is_active' => false]);

        $this->getJson('/api/products/old-phone')->assertNotFound();
    }

    public function test_admin_can_create_update_and_delete_product(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $category = Category::factory()->create();

        $create = $this->actingAs($admin)->postJson('/api/products', [
            'category_id' => $category->id,
            'name' => 'Test Phone',
            'price' => 999.99,
            'stock' => 10,
        ]);

        $create->assertCreated()->assertJsonPath('data.slug', 'test-phone');
        $productId = $create->json('data.id');

        $update = $this->actingAs($admin)->putJson("/api/products/{$productId}", [
            'price' => 899.99,
        ]);
        $update->assertOk()->assertJsonPath('data.price', '899.99');

        $this->actingAs($admin)->deleteJson("/api/products/{$productId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('products', ['id' => $productId]);
    }

    public function test_regular_user_cannot_manage_products(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $product = Product::factory()->create();

        $this->actingAs($user)->postJson('/api/products', [
            'name' => 'Hack', 'price' => 1,
        ])->assertForbidden();

        $this->actingAs($user)->deleteJson("/api/products/{$product->id}")
            ->assertForbidden();
    }
}
