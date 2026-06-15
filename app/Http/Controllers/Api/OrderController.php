<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\DispatchUberDelivery;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PromoCode;
use App\Models\RewardBalance;
use App\Models\RewardTransaction;
use App\Services\UberDirectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OrderController extends Controller
{
    public function index(): JsonResponse
    {
        $query = Order::with(['user', 'promoCode', 'items.menuItem'])->latest();

        if (request('user_id')) {
            $query->where('user_id', request('user_id'));
        } elseif (request('email')) {
            $email = request('email');
            $query->where(function ($emailQuery) use ($email) {
                $emailQuery
                    ->where('email', $email)
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->where('email', $email));
            });
        }

        if (request('status') && request('status') !== 'All') {
            $query->where('status', strtolower(request('status')));
        }

        if (request('order_type') && request('order_type') !== 'All') {
            $query->where(function ($typeQuery) {
                $typeQuery
                    ->where('order_type', strtolower(request('order_type')))
                    ->orWhere('service_type', strtolower(request('order_type')));
            });
        }

        if (request('search')) {
            $search = request('search');
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery
                    ->where('id', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery
                            ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = min(max((int) request('per_page', 10), 1), 100);

        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone_number' => ['required', 'string', 'max:50'],
            'order_type' => ['required', 'in:delivery,pickup'],
            'service_type' => ['nullable', 'in:delivery,pickup'],
            'pickup_time' => ['nullable', 'date_format:H:i'],
            'delivery_address' => ['nullable', 'required_if:order_type,delivery', 'string', 'max:1000'],
            'suite_apt' => ['nullable', 'string', 'max:100'],
            'promo_code_id' => ['nullable', 'exists:promo_codes,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.customization' => ['nullable', 'array'],
            'items.*.customization.size.name' => ['nullable', 'string', 'max:255'],
            'items.*.customization.protein.name' => ['nullable', 'string', 'max:255'],
            'items.*.customization.spice' => ['nullable', 'string', 'max:100'],
            'items.*.customization.addons' => ['nullable', 'array'],
            'items.*.customization.addons.*.name' => ['required', 'string', 'max:255'],
            'items.*.customization.requirements' => ['nullable', 'string', 'max:1000'],
        ]);

        $type = $data['service_type'] ?? $data['order_type'];
        $pricedItems = $this->priceItems($data['items']);
        $subtotal = round($pricedItems->sum('subtotal'), 2);
        $discount = $this->discountFor($data['promo_code_id'] ?? null, $subtotal);
        $total = round(max(0, $subtotal - $discount), 2);

        $order = DB::transaction(function () use (
            $data,
            $type,
            $pricedItems,
            $subtotal,
            $discount,
            $total
        ) {
            $order = Order::create([
                'user_id' => $data['user_id'] ?? null,
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone_number' => $data['phone_number'],
                'order_type' => $type,
                'service_type' => $type,
                'pickup_time' => $type === 'pickup' ? ($data['pickup_time'] ?? null) : null,
                'delivery_address' => $type === 'delivery' ? $data['delivery_address'] : null,
                'suite_apt' => $data['suite_apt'] ?? null,
                'promo_code_id' => $data['promo_code_id'] ?? null,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'total_amount' => $total,
                'order_items' => $pricedItems->values()->all(),
                'status' => 'pending',
            ]);

            foreach ($pricedItems as $item) {
                $order->items()->create([
                    'menu_item_id' => $item['menu_item_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'subtotal' => $item['subtotal'],
                ]);
            }

            $this->awardRewards($order, max(0, $subtotal - $discount));

            return $order;
        });

        return response()->json($order->fresh(['user', 'promoCode', 'items.menuItem']), 201);
    }

    public function update(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'max:50'],
        ]);

        $allowedStatuses = [
            'pending',
            'preparing',
            'ready for pickup',
            'out for delivery',
            'delivered',
            'completed',
            'cancelled',
            'canceled',
        ];
        $status = strtolower(trim($data['status']));

        if (! in_array($status, $allowedStatuses, true)) {
            return response()->json(['message' => 'Invalid order status.'], 422);
        }

        $order->forceFill(['status' => $status])->save();

        if ($status === 'ready for pickup' && $this->isDelivery($order) && blank($order->uber_delivery_id)) {
            DispatchUberDelivery::dispatch($order->id);
        }

        return response()->json($order->fresh(['user', 'promoCode', 'items.menuItem']));
    }

    public function destroy(Order $order): JsonResponse
    {
        $order->delete();

        return response()->json([
            'message' => 'Order deleted successfully.',
        ]);
    }

    public function dispatch(Order $order): JsonResponse
    {
        if (! $this->isDelivery($order)) {
            return response()->json(['message' => 'Only delivery orders can be dispatched.'], 422);
        }

        if (filled($order->uber_delivery_id)) {
            return response()->json(['message' => 'This order already has an Uber delivery.'], 409);
        }

        $order->forceFill([
            'status' => 'ready for pickup',
            'uber_delivery_error' => null,
        ])->save();

        DispatchUberDelivery::dispatch($order->id);

        return response()->json([
            'message' => 'Uber Direct dispatch queued.',
            'order' => $order->fresh(['user', 'promoCode', 'items.menuItem']),
        ], 202);
    }

    public function retryDispatch(Order $order): JsonResponse
    {
        return $this->dispatch($order);
    }

    public function cancelDelivery(Order $order, UberDirectService $uberDirect): JsonResponse
    {
        try {
            $delivery = $uberDirect->cancelDelivery($order);
        } catch (RuntimeException $error) {
            return response()->json([
                'message' => $error->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Uber Direct delivery cancelled.',
            'delivery' => $delivery,
            'order' => $order->fresh(['user', 'promoCode', 'items.menuItem']),
        ]);
    }

    private function priceItems(array $requestedItems): Collection
    {
        $menuItems = MenuItem::whereIn('id', collect($requestedItems)->pluck('menu_item_id'))
            ->get()
            ->keyBy('id');

        return collect($requestedItems)->map(function (array $requested, int $index) use ($menuItems) {
            $menuItem = $menuItems->get($requested['menu_item_id']);

            if (! $menuItem || ! $menuItem->is_available) {
                throw ValidationException::withMessages([
                    "items.{$index}.menu_item_id" => 'This menu item is unavailable.',
                ]);
            }

            $customization = $requested['customization'] ?? [];
            $size = $this->selectedOption($menuItem->size_options, data_get($customization, 'size.name'), "items.{$index}.customization.size");
            $protein = $this->selectedOption($menuItem->protein_choice, data_get($customization, 'protein.name'), "items.{$index}.customization.protein");
            $addons = collect($customization['addons'] ?? [])->map(
                fn (array $addon) => $this->selectedOption($menuItem->addon_options, $addon['name'], "items.{$index}.customization.addons")
            )->filter()->values();

            $unitPrice = $size ? (float) $size['price'] : (float) $menuItem->price;
            $unitPrice += (float) ($protein['price'] ?? 0);
            $unitPrice += $addons->sum(fn ($addon) => (float) ($addon['price'] ?? 0));
            $quantity = (int) $requested['quantity'];
            $unitPrice = round($unitPrice, 2);

            return [
                'menu_item_id' => $menuItem->id,
                'name' => $menuItem->name,
                'quantity' => $quantity,
                'price' => $unitPrice,
                'subtotal' => round($unitPrice * $quantity, 2),
                'customization' => [
                    'size' => $size,
                    'protein' => $protein,
                    'spice' => $customization['spice'] ?? null,
                    'addons' => $addons->all(),
                    'requirements' => $customization['requirements'] ?? null,
                ],
            ];
        });
    }

    private function selectedOption(?array $options, ?string $name, string $field): ?array
    {
        if (blank($name)) {
            return null;
        }

        $match = collect($options ?? [])->first(
            fn ($option) => strcasecmp((string) ($option['name'] ?? ''), $name) === 0
        );

        if (! $match) {
            throw ValidationException::withMessages([$field => "The selected option '{$name}' is unavailable."]);
        }

        return [
            'name' => $match['name'],
            'price' => round((float) ($match['price'] ?? 0), 2),
        ];
    }

    private function discountFor(?int $promoCodeId, float $subtotal): float
    {
        if (! $promoCodeId) {
            return 0;
        }

        $promo = PromoCode::find($promoCodeId);
        $today = now()->startOfDay();
        $valid = $promo
            && $promo->is_active
            && (! $promo->start_date || $promo->start_date->startOfDay()->lte($today))
            && (! $promo->end_date || $promo->end_date->endOfDay()->gte($today))
            && (! $promo->minimum_order_amount || $subtotal >= (float) $promo->minimum_order_amount);

        if (! $valid) {
            throw ValidationException::withMessages(['promo_code_id' => 'This promo code is no longer valid.']);
        }

        $discount = $promo->discount_type === 'percentage'
            ? $subtotal * ((float) $promo->discount_value / 100)
            : (float) $promo->discount_value;

        return round(min($subtotal, $discount), 2);
    }

    private function awardRewards(Order $order, float $eligibleAmount): void
    {
        if (! $order->user_id) {
            return;
        }

        $points = (int) floor($eligibleAmount / 10);
        $balance = RewardBalance::firstOrCreate(
            ['user_id' => $order->user_id],
            ['current_points' => 0, 'lifetime_points' => 0, 'redeemed_points' => 0]
        );

        if ($points < 1) {
            return;
        }

        $balance->increment('current_points', $points);
        $balance->increment('lifetime_points', $points);
        $balance->forceFill(['last_earned_at' => now()])->save();

        RewardTransaction::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'type' => 'earned',
            'points' => $points,
            'description' => "Order #{$order->id} reward points",
        ]);
    }

    private function isDelivery(Order $order): bool
    {
        return strtolower((string) ($order->service_type ?: $order->order_type)) === 'delivery';
    }
}
