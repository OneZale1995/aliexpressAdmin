<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shop;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AliExpressService
{
    protected string $baseUrl;
    protected bool $verifySsl;

    public function __construct()
    {
        $this->baseUrl = (string) config('services.aliexpress.base_url', 'https://openapi.aliexpress.ru');
        $this->verifySsl = (bool) config('services.aliexpress.verify_ssl', false);
    }

    /**
     * 同步指定店铺的订单
     */
    public function syncOrders(Shop $shop, array $params = []): array
    {
        $token = $shop->access_token;
        if (!$token) {
            return ['synced' => 0, 'error' => '店铺未配置access_token'];
        }

        $page = 1;
        $pageSize = 50;
        $synced = 0;

        do {
            $body = array_merge([
                'page' => $page,
                'page_size' => $pageSize,
                'trade_order_info' => 'LogisticInfo',
                'sorting_order' => 'Desc',
            ], $params);

            try {
                $response = Http::withHeaders([
                    'x-auth-token' => $token,
                    'x-request-locale' => 'en',
                    'Content-Type' => 'application/json',
                ])
                    ->withOptions(['verify' => $this->verifySsl])
                    ->timeout(30)
                    ->post($this->baseUrl . '/seller-api/v1/order/get-order-list', $body);

                $data = $response->json();

                if (!$response->successful() || isset($data['error'])) {
                    Log::error('AliExpress API error', [
                        'shop_id' => $shop->id,
                        'status' => $response->status(),
                        'response' => $data,
                    ]);
                    return ['synced' => $synced, 'error' => $data['error']['message'] ?? 'API请求失败'];
                }

                $orders = $data['data']['orders'] ?? [];

                foreach ($orders as $orderData) {
                    $this->upsertOrder($shop, $orderData);
                    $synced++;
                }

                $page++;
            } catch (\Exception $e) {
                Log::error('AliExpress sync exception', [
                    'shop_id' => $shop->id,
                    'message' => $e->getMessage(),
                ]);
                return ['synced' => $synced, 'error' => $e->getMessage()];
            }
        } while (count($orders ?? []) >= $pageSize);

        // 更新店铺的订单同步时间
        $shop->update(['order_updated_at' => now()]);

        return ['synced' => $synced, 'error' => null];
    }

    /**
     * 插入或更新单个订单
     */
    protected function upsertOrder(Shop $shop, array $data): Order
    {
        $aeOrderId = $data['id'];

        // 解析commission
        $commission = $data['commission'] ?? [];
        $platformFee = ($commission['platform_fee'] ?? 0) / 100;
        $affiliateFee = ($commission['affiliate_fee'] ?? 0) / 100;
        $estimateRevenue = ($commission['estimate_revenue'] ?? 0) / 100;

        // 解析收货地址
        $deliveryInfo = $data['delivery_info'] ?? [];
        $receiver = $deliveryInfo['receiver'] ?? [];

        // 解析物流信息
        $logisticOrders = $data['logistic_orders'] ?? [];
        $trackingNumber = '';
        $logisticsType = '';
        $markedShipAt = null;
        $actualShipAt = null;
        if (!empty($logisticOrders)) {
            $trackingNumber = $logisticOrders[0]['track_number'] ?? '';
            // 从 pre_split_postings 获取物流类型
        }
        $preSplit = $data['pre_split_postings'] ?? [];
        if (!empty($preSplit)) {
            $logisticsType = $preSplit[0]['logistics_type'] ?? '';
        }

        $logisticOrderId = null;
        if (!empty($logisticOrders) && !empty($logisticOrders[0]['id'])) {
            $logisticOrderId = (int) $logisticOrders[0]['id'];
        }

        // 计算快递费(从pre_split_postings的delivery_fee)
        $shippingFee = 0;
        foreach ($preSplit as $posting) {
            $shippingFee += ($posting['delivery_fee'] ?? 0) / 100;
        }

        $existingOrder = Order::where('ae_order_id', $aeOrderId)->first();
        $apiShipQianze = $this->parseDate(
            $data['actual_ship_at']
                ?? $data['shipped_at']
                ?? ($logisticOrders[0]['actual_ship_at'] ?? null)
                ?? ($logisticOrders[0]['shipped_at'] ?? null)
        );

        $order = Order::updateOrCreate(
            ['ae_order_id' => $aeOrderId],
            [
                'shop_id' => $shop->id,
                'status' => $data['status'] ?? '',
                'payment_status' => $data['payment_status'] ?? '',
                'delivery_status' => $data['delivery_status'] ?? '',
                'order_display_status' => $data['order_display_status'] ?? '',
                'antifraud_status' => $data['antifraud_status'] ?? '',
                'total_amount' => ($data['total_amount'] ?? 0) / 100,
                'currency' => $data['order_lines'][0]['currency'] ?? 'RUB',
                'platform_fee' => $platformFee,
                'affiliate_fee' => $affiliateFee,
                'estimate_revenue' => $estimateRevenue,
                'buyer_name' => $data['buyer_name'] ?? '',
                'buyer_phone' => $data['buyer_phone'] ?? '',
                'buyer_country_code' => $data['buyer_country_code'] ?? '',
                'receiver_name' => $receiver['name'] ?? '',
                'receiver_phone' => $receiver['phone'] ?? '',
                'delivery_address' => $data['delivery_address'] ?? '',
                'receiver_country' => $deliveryInfo['country'] ?? '',
                'receiver_region' => $deliveryInfo['region'] ?? '',
                'receiver_city' => $deliveryInfo['city'] ?? '',
                'receiver_street' => $deliveryInfo['street_house'] ?? '',
                'receiver_zip' => $deliveryInfo['index'] ?? '',
                'logistics_type' => $logisticsType,
                'tracking_number' => $trackingNumber,
                'logistic_order_id' => $logisticOrderId,
                'finish_reason' => $data['finish_reason'] ?? '',
                'seller_comment' => $data['seller_comment'] ?? null,
                'fully_prepared' => $data['fully_prepared'] ?? false,
                'shipping_fee' => $shippingFee,
                'ae_created_at' => $this->parseDate($data['created_at'] ?? null),
                'ae_paid_at' => $this->parseDate($data['paid_at'] ?? null),
                'ae_updated_at' => $this->parseDate($data['updated_at'] ?? null),
                'cut_off_date' => $this->parseDate($data['cut_off_date'] ?? null),
                // 手工维护优先：仅在为空时，使用平台时间兜底填充。
                'apply_qianze_at' => $existingOrder && $existingOrder->apply_qianze_at
                    ? $existingOrder->apply_qianze_at
                    : $this->parseDate($data['cut_off_date'] ?? null),
                'ship_qianze_at' => $existingOrder && $existingOrder->ship_qianze_at
                    ? $existingOrder->ship_qianze_at
                    : $apiShipQianze,
                'shipping_deadline' => $this->parseDate($data['shipping_deadline'] ?? null),
                'disputes' => !empty($data['disputes']) ? $data['disputes'] : null,
                'raw_data' => $data,
            ]
        );

        // 同步订单行项
        $existingLineIds = [];
        foreach ($data['order_lines'] ?? [] as $line) {
            $fees = $line['order_line_fees'] ?? [];
            $item = OrderItem::updateOrCreate(
                ['order_id' => $order->id, 'ae_order_line_id' => $line['id']],
                [
                    'ae_item_id' => $line['item_id'] ?? '',
                    'ae_sku_id' => $line['sku_id'] ?? '',
                    'sku_code' => $line['sku_code'] ?? '',
                    'name' => $line['name'] ?? '',
                    'img_url' => $line['img_url'] ?? '',
                    'item_price' => ($line['item_price'] ?? 0) / 100,
                    'currency' => $line['currency'] ?? 'RUB',
                    'quantity' => $line['quantity'] ?? 1,
                    'total_amount' => ($line['total_amount'] ?? 0) / 100,
                    'properties' => $line['properties_map'] ?? null,
                    'line_fee' => ($fees['line_fee'] ?? 0),
                    'item_affiliate_fee' => ($fees['affiliate_fee'] ?? 0),
                    'item_estimate_revenue' => ($fees['estimate_revenue'] ?? 0),
                    'issue_status' => $line['issue_status'] ?? 'NoDispute',
                    'logistic_name' => $line['logistic_name'] ?? '',
                    'logistic_storage_type' => $line['logistic_storage_type'] ?? '',
                ]
            );
            $existingLineIds[] = $item->id;
        }

        // 删除不存在的行项
        if (!empty($existingLineIds)) {
            OrderItem::where('order_id', $order->id)
                ->whereNotIn('id', $existingLineIds)
                ->delete();
        }

        return $order;
    }

    /**
     * 提交发货（DBS 填单号，FBS 直接确认）
     * 返回 ['success' => bool, 'message' => string, 'data' => array|null]
     */
    public function markShip(Shop $shop, Order $order, string $trackNumber, string $logisticMethod = ''): array
    {
        $token = $shop->access_token;
        if (!$token) {
            return ['success' => false, 'message' => '店铺未配置access_token'];
        }

        // 从 raw_data 里拿 pre_split_posting IDs
        $rawData = $order->raw_data ?? [];
        $preSplitPostings = $rawData['pre_split_postings'] ?? [];
        if (empty($preSplitPostings)) {
            return ['success' => false, 'message' => '订单无发货包裹信息(pre_split_postings为空)'];
        }

        $results = [];
        $allOk = true;

        foreach ($preSplitPostings as $posting) {
            $postingId = $posting['id'] ?? null;
            $logisticMethodUsed = $logisticMethod ?: ($posting['logistic_method'] ?? '');

            if (!$postingId) continue;

            $body = [
                'posting_id' => (string) $postingId,
                'track_number' => $trackNumber,
            ];
            if ($logisticMethodUsed) {
                $body['logistic_method'] = $logisticMethodUsed;
            }

            try {
                $response = Http::withHeaders([
                    'x-auth-token' => $token,
                    'x-request-locale' => 'en',
                    'Content-Type' => 'application/json',
                ])
                    ->withOptions(['verify' => $this->verifySsl])
                    ->timeout(30)
                    ->post($this->baseUrl . '/seller-api/v1/logistic-order/mark-ship', $body);

                $data = $response->json();

                if (!$response->successful() || isset($data['error'])) {
                    $msg = $data['error']['message'] ?? ('发货请求失败: ' . $response->status());
                    Log::warning('AliExpress markShip failed', [
                        'order_id' => $order->ae_order_id,
                        'posting_id' => $postingId,
                        'response' => $data,
                    ]);
                    $results[] = ['posting_id' => $postingId, 'ok' => false, 'message' => $msg];
                    $allOk = false;
                } else {
                    $results[] = ['posting_id' => $postingId, 'ok' => true, 'message' => '发货成功'];
                }
            } catch (\Exception $e) {
                Log::error('AliExpress markShip exception', [
                    'order_id' => $order->ae_order_id,
                    'message' => $e->getMessage(),
                ]);
                $results[] = ['posting_id' => $postingId, 'ok' => false, 'message' => $e->getMessage()];
                $allOk = false;
            }
        }

        $message = $allOk ? '发货请求已提交' : collect($results)->where('ok', false)->pluck('message')->implode('; ');
        return ['success' => $allOk, 'message' => $message, 'data' => $results];
    }

    /**
     * DBS 线下发货 → 将订单状态变更为 InTransit
     * POST /api/v1/offline-ship/to-in-transit
     */
    public function offlineShipToInTransit(Shop $shop, Order $order, string $trackingNumber, string $providerName, string $trackingUrl = ''): array
    {
        $token = $shop->access_token;
        if (!$token) {
            return ['success' => false, 'message' => '店铺未配置access_token'];
        }

        $body = [
            'trade_order_id' => (int) $order->ae_order_id,
            'tracking_number' => $trackingNumber,
            'provider_name' => $providerName,
        ];
        if ($trackingUrl) {
            $body['tracking_url'] = $trackingUrl;
        }

        Log::info('AliExpress offlineShip toInTransit request', [
            'order_id' => $order->ae_order_id,
            'tracking_number' => $trackingNumber,
            'provider_name' => $providerName,
        ]);

        try {
            $response = Http::withHeaders([
                'x-auth-token' => $token,
                'x-request-locale' => 'en',
                'Content-Type' => 'application/json',
            ])
                ->withOptions(['verify' => $this->verifySsl])
                ->timeout(30)
                ->post($this->baseUrl . '/api/v1/offline-ship/to-in-transit', $body);

            $data = $response->json();

            if ($response->successful() && !isset($data['error'])) {
                return [
                    'success' => true,
                    'message' => 'DBS发货状态已更新为InTransit',
                    'data' => $data['data'] ?? $data,
                ];
            }

            $errorMsg = $data['error']['message'] ?? ($data['message'] ?? ('请求失败: ' . $response->status()));
            Log::warning('AliExpress offlineShip toInTransit failed', [
                'order_id' => $order->ae_order_id,
                'response' => $data,
            ]);

            return ['success' => false, 'message' => $errorMsg, 'data' => $data];
        } catch (\Exception $e) {
            Log::error('AliExpress offlineShip toInTransit exception', [
                'order_id' => $order->ae_order_id,
                'message' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 获取面单标签（返回 PDF URL 或 base64）
     */
    public function getShippingLabel(Shop $shop, Order $order): array
    {
        $token = $shop->access_token;
        if (!$token) {
            return ['success' => false, 'message' => '店铺未配置access_token'];
        }

        $rawData = $order->raw_data ?? [];
        // 只认原始数据里的 raw_data.logistic_orders[*].id
        $logisticOrders = is_array($rawData['logistic_orders'] ?? null) ? $rawData['logistic_orders'] : [];
        $logisticOrderIds = collect($logisticOrders)
            ->map(function ($item) {
                if (!is_array($item) || !array_key_exists('id', $item)) {
                    return null;
                }
                return (int) $item['id'];
            })
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->toArray();

        if (empty($logisticOrderIds)) {
            return ['success' => false, 'message' => '未找到logistic_order_id，请先创建货件并同步订单后再打印'];
        }

        Log::info('AliExpress getLabel request payload', [
            'order_id' => $order->ae_order_id,
            'logistic_order_ids' => $logisticOrderIds,
        ]);

        try {
            $response = Http::withHeaders([
                'accept' => 'application/json',
                'x-auth-token' => $token,
                'x-request-locale' => 'en',
                'Content-Type' => 'application/json',
            ])
                ->withOptions(['verify' => $this->verifySsl])
                ->timeout(30)
                ->post($this->baseUrl . '/seller-api/v1/labels/orders/get', [
                    'logistic_order_ids' => $logisticOrderIds,
                ]);

            $data = $response->json();
            $labelUrl = $data['data']['label_url'] ?? null;

            if ($response->successful() && $labelUrl) {
                return [
                    'success' => true,
                    'message' => '面单获取成功',
                    'label_url' => $labelUrl,
                    'used_ids' => $logisticOrderIds,
                    'raw' => $data['data'] ?? [],
                ];
            }

            $errorCode = $data['error']['code'] ?? null;
            $errorMessage = $data['error']['message'] ?? ($data['message'] ?? ('面单请求失败: ' . $response->status()));
            $finalMessage = $errorCode ? ($errorCode . ': ' . $errorMessage) : $errorMessage;

            if ($errorCode === 'LogisticProviderNotFound') {
                $logisticMethod = $rawData['pre_split_postings'][0]['logistic_method'] ?? '';
                if (str_starts_with((string) $logisticMethod, 'OTHER_')) {
                    $finalMessage = '当前物流方式为 ' . $logisticMethod . '（第三方/其他物流），速卖通平台不提供API面单打印，请在线下渠道打印。';
                } else {
                    $finalMessage = '物流服务商不支持面单API打印（' . $errorCode . '），请确认该物流方式是否支持平台面单。';
                }
            }

            Log::warning('AliExpress getLabel failed', [
                'order_id' => $order->ae_order_id,
                'logistic_order_ids' => $logisticOrderIds,
                'response' => $data,
            ]);

            return [
                'success' => false,
                'message' => $finalMessage,
                'used_ids' => $logisticOrderIds,
                'error_code' => $errorCode,
                'error_details' => $data['error']['details'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('AliExpress getLabel exception', [
                'order_id' => $order->ae_order_id,
                'logistic_order_ids' => $logisticOrderIds,
                'message' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function parseDate($dateStr)
    {
        if (!$dateStr) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($dateStr);
        } catch (\Exception $e) {
            return null;
        }
    }
}
