<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use App\Models\RewardBalance;
use App\Models\RewardTransaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RewardController extends Controller
{
    public function index(): JsonResponse
    {
        User::query()
            ->whereDoesntHave('rewardBalance')
            ->get()
            ->each(function (User $user) {
                RewardBalance::create([
                    'user_id' => $user->id,
                    'current_points' => 0,
                    'lifetime_points' => 0,
                    'redeemed_points' => 0,
                ]);
            });

        $balances = RewardBalance::with('user')
            ->latest('updated_at')
            ->get();

        return response()->json($balances);
    }

    public function showForUser(User $user): JsonResponse
    {
        $balance = RewardBalance::firstOrCreate(
            ['user_id' => $user->id],
            ['current_points' => 0, 'lifetime_points' => 0, 'redeemed_points' => 0]
        );

        $transactions = RewardTransaction::with('order')
            ->where('user_id', $user->id)
            ->latest()
            ->limit(25)
            ->get();

        return response()->json([
            'balance' => $balance->load('user'),
            'transactions' => $transactions,
        ]);
    }

    public function redeem(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'points' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
            'reward_id' => ['nullable', 'string', 'max:50'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'minimum_order_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $result = DB::transaction(function () use ($user, $data) {
            $balance = RewardBalance::lockForUpdate()->firstOrCreate(
                ['user_id' => $user->id],
                ['current_points' => 0, 'lifetime_points' => 0, 'redeemed_points' => 0]
            );

            if ($balance->current_points < $data['points']) {
                abort(422, 'Insufficient reward points.');
            }

            $balance->decrement('current_points', $data['points']);
            $balance->increment('redeemed_points', $data['points']);
            $balance->refresh();

            RewardTransaction::create([
                'user_id' => $user->id,
                'type' => 'redeemed',
                'points' => -$data['points'],
                'description' => $data['description'] ?? 'Reward redeemed',
            ]);

            $prefix = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $data['reward_id'] ?? 'REWARD'));
            $prefix = substr($prefix ?: 'REWARD', 0, 8);
            do {
                $code = 'MAHA-' . $prefix . '-' . random_int(1000, 9999);
            } while (PromoCode::where('code', $code)->exists());

            $promoCode = PromoCode::create([
                'code' => $code,
                'discount_type' => 'fixed',
                'discount_value' => $data['discount_value'] ?? 0,
                'minimum_order_amount' => $data['minimum_order_amount'] ?? 0,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDays(30)->toDateString(),
                'is_active' => true,
            ]);

            return [
                'balance' => $balance->load('user'),
                'transactions' => RewardTransaction::where('user_id', $user->id)->latest()->limit(25)->get(),
                'promo_code' => $promoCode,
            ];
        });

        return response()->json($result);
    }
}
