<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class UberDirectService
{
    public function createDelivery(Order $order): ?array
    {
        return Cache::lock("uber_direct_dispatch_order_{$order->id}", 30)->block(5, function () use ($order) {
            $order->refresh();

            if (! $this->shouldDispatch($order)) {
                return null;
            }

            $response = $this->client()->post($this->deliveriesUrl(), $this->deliveryPayload($order));

            if ($response->failed()) {
                Log::warning('Uber Direct delivery creation failed.', [
                    'order_id' => $order->id,
                    'status' => $response->status(),
                    'response' => $response->json() ?? $response->body(),
                ]);

                throw new RuntimeException($this->responseError($response->json(), $response->body()));
            }

            $delivery = $response->json();

            $order->forceFill([
                'uber_delivery_id' => $delivery['id'] ?? $delivery['delivery_id'] ?? null,
                'uber_delivery_status' => $delivery['status'] ?? null,
                'uber_tracking_url' => $delivery['tracking_url'] ?? null,
                'uber_delivery_response' => $delivery,
                'uber_delivery_error' => null,
                'uber_delivery_dispatched_at' => now(),
            ])->save();

            return $delivery;
        });
    }

    public function cancelDelivery(Order $order): array
    {
        if (blank($order->uber_delivery_id)) {
            throw new RuntimeException('This order does not have an Uber delivery.');
        }

        $response = $this->client()
            ->withBody('{}', 'application/json')
            ->post($this->deliveryUrl($order->uber_delivery_id).'/cancel');

        if ($response->failed()) {
            Log::warning('Uber Direct delivery cancellation failed.', [
                'order_id' => $order->id,
                'delivery_id' => $order->uber_delivery_id,
                'status' => $response->status(),
                'response' => $response->json() ?? $response->body(),
            ]);

            throw new RuntimeException($this->responseError($response->json(), $response->body()));
        }

        $payload = $response->json() ?: ['status' => 'canceled'];
        $order->forceFill([
            'status' => 'cancelled',
            'uber_delivery_status' => $payload['status'] ?? 'canceled',
            'uber_delivery_response' => $payload,
            'uber_delivery_error' => null,
        ])->save();

        return $payload;
    }

    public function shouldDispatch(Order $order): bool
    {
        if (! $this->isEnabledAndConfigured()) {
            return false;
        }

        $type = strtolower((string) ($order->service_type ?: $order->order_type));

        return $type === 'delivery'
            && filled($order->delivery_address)
            && blank($order->uber_delivery_id);
    }

    public function isEnabledAndConfigured(): bool
    {
        return (bool) config('services.uber_direct.enabled')
            && filled(config('services.uber_direct.customer_id'))
            && filled(config('services.uber_direct.client_id'))
            && filled(config('services.uber_direct.client_secret'))
            && filled(config('services.uber_direct.pickup_address'))
            && filled(config('services.uber_direct.pickup_phone'));
    }

    public function deliveryPayload(Order $order): array
    {
        $order->loadMissing(['user', 'items.menuItem']);

        $customerName = $order->full_name ?: $order->user?->full_name ?: 'Maha Thai Guest';
        $customerPhone = $order->phone_number ?: $order->user?->phone;

        return array_filter([
            'external_delivery_id' => (string) $order->id,
            'pickup_name' => config('services.uber_direct.pickup_name', config('app.name')),
            'pickup_address' => $this->addressForUber(config('services.uber_direct.pickup_address')),
            'pickup_phone_number' => $this->phoneForUber(config('services.uber_direct.pickup_phone')),
            'pickup_business_name' => config('services.uber_direct.pickup_business_name'),
            'pickup_notes' => config('services.uber_direct.pickup_notes'),
            'dropoff_name' => $customerName,
            'dropoff_address' => $this->addressForUber($this->dropoffAddress($order)),
            'dropoff_phone_number' => $this->phoneForUber($customerPhone),
            'dropoff_notes' => $order->suite_apt ? "Suite/Apt: {$order->suite_apt}" : null,
            'manifest_reference' => "Maha Thai order #{$order->id}",
            'manifest_total_value' => $this->moneyToCents($order->total_amount),
            'manifest_items' => $this->manifestItems($order),
            'external_store_id' => config('services.uber_direct.external_store_id'),
        ], fn ($value) => filled($value) || is_array($value));
    }

    public function handleWebhook(array $payload): ?Order
    {
        $deliveryId = Arr::get($payload, 'delivery_id')
            ?? Arr::get($payload, 'id')
            ?? Arr::get($payload, 'data.delivery_id')
            ?? Arr::get($payload, 'data.id')
            ?? Arr::get($payload, 'data.delivery.id');

        $externalDeliveryId = Arr::get($payload, 'external_delivery_id')
            ?? Arr::get($payload, 'data.external_delivery_id')
            ?? Arr::get($payload, 'data.delivery.external_delivery_id');

        $order = null;

        if ($externalDeliveryId) {
            $order = Order::find($externalDeliveryId);
        }

        if (! $order && $deliveryId) {
            $order = Order::where('uber_delivery_id', $deliveryId)->first();
        }

        if (! $order) {
            return null;
        }

        $status = Arr::get($payload, 'status')
            ?? Arr::get($payload, 'data.status')
            ?? Arr::get($payload, 'data.delivery.status');
        $trackingUrl = Arr::get($payload, 'tracking_url')
            ?? Arr::get($payload, 'data.tracking_url')
            ?? Arr::get($payload, 'data.delivery.tracking_url');

        $order->forceFill(array_filter([
            'uber_delivery_id' => $deliveryId ?: $order->uber_delivery_id,
            'uber_delivery_status' => $status ?: $order->uber_delivery_status,
            'uber_tracking_url' => $trackingUrl ?: $order->uber_tracking_url,
            'uber_delivery_response' => $payload,
        ], fn ($value) => filled($value) || is_array($value)))->save();

        $orderStatus = match (strtolower((string) $status)) {
            'pickup', 'picked_up', 'courier_pickup', 'courier_picked_up' => 'out for delivery',
            'delivered', 'completed', 'courier_dropoff' => 'delivered',
            'canceled', 'cancelled', 'returned' => 'cancelled',
            default => null,
        };

        if ($orderStatus) {
            $order->forceFill(['status' => $orderStatus])->save();
        }

        return $order;
    }

    private function client(): PendingRequest
    {
        return Http::acceptJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->retry(3, 250, throw: false)
            ->withToken($this->accessToken());
    }

    private function accessToken(): string
    {
        return Cache::remember('uber_direct_access_token', 50 * 60, function () {
            $response = Http::asForm()
                ->connectTimeout(5)
                ->timeout(15)
                ->retry(3, 250, throw: false)
                ->post(config('services.uber_direct.oauth_url'), [
                    'client_id' => config('services.uber_direct.client_id'),
                    'client_secret' => config('services.uber_direct.client_secret'),
                    'grant_type' => 'client_credentials',
                    'scope' => config('services.uber_direct.scope', 'eats.deliveries'),
                ]);

            if ($response->failed()) {
                throw new RuntimeException($response->json('error_description') ?? 'Uber Direct authentication failed.');
            }

            return $response->json('access_token');
        });
    }

    private function deliveriesUrl(): string
    {
        $baseUrl = rtrim(config('services.uber_direct.base_url'), '/');
        $customerId = config('services.uber_direct.customer_id');

        return "{$baseUrl}/v1/customers/{$customerId}/deliveries";
    }

    private function deliveryUrl(string $deliveryId): string
    {
        return $this->deliveriesUrl().'/'.urlencode($deliveryId);
    }

    private function responseError(?array $json, string $body): string
    {
        $error = Arr::get($json, 'error');
        $errorMessage = is_string($error) ? $error : Arr::get($json, 'error.message');
        $errorCode = Arr::get($json, 'code')
            ?? Arr::get($json, 'error.code')
            ?? Arr::get($json, 'kind');
        $message = Arr::get($json, 'message')
            ?? Arr::get($json, 'error_description')
            ?? $errorMessage
            ?? ($body ?: 'Uber Direct request failed.');

        return $errorCode ? "{$message} ({$errorCode})" : $message;
    }

    private function dropoffAddress(Order $order): string
    {
        return trim(collect([$order->delivery_address, $order->suite_apt])->filter()->join(', '));
    }

    private function phoneForUber(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        $trimmed = trim($phone);

        if (str_starts_with($trimmed, '+')) {
            return preg_replace('/[^+0-9]/', '', $trimmed);
        }

        $digits = preg_replace('/\D+/', '', $trimmed);

        if (strlen($digits) === 10) {
            return '+1'.$digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return '+'.$digits;
        }

        return $trimmed;
    }

    private function addressForUber(?string $address): ?string
    {
        if (blank($address)) {
            return null;
        }

        $decoded = json_decode($address, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return collect([
                collect($decoded['street_address'] ?? [])->filter()->join(' '),
                $decoded['city'] ?? null,
                $decoded['state'] ?? null,
                $decoded['zip_code'] ?? null,
                $decoded['country'] ?? null,
            ])->filter()->join(', ');
        }

        return $address;
    }

    private function manifestItems(Order $order): array
    {
        if ($order->items->isNotEmpty()) {
            return $order->items->map(fn ($item) => [
                'name' => $item->menuItem?->name ?: "Menu item {$item->menu_item_id}",
                'quantity' => (int) $item->quantity,
                'price' => $this->moneyToCents($item->price),
            ])->values()->all();
        }

        $storedItems = is_string($order->order_items) ? json_decode($order->order_items, true) : $order->order_items;

        if (! is_array($storedItems)) {
            return [[
                'name' => "Maha Thai order #{$order->id}",
                'quantity' => 1,
                'price' => $this->moneyToCents($order->total_amount),
            ]];
        }

        return collect($storedItems)->map(fn ($item) => [
            'name' => $item['name'] ?? $item['displayName'] ?? 'Maha Thai item',
            'quantity' => (int) ($item['quantity'] ?? 1),
            'price' => $this->moneyToCents($item['price'] ?? 0),
        ])->values()->all();
    }

    private function moneyToCents(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
