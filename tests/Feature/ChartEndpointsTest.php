<?php

namespace MlSolutions\ChartJsIntegration\Tests\Feature;

use Illuminate\Support\Carbon;
use MlSolutions\ChartJsIntegration\Tests\Fixtures\Customer;
use MlSolutions\ChartJsIntegration\Tests\Fixtures\Order;
use MlSolutions\ChartJsIntegration\Tests\TestCase;

class ChartEndpointsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_total_records_endpoint_applies_not_in_filter(): void
    {
        Carbon::setTestNow('2026-02-15 10:00:00');

        $customer = Customer::query()->create(['name' => 'VIP']);
        $this->createOrder($customer->id, 'paid', 10, '2026-02-15 09:00:00');
        $this->createOrder($customer->id, 'cancelled', 5, '2026-02-15 10:30:00');
        $this->createOrder($customer->id, 'pending', 7, '2026-02-15 12:00:00');

        $response = $this->callEndpoint('/nova-vendor/mlsolutions/check-data/endpoint', [
            'model' => urlencode(Order::class),
            'col_xaxis' => 'orders.created_at',
            'options' => json_encode([
                'uom' => 'day',
                'showTotal' => false,
                'sum' => 'orders.amount',
                'queryFilter' => [[
                    'key' => 'orders.status',
                    'operator' => 'NOT IN',
                    'value' => ['cancelled'],
                ]],
            ]),
            'series' => json_encode([
                [
                    'label' => 'All Non-Null Status',
                    'filter' => [
                        'key' => 'orders.status',
                        'operator' => 'IS NOT NULL',
                    ],
                ],
            ]),
        ]);

        $response->assertOk();
        $response->assertJsonPath('dataset.xAxis.0', '2026-02-15');
        $response->assertJsonPath('dataset.yAxis.0.data.0', 17);
    }

    public function test_total_records_cache_key_changes_with_filter_payload(): void
    {
        Carbon::setTestNow('2026-02-16 10:00:00');

        $customer = Customer::query()->create(['name' => 'VIP']);
        $this->createOrder($customer->id, 'paid', 10, '2026-02-16 09:00:00');
        $this->createOrder($customer->id, 'pending', 7, '2026-02-16 09:05:00');

        $baseParams = [
            'model' => urlencode(Order::class),
            'col_xaxis' => 'orders.created_at',
            'series' => json_encode([
                [
                    'label' => 'All',
                    'filter' => [
                        'key' => 'orders.status',
                        'operator' => 'IS NOT NULL',
                    ],
                ],
            ]),
        ];

        $first = $this->callEndpoint('/nova-vendor/mlsolutions/check-data/endpoint', [
            ...$baseParams,
            'options' => json_encode([
                'uom' => 'day',
                'showTotal' => false,
                'sum' => 'orders.amount',
                'queryFilter' => [[
                    'key' => 'orders.status',
                    'operator' => '=',
                    'value' => 'paid',
                ]],
            ]),
            'expires' => 10,
        ]);

        $second = $this->callEndpoint('/nova-vendor/mlsolutions/check-data/endpoint', [
            ...$baseParams,
            'options' => json_encode([
                'uom' => 'day',
                'showTotal' => false,
                'sum' => 'orders.amount',
                'queryFilter' => [[
                    'key' => 'orders.status',
                    'operator' => '=',
                    'value' => 'pending',
                ]],
            ]),
            'expires' => 10,
        ]);

        $first->assertOk();
        $second->assertOk();

        $first->assertJsonPath('dataset.yAxis.0.data.0', 10);
        $second->assertJsonPath('dataset.yAxis.0.data.0', 7);
    }

    public function test_total_circle_endpoint_supports_join_and_between_filters(): void
    {
        Carbon::setTestNow('2026-02-17 10:00:00');

        $vip = Customer::query()->create(['name' => 'VIP']);
        $regular = Customer::query()->create(['name' => 'Regular']);

        $this->createOrder($vip->id, 'paid', 10, '2026-02-17 08:00:00');
        $this->createOrder($regular->id, 'paid', 12, '2026-02-17 09:00:00');
        $this->createOrder($regular->id, 'paid', 20, '2026-02-17 10:00:00');

        $response = $this->callEndpoint('/nova-vendor/mlsolutions/check-data/circle-endpoint', [
            'model' => urlencode(Order::class),
            'col_xaxis' => 'orders.created_at',
            'join' => json_encode([
                'joinTable' => 'customers',
                'joinColumnFirst' => 'orders.customer_id',
                'joinEqual' => '=',
                'joinColumnSecond' => 'customers.id',
            ]),
            'options' => json_encode([
                'sum' => 'orders.amount',
                'queryFilter' => [[
                    'key' => 'orders.amount',
                    'operator' => 'BETWEEN',
                    'value' => [5, 15],
                ]],
            ]),
            'series' => json_encode([
                [
                    'label' => 'VIP',
                    'filter' => [
                        'key' => 'customers.name',
                        'operator' => '=',
                        'value' => 'VIP',
                    ],
                ],
                [
                    'label' => 'Non VIP',
                    'filter' => [
                        'key' => 'customers.name',
                        'operator' => '!=',
                        'value' => 'VIP',
                    ],
                ],
            ]),
        ]);

        $response->assertOk();
        $response->assertJsonPath('dataset.xAxis.0', 'VIP');
        $response->assertJsonPath('dataset.xAxis.1', 'Non VIP');
        $response->assertJsonPath('dataset.yAxis.0.data.0', 10);
        $response->assertJsonPath('dataset.yAxis.0.data.1', 12);
    }

    public function test_endpoint_rejects_invalid_x_axis_column(): void
    {
        Carbon::setTestNow('2026-02-18 10:00:00');

        $customer = Customer::query()->create(['name' => 'VIP']);
        $this->createOrder($customer->id, 'paid', 1, '2026-02-18 09:00:00');

        $response = $this->callEndpoint('/nova-vendor/mlsolutions/check-data/endpoint', [
            'model' => urlencode(Order::class),
            'col_xaxis' => 'orders.created_at;DROP TABLE orders',
            'options' => json_encode([
                'uom' => 'day',
            ]),
        ]);

        $response->assertStatus(500);
    }

    private function callEndpoint(string $path, array $params)
    {
        return $this->getJson($path.'?'.http_build_query($params));
    }

    private function createOrder(?int $customerId, string $status, int $amount, string $createdAt): void
    {
        Order::query()->create([
            'customer_id' => $customerId,
            'status' => $status,
            'amount' => $amount,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
