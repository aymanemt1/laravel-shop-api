<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class PaymentController extends Controller
{
    /**
     * Create a Stripe PaymentIntent for the current cart total.
     * The frontend confirms it with the publishable key, then calls
     * POST /checkout with the payment_intent_id.
     */
    public function createIntent(Request $request): JsonResponse
    {
        $secret = config('services.stripe.secret');

        if (! $secret) {
            return response()->json([
                'message' => 'Stripe is not configured. Set STRIPE_SECRET in your .env.',
            ], 503);
        }

        $cart = $request->user()->cartItems()->with('product')->get();

        if ($cart->isEmpty()) {
            return response()->json(['message' => 'Your cart is empty.'], 422);
        }

        $subtotal = $cart->sum(fn ($item) => $item->quantity * $item->product->price);
        $amountCents = (int) round($subtotal * 100);

        Stripe::setApiKey($secret);

        try {
            $intent = PaymentIntent::create([
                'amount' => $amountCents,
                'currency' => 'usd',
                'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never'],
                'metadata' => ['user_id' => $request->user()->id],
            ]);
        } catch (ApiErrorException $e) {
            return response()->json([
                'message' => 'Could not start the payment: ' . $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'client_secret' => $intent->client_secret,
            'payment_intent_id' => $intent->id,
            'amount' => $subtotal,
        ]);
    }

    /**
     * Verify that a PaymentIntent succeeded. Returns the intent id on
     * success, throws a validation-style exception otherwise.
     */
    public static function verifySucceeded(string $paymentIntentId): string
    {
        $secret = config('services.stripe.secret');

        if (! $secret) {
            abort(response()->json([
                'message' => 'Stripe is not configured. Set STRIPE_SECRET in your .env.',
            ], 503));
        }

        Stripe::setApiKey($secret);

        try {
            $intent = PaymentIntent::retrieve($paymentIntentId);
        } catch (ApiErrorException $e) {
            abort(response()->json([
                'message' => 'Payment verification failed: ' . $e->getMessage(),
            ], 422));
        }

        if ($intent->status !== 'succeeded') {
            abort(response()->json([
                'message' => 'Payment has not completed yet.',
            ], 422));
        }

        return $intent->id;
    }
}
