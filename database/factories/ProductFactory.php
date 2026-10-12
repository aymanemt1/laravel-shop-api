<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        $price = fake()->randomFloat(2, 50, 5000);

        return [
            'category_id' => Category::factory(),
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'price' => $price,
            'compare_at_price' => fake()->boolean(30) ? $price * 1.25 : null,
            'stock' => fake()->numberBetween(0, 100),
            'image_url' => 'https://picsum.photos/seed/' . Str::random(8) . '/800/600',
            'is_active' => true,
        ];
    }
}
