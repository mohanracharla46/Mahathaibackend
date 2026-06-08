<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\RewardBalance;
use App\Models\RewardTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(): JsonResponse
    {
        $query = Order::with(['user', 'promoCode', 'items.menuItem'])->latest();

        if (request('user_id')) {
            $query->where('user_id', request('user_id'));
        } elseif (request('email')) {
            $query->whereHas('user', function ($userQuery) {
                $userQuery->where('email', request('email'));
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
            'user_id' => ['required', 'exists:users,id'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'order_type' => ['required', 'string', 'max:100'],
            'service_type' => ['nullable', 'string', 'max:100'],
            'pickup_time' => ['nullable', 'date_format:H:i'],
            'delivery_address' => ['nullable', 'string'],
            'suite_apt' => ['nullable', 'string', 'max:100'],
            'items' => ['nullable', 'string'],
            'order_items' => ['nullable', 'string'],
            'promo_code_id' => ['nullable', 'exists:promo_codes,id'],
            'subtotal' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);
        $data['order_items'] = $data['order_items'] ?? $data['items'] ?? null;
        unset($data['items']);

        $order = DB::transaction(function () use ($data) {
            $createdOrder = Order::create($data);
            $points = (int) floor((float) ($createdOrder->total_amount ?? 0) / 10);

            $balance = RewardBalance::firstOrCreate(
                ['user_id' => $createdOrder->user_id],
                ['current_points' => 0, 'lifetime_points' => 0, 'redeemed_points' => 0]
            );

            if ($points > 0) {
                $balance->increment('current_points', $points);
                $balance->increment('lifetime_points', $points);
                $balance->forceFill(['last_earned_at' => now()])->save();

                RewardTransaction::create([
                    'user_id' => $createdOrder->user_id,
                    'order_id' => $createdOrder->id,
                    'type' => 'earned',
                    'points' => $points,
                    'description' => "Order #{$createdOrder->id} reward points",
                ]);
            }

            return $createdOrder;
        });

        return response()->json($order->load(['user', 'promoCode', 'items.menuItem']), 201);
    }
}
