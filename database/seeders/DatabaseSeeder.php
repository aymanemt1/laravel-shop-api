<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@shop.test',
            'password' => 'password',
            'is_admin' => true,
        ]);

        User::factory()->create([
            'name' => 'Demo Customer',
            'email' => 'customer@shop.test',
            'password' => 'password',
        ]);

        $categories = [
            ['name' => 'Electronics', 'slug' => 'electronics', 'description' => 'Phones, laptops and gadgets.'],
            ['name' => 'Fashion', 'slug' => 'fashion', 'description' => 'Clothing and accessories.'],
            ['name' => 'Home & Living', 'slug' => 'home-living', 'description' => 'Furniture and home essentials.'],
            ['name' => 'Sports', 'slug' => 'sports', 'description' => 'Sportswear and equipment.'],
        ];

        foreach ($categories as $category) {
            $cat = Category::create($category);
            Product::factory(12)->create(['category_id' => $cat->id]);
        }

        $this->command->info('Seeded admin: admin@shop.test / password');
        $this->command->info('Seeded customer: customer@shop.test / password');
    }
}
