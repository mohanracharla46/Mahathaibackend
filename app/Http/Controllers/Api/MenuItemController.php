<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MenuItemController extends Controller
{
    public function index(): JsonResponse
    {
        $items = MenuItem::with('category')->latest()->get()->map(fn (MenuItem $item) => $this->attachSuggestedItems($item));

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'exists:menu_categories,id'],
            'sub_category' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'image_url' => ['nullable', 'string'],
            'rating' => ['nullable', 'numeric', 'between:0,5'],
            'is_available' => ['nullable', 'boolean'],
            'addon_options' => ['nullable', 'array'],
            'addon_options.*.name' => ['required_with:addon_options', 'string', 'max:255'],
            'addon_options.*.price' => ['required_with:addon_options', 'numeric', 'min:0'],
            'protein_choice' => ['nullable', 'array'],
            'protein_choice.*.name' => ['required_with:protein_choice', 'string', 'max:255'],
            'protein_choice.*.price' => ['required_with:protein_choice', 'numeric', 'min:0'],
            'spice_options' => ['nullable', 'array'],
            'spice_options.*' => ['string', 'max:100'],
            'size_options' => ['nullable', 'array'],
            'size_options.*.name' => ['required_with:size_options', 'string', 'max:255'],
            'size_options.*.price' => ['required_with:size_options', 'numeric', 'min:0'],
            'suggested_item_ids' => ['nullable', 'array'],
            'suggested_item_ids.*' => ['integer', 'exists:menu_items,id'],
        ]);

        return response()->json($this->attachSuggestedItems(MenuItem::create($data)->fresh('category')), 201);
    }

    public function uploadImage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'file', 'image'],
        ]);

        $file = $data['image'];
        $directory = public_path('uploads/menu-items');

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $file->move($directory, $filename);

        return response()->json([
            'image_url' => url("uploads/menu-items/{$filename}"),
        ], 201);
    }

    public function update(Request $request, MenuItem $menuItem): JsonResponse
    {
        $data = $request->validate([
            'category_id' => ['sometimes', 'exists:menu_categories,id'],
            'sub_category' => ['nullable', 'string', 'max:255'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'image_url' => ['nullable', 'string'],
            'rating' => ['nullable', 'numeric', 'between:0,5'],
            'is_available' => ['nullable', 'boolean'],
            'addon_options' => ['nullable', 'array'],
            'addon_options.*.name' => ['required_with:addon_options', 'string', 'max:255'],
            'addon_options.*.price' => ['required_with:addon_options', 'numeric', 'min:0'],
            'protein_choice' => ['nullable', 'array'],
            'protein_choice.*.name' => ['required_with:protein_choice', 'string', 'max:255'],
            'protein_choice.*.price' => ['required_with:protein_choice', 'numeric', 'min:0'],
            'spice_options' => ['nullable', 'array'],
            'spice_options.*' => ['string', 'max:100'],
            'size_options' => ['nullable', 'array'],
            'size_options.*.name' => ['required_with:size_options', 'string', 'max:255'],
            'size_options.*.price' => ['required_with:size_options', 'numeric', 'min:0'],
            'suggested_item_ids' => ['nullable', 'array'],
            'suggested_item_ids.*' => ['integer', 'exists:menu_items,id'],
        ]);

        $menuItem->update($data);

        return response()->json($this->attachSuggestedItems($menuItem->fresh('category')));
    }

    public function destroy(MenuItem $menuItem): JsonResponse
    {
        $menuItem->delete();

        return response()->json(['message' => 'Menu item deleted.']);
    }

    private function attachSuggestedItems(MenuItem $item): MenuItem
    {
        $ids = collect($item->suggested_item_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $id !== $item->id)
            ->values();

        $suggestedItems = $ids->isEmpty()
            ? collect()
            : MenuItem::whereIn('id', $ids)
                ->get(['id', 'category_id', 'sub_category', 'name', 'description', 'price', 'image_url', 'rating', 'is_available', 'addon_options', 'protein_choice', 'spice_options', 'size_options']);

        $item->setAttribute('suggested_items', $suggestedItems->values());

        return $item;
    }
}


