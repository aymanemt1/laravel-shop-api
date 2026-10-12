<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $this->price,
            'compare_at_price' => $this->compare_at_price,
            'discount_percent' => $this->discount_percent,
            'stock' => $this->stock,
            'in_stock' => $this->stock > 0,
            'image_url' => $this->image_url,
            'is_active' => $this->is_active,
            'category' => new CategoryResource($this->whenLoaded('category')),
        ];
    }
}
