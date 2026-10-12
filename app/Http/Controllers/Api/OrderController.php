<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Models\CartItem;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = $request->user()->orders()
            ->with('items')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return OrderResource::collection($orders);
    }

    public function show(Request $request, Order $order): OrderResource
    {
        abort_unless($order->user_id === $request->user()->id || $request->user()->isAdmin(), 403);

        return new OrderResource($order->load('items'));
    }

    public function checkout(CheckoutRequest $request): JsonResponse
    {
        // Optional Stripe payment: verify the intent succeeded first
        $paymentIntentId = null;
        if ($request->filled('payment_intent_id')) {
            $paymentIntentId = PaymentController::verifySucceeded($request->input('payment_intent_id'));
        }

        $cartItems = $request->user()->cartItems()->with('product')->get();

        abort_if($cartItems->isEmpty(), 422, 'Your cart is empty.');

        foreach ($cartItems as $item) {
            abort_if(! $item->product->is_active, 422, "Product {$item->product->name} is no longer available.");
            abort_if($item->product->stock < $item->quantity, 422, "Not enough stock for {$item->product->name}.");
        }

        $order = DB::transaction(function () use ($request, $cartItems, $paymentIntentId) {
            $subtotal = $cartItems->sum(fn (CartItem $item) => $item->line_total);
            $shipping = $subtotal >= 500 ? 0 : 30;

            $order = Order::create([
                'user_id' => $request->user()->id,
                'subtotal' => $subtotal,
                'shipping' => $shipping,
                'total' => $subtotal + $shipping,
                'status' => $paymentIntentId ? 'paid' : 'pending',
                'stripe_payment_intent_id' => $paymentIntentId,
                ...$request->validated(),
            ]);

            foreach ($cartItems as $item) {
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'unit_price' => $item->product->price,
                    'quantity' => $item->quantity,
                    'line_total' => $item->line_total,
                ]);

                $item->product->decrement('stock', $item->quantity);
            }

            $request->user()->cartItems()->delete();

            return $order;
        });

        return (new OrderResource($order->load('items')))->response()->setStatusCode(201);
    }

    public function updateStatus(Request $request, Order $order): OrderResource
    {
        abort_unless($request->user()->isAdmin(), 403);

        $request->validate([
            'status' => ['required', 'in:' . implode(',', Order::STATUSES)],
        ]);

        $order->update(['status' => $request->status]);

        return new OrderResource($order->load('items'));
    }
}
