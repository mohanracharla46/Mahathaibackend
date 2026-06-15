<?php

namespace Tests\Feature;

use App\Jobs\DispatchUberDelivery;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UberDirectOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_pickup_order_is_priced_and_saved_atomically(): void
    {
        $item = $this->menuItem();

        $response = $this->postJson('/api/orders', [
            'full_name' => 'Guest Customer',
            'email' => 'guest@example.com',
            'phone_number' => '(214) 555-0199',
            'order_type' => 'pickup',
            'pickup_time' => '18:30',
            'items' => [[
                'menu_item_id' => $item->id,
                'quantity' => 2,
                'customization' => [
                    'size' => ['name' => 'Large'],
                    'protein' => ['name' => 'Chicken'],
                    'addons' => [['name' => 'Extra Vegetables']],
                ],
            ]],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user_id', null)
            ->assertJsonPath('subtotal', '37.00')
            ->assertJsonPath('total_amount', '37.00')
            ->assertJsonPath('order_items.0.name', 'Pad Thai')
            ->assertJsonPath('order_items.0.quantity', 2)
            ->assertJsonPath('order_items.0.customization.size.name', 'Large')
            ->assertJsonPath('order_items.0.customization.protein.name', 'Chicken');

        $this->assertIsArray($response->json('order_items'));

        $this->assertDatabaseHas('orders', [
            'email' => 'guest@example.com',
            'subtotal' => 37,
            'total_amount' => 37,
        ]);
        $this->assertDatabaseHas('order_items', [
            'menu_item_id' => $item->id,
            'quantity' => 2,
            'price' => 18.50,
            'subtotal' => 37,
        ]);
    }

    public function test_order_can_be_deleted_with_its_items(): void
    {
        $item = $this->menuItem();
        $orderResponse = $this->postJson('/api/orders', [
            'full_name' => 'Deleted Customer',
            'email' => 'deleted@example.com',
            'phone_number' => '214-555-0100',
            'order_type' => 'pickup',
            'pickup_time' => '18:30',
            'items' => [[
                'menu_item_id' => $item->id,
                'quantity' => 1,
                'customization' => [],
            ]],
        ])->assertCreated();

        $orderId = $orderResponse->json('id');
        $this->assertDatabaseHas('order_items', ['order_id' => $orderId]);

        $this->deleteJson("/api/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('message', 'Order deleted successfully.');

        $this->assertDatabaseMissing('orders', ['id' => $orderId]);
        $this->assertDatabaseMissing('order_items', ['order_id' => $orderId]);
    }

    public function test_delivery_order_is_saved_without_a_quote_and_dispatch_is_queued_when_ready(): void
    {
        Queue::fake();
        $item = $this->menuItem();

        $orderResponse = $this->postJson('/api/orders', [
            'full_name' => 'Delivery Guest',
            'email' => 'delivery@example.com',
            'phone_number' => '214-555-0199',
            'order_type' => 'delivery',
            'delivery_address' => '100 Main St',
            'suite_apt' => 'Apt 4',
            'items' => [[
                'menu_item_id' => $item->id,
                'quantity' => 1,
                'customization' => [],
            ]],
        ]);

        $orderResponse
            ->assertCreated()
            ->assertJsonPath('total_amount', '12.00');

        $orderId = $orderResponse->json('id');
        $this->patchJson("/api/orders/{$orderId}", ['status' => 'ready for pickup'])
            ->assertOk()
            ->assertJsonPath('status', 'ready for pickup');

        Queue::assertPushed(DispatchUberDelivery::class, fn ($job) => $job->orderId === $orderId);
    }

    public function test_cancel_delivery_explains_when_no_uber_delivery_exists(): void
    {
        $order = Order::create([
            'full_name' => 'Delivery Guest',
            'email' => 'delivery@example.com',
            'phone_number' => '+12145550199',
            'order_type' => 'delivery',
            'service_type' => 'delivery',
            'delivery_address' => '100 Main St',
            'total_amount' => 20,
        ]);

        $this->postJson("/api/orders/{$order->id}/delivery/cancel")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This order does not have an Uber delivery.');
    }

    public function test_cancel_delivery_calls_uber_cancel_action_and_updates_order(): void
    {
        Cache::forget('uber_direct_access_token');
        config([
            'services.uber_direct.base_url' => 'https://api.uber.test',
            'services.uber_direct.oauth_url' => 'https://login.uber.test/oauth/token',
            'services.uber_direct.customer_id' => 'customer-1',
            'services.uber_direct.client_id' => 'client-1',
            'services.uber_direct.client_secret' => 'secret-1',
        ]);
        Http::fake([
            'https://login.uber.test/oauth/token' => Http::response(['access_token' => 'token-1']),
            'https://api.uber.test/v1/customers/customer-1/deliveries/delivery-1/cancel' => Http::response([
                'id' => 'delivery-1',
                'status' => 'canceled',
            ]),
        ]);
        $order = Order::create([
            'full_name' => 'Delivery Guest',
            'email' => 'delivery@example.com',
            'phone_number' => '+12145550199',
            'order_type' => 'delivery',
            'service_type' => 'delivery',
            'delivery_address' => '100 Main St',
            'total_amount' => 20,
            'status' => 'ready for pickup',
            'uber_delivery_id' => 'delivery-1',
            'uber_delivery_status' => 'pending',
        ]);

        $this->postJson("/api/orders/{$order->id}/delivery/cancel")
            ->assertOk()
            ->assertJsonPath('order.status', 'cancelled')
            ->assertJsonPath('order.uber_delivery_status', 'canceled');

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://api.uber.test/v1/customers/customer-1/deliveries/delivery-1/cancel'
            && $request->body() === '{}');
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'cancelled',
            'uber_delivery_status' => 'canceled',
        ]);
    }

    public function test_webhook_requires_a_configured_valid_signature(): void
    {
        $order = Order::create([
            'full_name' => 'Webhook Customer',
            'email' => 'webhook@example.com',
            'phone_number' => '+12145550199',
            'order_type' => 'delivery',
            'service_type' => 'delivery',
            'delivery_address' => '100 Main St',
            'total_amount' => 20,
            'uber_delivery_id' => 'delivery-1',
        ]);
        $payload = json_encode([
            'delivery_id' => 'delivery-1',
            'status' => 'delivered',
            'tracking_url' => 'https://tracking.example/delivery-1',
        ]);

        config(['services.uber_direct.webhook_secret' => null]);
        $this->call(
            'POST',
            '/api/uber-direct/webhook',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $payload
        )->assertUnauthorized();

        config(['services.uber_direct.webhook_secret' => 'webhook-secret']);
        $signature = hash_hmac('sha256', $payload, 'webhook-secret');

        $this->call(
            'POST',
            '/api/uber-direct/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_UBER_SIGNATURE' => $signature,
            ],
            $payload
        )->assertOk();

        $order->refresh();
        $this->assertSame('delivered', $order->status);
        $this->assertSame('delivered', $order->uber_delivery_status);
        $this->assertSame('https://tracking.example/delivery-1', $order->uber_tracking_url);
    }

    private function menuItem(): MenuItem
    {
        $category = MenuCategory::create([
            'name' => 'Dinner',
            'status' => 'active',
        ]);

        return MenuItem::create([
            'category_id' => $category->id,
            'name' => 'Pad Thai',
            'price' => 12,
            'is_available' => true,
            'size_options' => [
                ['name' => 'Large', 'price' => 15],
            ],
            'protein_choice' => [
                ['name' => 'Chicken', 'price' => 2],
            ],
            'addon_options' => [
                ['name' => 'Extra Vegetables', 'price' => 1.5],
            ],
        ]);
    }
}
