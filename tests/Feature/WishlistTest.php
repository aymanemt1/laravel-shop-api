<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_wishlist(): void
    {
        $this->getJson('/api/wishlist')->assertUnauthorized();
        $this->postJson('/api/wishlist', ['product_id' => 1])->assertUnauthorized();
    }

    public function test_user_can_toggle_wishlist(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        // Add
        $this->actingAs($user)->postJson('/api/wishlist', [
            'product_id' => $product->id,
        ])->assertCreated()->assertJsonPath('wishlisted', true);

        $this->assertDatabaseHas('wishlists', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        // Toggle off
        $this->actingAs($user)->postJson('/api/wishlist', [
            'product_id' => $product->id,
        ])->assertOk()->assertJsonPath('wishlisted', false);

        $this->assertDatabaseMissing('wishlists', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_user_can_list_and_remove_wishlist_items(): void
    {
        $user = User::factory()->create();
        $products = Product::factory(3)->create();

        foreach ($products as $product) {
            $this->actingAs($user)->postJson('/api/wishlist', [
                'product_id' => $product->id,
            ]);
        }

        $this->actingAs($user)->getJson('/api/wishlist')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->actingAs($user)->deleteJson("/api/wishlist/{$products[0]->id}")
            ->assertNoContent();

        $this->actingAs($user)->getJson('/api/wishlist')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_wishlist_validates_product(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/wishlist', [
            'product_id' => 999999,
        ])->assertUnprocessable();
    }
}
