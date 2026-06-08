<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $transactions = DB::table('reward_transactions')
            ->join('orders', 'reward_transactions.order_id', '=', 'orders.id')
            ->where('reward_transactions.type', 'earned')
            ->select(
                'reward_transactions.id',
                'reward_transactions.user_id',
                'reward_transactions.points',
                'orders.total_amount'
            )
            ->get();

        foreach ($transactions as $transaction) {
            $newPoints = (int) floor(((float) $transaction->total_amount) / 10);
            $delta = $newPoints - (int) $transaction->points;

            if ($delta === 0) {
                continue;
            }

            DB::table('reward_transactions')
                ->where('id', $transaction->id)
                ->update([
                    'points' => $newPoints,
                    'updated_at' => now(),
                ]);

            DB::table('reward_balances')
                ->where('user_id', $transaction->user_id)
                ->update([
                    'current_points' => DB::raw('GREATEST(current_points + ' . $delta . ', 0)'),
                    'lifetime_points' => DB::raw('GREATEST(lifetime_points + ' . $delta . ', 0)'),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Intentionally not reversible because this migration corrects historical
        // reward totals from a fixed-per-order rule to the live amount-based rule.
    }
};
