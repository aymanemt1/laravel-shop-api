<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
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

    public function test_payment_intent_charges_merchandise_plus_shipping(): void
    {
        config(['services.stripe.secret' => 'sk_test_fake']);

        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100, 'stock' => 5]);

        $this->actingAs($user)->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertCreated();

        $stripe = $this->fakeStripe();

        $this->actingAs($user)->postJson('/api/checkout/payment-intent')
            ->assertOk()
            ->assertJsonPath('subtotal', '200.00')
            ->assertJsonPath('shipping', '30.00')
            ->assertJsonPath('amount', '230.00')
            ->assertJsonPath('payment_intent_id', 'pi_created');

        $this->assertSame(23000, $stripe->createdAmount);
    }

    public function test_payment_intent_charges_no_shipping_at_the_free_shipping_threshold(): void
    {
        config(['services.stripe.secret' => 'sk_test_fake']);

        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 500, 'stock' => 5]);

        $this->actingAs($user)->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated();

        $stripe = $this->fakeStripe();

        $this->actingAs($user)->postJson('/api/checkout/payment-intent')
            ->assertOk()
            ->assertJsonPath('subtotal', '500.00')
            ->assertJsonPath('shipping', '0.00')
            ->assertJsonPath('amount', '500.00');

        $this->assertSame(50000, $stripe->createdAmount);
    }

    public function test_checkout_returns_422_when_payment_amount_does_not_match_order_total(): void
    {
        config(['services.stripe.secret' => 'sk_test_fake']);

        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100, 'stock' => 5]);

        $this->actingAs($user)->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertCreated();

        $this->fakeStripe([
            'id' => 'pi_short',
            'object' => 'payment_intent',
            'status' => 'succeeded',
            'amount' => 20000,
            'currency' => 'usd',
        ]);

        $this->actingAs($user)->postJson('/api/checkout', [
            'shipping_name' => 'Aymane',
            'shipping_phone' => '+212600000000',
            'shipping_address' => '123 Rue',
            'shipping_city' => 'Tanger',
            'payment_intent_id' => 'pi_short',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Payment amount does not match the order total.');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_checkout_marks_order_paid_when_payment_matches_order_total(): void
    {
        config(['services.stripe.secret' => 'sk_test_fake']);

        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100, 'stock' => 5]);

        $this->actingAs($user)->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertCreated();

        $this->fakeStripe([
            'id' => 'pi_paid_230',
            'object' => 'payment_intent',
            'status' => 'succeeded',
            'amount' => 23000,
            'currency' => 'usd',
        ]);

        $this->actingAs($user)->postJson('/api/checkout', [
            'shipping_name' => 'Aymane',
            'shipping_phone' => '+212600000000',
            'shipping_address' => '123 Rue',
            'shipping_city' => 'Tanger',
            'payment_intent_id' => 'pi_paid_230',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.subtotal', '200.00')
            ->assertJsonPath('data.shipping', '30.00')
            ->assertJsonPath('data.total', '230.00');

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'status' => 'paid',
            'subtotal' => '200.00',
            'shipping' => '30.00',
            'total' => '230.00',
            'stripe_payment_intent_id' => 'pi_paid_230',
        ]);
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertSame(3, $product->fresh()->stock);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>|null  $paymentIntent
     */
    private function fakeStripe(?array $paymentIntent = null): RecordingStripeClient
    {
        $client = new RecordingStripeClient($paymentIntent);
        ApiRequestor::setHttpClient($client);

        return $client;
    }
}

class RecordingStripeClient implements ClientInterface
{
    public ?int $createdAmount = null;

    /**
     * @param  array<string, mixed>|null  $paymentIntent
     */
    public function __construct(private ?array $paymentIntent = null) {}

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        if ($method === 'post') {
            $this->createdAmount = (int) ($params['amount'] ?? 0);

            return [json_encode([
                'id' => 'pi_created',
                'object' => 'payment_intent',
                'amount' => $this->createdAmount,
                'currency' => 'usd',
                'status' => 'requires_payment_method',
                'client_secret' => 'pi_created_secret_test',
            ]), 200, []];
        }

        return [json_encode($this->paymentIntent), 200, []];
    }
}
