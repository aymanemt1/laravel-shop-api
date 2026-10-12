<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_manage_cart_and_checkout(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100, 'stock' => 10]);

        // Add to cart
        $this->actingAs($user)->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertCreated();

        // View cart
        $this->actingAs($user)->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('subtotal', '200.00');

        // Checkout
        $checkout = $this->actingAs($user)->postJson('/api/checkout', [
            'shipping_name' => 'Aymane Moutoute',
            'shipping_phone' => '+212600000000',
            'shipping_address' => '123 Rue Example',
            'shipping_city' => 'Tanger',
        ]);

        $checkout->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.subtotal', '200.00')
            ->assertJsonCount(1, 'data.items');

        // Stock decreased and cart cleared
        $this->assertEquals(8, $product->fresh()->stock);
        $this->assertDatabaseCount('cart_items', 0);

        // Order appears in history
        $this->actingAs($user)->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_checkout_fails_with_empty_cart(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/checkout', [
            'shipping_name' => 'Aymane',
            'shipping_phone' => '+212600000000',
            'shipping_address' => '123 Rue',
            'shipping_city' => 'Tanger',
        ])->assertUnprocessable();
    }

    public function test_checkout_fails_when_stock_insufficient(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 50, 'stock' => 1]);

        $this->actingAs($user)->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 5,
        ])->assertUnprocessable();
    }

    public function test_admin_can_update_order_status(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100, 'stock' => 10]);

        $this->actingAs($user)->postJson('/api/cart', [
            'product_id' => $product->id, 'quantity' => 1,
        ]);
        $orderId = $this->actingAs($user)->postJson('/api/checkout', [
            'shipping_name' => 'Aymane',
            'shipping_phone' => '+212600000000',
            'shipping_address' => '123 Rue',
            'shipping_city' => 'Tanger',
        ])->json('data.id');

        $this->actingAs($admin)->patchJson("/api/orders/{$orderId}/status", [
            'status' => 'shipped',
        ])->assertOk()->assertJsonPath('data.status', 'shipped');

        $this->actingAs($user)->patchJson("/api/orders/{$orderId}/status", [
            'status' => 'shipped',
        ])->assertForbidden();
    }

    public function test_user_cannot_view_someone_elses_order(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $product = Product::factory()->create(['price' => 100, 'stock' => 10]);

        $this->actingAs($userA)->postJson('/api/cart', [
            'product_id' => $product->id, 'quantity' => 1,
        ]);
        $orderId = $this->actingAs($userA)->postJson('/api/checkout', [
            'shipping_name' => 'A',
            'shipping_phone' => '+212600000000',
            'shipping_address' => '123 Rue',
            'shipping_city' => 'Tanger',
        ])->json('data.id');

        $this->actingAs($userB)->getJson("/api/orders/{$orderId}")
            ->assertForbidden();
    }
}
