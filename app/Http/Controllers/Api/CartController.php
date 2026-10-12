<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\CartItemResource;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = $request->user()->cartItems()->with('product.category')->get();

        $subtotal = $items->sum(fn (CartItem $item) => $item->line_total);

        return response()->json([
            'items' => CartItemResource::collection($items),
            'count' => $items->sum('quantity'),
            'subtotal' => number_format($subtotal, 2, '.', ''),
        ]);
    }

    public function store(UpdateCartItemRequest $request): JsonResponse
    {
        $product = Product::active()->findOrFail($request->product_id);

        abort_if($product->stock < $request->quantity, 422, 'Not enough stock available.');

        $item = $request->user()->cartItems()->updateOrCreate(
            ['product_id' => $product->id],
            ['quantity' => $request->quantity]
        );

        return response()->json(new CartItemResource($item->load('product.category')), 201);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $request->user()->cartItems()->where('product_id', $product->id)->delete();

        return response()->json(null, 204);
    }

    public function clear(Request $request): JsonResponse
    {
        $request->user()->cartItems()->delete();

        return response()->json(null, 204);
    }
}
