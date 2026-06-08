<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $orders = DB::table('orders')
            ->leftJoin('reward_transactions', function ($join) {
                $join->on('orders.id', '=', 'reward_transactions.order_id')
                    ->where('reward_transactions.type', '=', 'earned');
            })
            ->whereNull('reward_transactions.id')
            ->select('orders.id', 'orders.user_id', 'orders.created_at')
            ->orderBy('orders.id')
            ->get();

        foreach ($orders as $order) {
            DB::table('reward_transactions')->insert([
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'type' => 'earned',
                'points' => 10,
                'description' => "Order #{$order->id} reward points",
                'created_at' => $order->created_at,
                'updated_at' => now(),
            ]);
        }

        $totals = DB::table('reward_transactions')
            ->select('user_id')
            ->selectRaw("SUM(CASE WHEN type = 'earned' THEN points ELSE 0 END) as earned_points")
            ->selectRaw("ABS(SUM(CASE WHEN type = 'redeemed' THEN points ELSE 0 END)) as redeemed_points")
            ->selectRaw("MAX(CASE WHEN type = 'earned' THEN created_at ELSE NULL END) as last_earned_at")
            ->groupBy('user_id')
            ->get();

        foreach ($totals as $total) {
            DB::table('reward_balances')->updateOrInsert(
                ['user_id' => $total->user_id],
                [
                    'current_points' => max(0, (int) $total->earned_points - (int) $total->redeemed_points),
                    'lifetime_points' => (int) $total->earned_points,
                    'redeemed_points' => (int) $total->redeemed_points,
                    'last_earned_at' => $total->last_earned_at,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('reward_transactions')
            ->where('type', 'earned')
            ->where('description', 'like', 'Order #% reward points')
            ->delete();

        DB::table('reward_balances')->delete();
    }
};
