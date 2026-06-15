<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\UberDirectService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class DispatchUberDelivery implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public array $backoff = [30, 120, 300];

    public function __construct(public int $orderId) {}

    public function handle(UberDirectService $uberDirect): void
    {
        $order = Order::find($this->orderId);

        if (! $order) {
            return;
        }

        try {
            $uberDirect->createDelivery($order);
        } catch (Throwable $exception) {
            $order->forceFill(['uber_delivery_error' => $exception->getMessage()])->save();
            throw $exception;
        }
    }
}
