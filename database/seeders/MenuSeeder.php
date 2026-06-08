<?php

namespace Database\Seeders;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        MenuItem::truncate();
        MenuCategory::truncate();
        Schema::enableForeignKeyConstraints();

        $path = database_path('menu_data.json');
        if (!file_exists($path)) {
            $this->command->error("menu_data.json not found at $path");
            return;
        }

        $json = file_get_contents($path);
        $data = json_decode($json, true);

        // We only seed specific categories from the JSON.
        // We exclude 'Dinner' (since its items are in their respective subcategories).
        // We exclude 'Plant-Based' (since its items are identical to 'Vegetarian' and we seed those).
        $categoriesToSeed = [
            'Lunch',
            'Vegetarian',
            'Appetizers',
            'Salads',
            'Soups & Claypots',
            'Noodle Bar',
            'Curry Kitchen',
            'Rice & Wok',
            'Street Kitchen',
            'From the Sea',
            'Chef’s Table',
            'Sweet Endings',
            'Beverages & Sides',
        ];

        foreach ($categoriesToSeed as $categoryName) {
            if (!isset($data[$categoryName])) {
                continue;
            }

            $category = MenuCategory::create([
                'name' => $categoryName,
                'description' => "Traditional and signature $categoryName selections.",
                'status' => 'active',
            ]);

            // For dinner subcategories, we set sub_category to the categoryName.
            // For Lunch and Vegetarian, sub_category is empty/null.
            $subCategoryValue = in_array($categoryName, ['Lunch', 'Vegetarian']) ? null : $categoryName;

            foreach ($data[$categoryName] as $item) {
                MenuItem::create([
                    'category_id' => $category->id,
                    'sub_category' => $subCategoryValue,
                    'name' => $item['name'] ?? 'Unnamed Item',
                    'description' => $item['description'] ?? '',
                    'price' => isset($item['price']) ? (float)$item['price'] : 0.00,
                    'image_url' => $item['image'] ?? null,
                    'rating' => isset($item['rating']) ? (float)$item['rating'] : 4.8,
                    'is_available' => true,
                    'addon_options' => [],
                    'protein_choice' => [],
                    'spice_options' => [],
                    'size_options' => [],
                    'suggested_item_ids' => [],
                ]);
            }
        }

        $this->command->info('Menu categories and items successfully seeded!');
    }
}
