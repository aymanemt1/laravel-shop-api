<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'line_total' => number_format($this->line_total, 2, '.', ''),
            'product' => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
