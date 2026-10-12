<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_intent_requires_stripe_config(): void
    {
        config(['services.stripe.secret' => null]);

        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);

        $this->actingAs($user)->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->actingAs($user)->postJson('/api/checkout/payment-intent')
            ->assertStatus(503)
            ->assertJsonPath('message', 'Stripe is not configured. Set STRIPE_SECRET in your .env.');
    }

    public function test_checkout_with_payment_intent_requires_stripe_config(): void
    {
        config(['services.stripe.secret' => null]);

        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);

        $this->actingAs($user)->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->actingAs($user)->postJson('/api/checkout', [
            'shipping_name' => 'Aymane',
            'shipping_phone' => '+212600000000',
            'shipping_address' => '123 Rue',
            'shipping_city' => 'Tanger',
            'payment_intent_id' => 'pi_fake_123',
        ])->assertStatus(503);

        // No order was created
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_without_payment_intent_still_works_as_cod(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100, 'stock' => 5]);

        $this->actingAs($user)->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->actingAs($user)->postJson('/api/checkout', [
            'shipping_name' => 'Aymane',
            'shipping_phone' => '+212600000000',
            'shipping_address' => '123 Rue',
            'shipping_city' => 'Tanger',
        ])->assertCreated()->assertJsonPath('data.status', 'pending');
    }
}
