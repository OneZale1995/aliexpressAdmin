<?php

namespace App\Services;

use App\Jobs\RunOrderSkuEnrichmentTask;
use App\Jobs\RunShopProductDetailSyncJob;
use App\Models\AliCategory;
use App\Models\AliCategoryProperty;
use App\Models\AliCategoryPropertyValue;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AliExpressService
{
    private const SKU_ENRICH_LOCK_TTL_SECONDS = 600;

    protected string $baseUrl;
    protected bool $verifySsl;
    protected OrderLogisticsService $orderLogisticsService;
    protected AliExpressFbsMockService $fbsMockService;

    public function __construct()
    {
        $this->baseUrl = (string) config('services.aliexpress.base_url', 'https://openapi.aliexpress.ru');
        $this->verifySsl = (bool) config('services.aliexpress.verify_ssl', false);
        $this->orderLogisticsService = new OrderLogisticsService();
        $this->fbsMockService = new AliExpressFbsMockService();
    }

    protected function thirdPartyLog()
    {
        return Log::channel('third_party');
    }

    /**
     * 同步指定店铺的订单
     */
    public function syncOrders(Shop $shop, array $params = []): array
    {
        $token = $shop->access_token;
        if (!$token) {
            return ['synced' => 0, 'order_ids' => [], 'error' => '店铺未配置access_token'];
        }

        $page = 1;
        $pageSize = 20;
        $synced = 0;
        $syncedOrderIds = [];

        do {
            $body = array_merge([
                'page' => $page,
                'page_size' => $pageSize,
                'trade_order_info' => 'LogisticInfo',
                'sorting_order' => 'Desc',
            ], $params);

            $pageSuccess = false;
            $pageError = null;
            $maxPageRetries = 3;

            for ($pageRetry = 0; $pageRetry < $maxPageRetries; $pageRetry++) {
                try {
                    $response = Http::withHeaders([
                        'x-auth-token' => $token,
                        'x-request-locale' => 'en',
                        'Content-Type' => 'application/json',
                    ])
                        ->withOptions(['verify' => $this->verifySsl])
                        ->timeout(60)
                        ->retry(3, 1000, function (\Throwable $e) {
                            return $this->isTransientNetworkError($e);
                        })
                        ->post($this->baseUrl . '/seller-api/v1/order/get-order-list', $body);

                    $data = $response->json();

                    if (!$response->successful() || isset($data['error'])) {
                        $this->thirdPartyLog()->error('AliExpress API error', [
                            'shop_id' => $shop->id,
                            'status' => $response->status(),
                            'response' => $data,
                        ]);

                        $errorMsg = $data['error']['message'] ?? '';
                        if ($response->status() === 401 || str_contains($errorMsg, 'GetTokenByID') || str_contains($errorMsg, 'cannot GetToken')) {
                            $shop->update(['token_invalid_at' => now()]);
                        }

                        return ['synced' => $synced, 'order_ids' => $syncedOrderIds, 'error' => $errorMsg ?: 'API请求失败'];
                    }

                    $orders = $data['data']['orders'] ?? [];

                    foreach ($orders as $orderData) {
                        $order = $this->upsertOrder($shop, $orderData);
                        $syncedOrderIds[] = (int) $order->id;
                        $synced++;
                    }

                    $pageSuccess = true;
                    $pageError = null;
                    $page++;
                    break;
                } catch (\Exception $e) {
                    $pageError = $e->getMessage();
                    if ($pageRetry < $maxPageRetries - 1) {
                        usleep(($pageRetry + 1) * 3000000);
                    }
                }
            }

            if (!$pageSuccess) {
                $this->thirdPartyLog()->error('AliExpress sync exception', [
                    'shop_id' => $shop->id,
                    'page' => $page,
                    'message' => $pageError,
                ]);
                return ['synced' => $synced, 'order_ids' => $syncedOrderIds, 'error' => $pageError];
            }
        } while (count($orders ?? []) >= $pageSize);

        // 更新店铺的订单同步时间
        $shop->update(['order_updated_at' => now()]);

        return ['synced' => $synced, 'order_ids' => $syncedOrderIds, 'error' => null];
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

        $handoverListId = null;
        if (!empty($logisticOrders) && !empty($logisticOrders[0]['handover_list_id'])) {
            $handoverListId = (int) $logisticOrders[0]['handover_list_id'];
        }

        $handoverListStatus = !empty($logisticOrders) ? ($logisticOrders[0]['handover_list_status'] ?? null) : null;

        // 计算快递费(从pre_split_postings的delivery_fee)
        $shippingFee = 0;
        foreach ($preSplit as $posting) {
            $shippingFee += ($posting['delivery_fee'] ?? 0) / 100;
        }

        $existingOrder = Order::with('currentLogistics')->where('ae_order_id', $aeOrderId)->first();
        $resolvedLogisticsType = $logisticsType !== '' ? $logisticsType : ($existingOrder ? (string) $existingOrder->logistics_type : '');
        $resolvedTrackingNumber = $trackingNumber !== '' ? $trackingNumber : ($existingOrder ? (string) $existingOrder->tracking_number : '');
        $resolvedLogisticOrderId = $logisticOrderId ?: ($existingOrder ? $existingOrder->logistic_order_id : null);
        $resolvedHandoverListId = $handoverListId ?: ($existingOrder ? $existingOrder->handover_list_id : null);
        $resolvedHandoverListStatus = $handoverListStatus ?? ($existingOrder ? $existingOrder->handover_list_status : null);
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
                'logistics_type' => $resolvedLogisticsType,
                'tracking_number' => $resolvedTrackingNumber,
                'logistic_order_id' => $resolvedLogisticOrderId,
                'handover_list_id' => $resolvedHandoverListId,
                'handover_list_status' => $resolvedHandoverListStatus,
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

        if (!$existingOrder && empty($order->backend_status)) {
            $order->update(['backend_status' => 'wait_review']);
        }

        $logisticsPayload = [
            'payload' => [
                'aliexpress' => [
                    'logistic_orders' => $logisticOrders,
                ],
            ],
        ];

        if ($resolvedLogisticsType !== '') {
            $logisticsPayload['logistics_mode'] = $resolvedLogisticsType;
        }
        if (strtoupper($resolvedLogisticsType) === 'FBS') {
            $logisticsPayload['provider_code'] = 'aliexpress';
            $logisticsPayload['provider_name'] = 'AliExpress';
        }
        if ($resolvedTrackingNumber !== '') {
            $logisticsPayload['tracking_number'] = $resolvedTrackingNumber;
        }
        if ($resolvedLogisticOrderId) {
            $logisticsPayload['platform_logistic_order_id'] = (int) $resolvedLogisticOrderId;
        }
        if ($resolvedHandoverListId) {
            $logisticsPayload['handover_list_id'] = (int) $resolvedHandoverListId;
        }
        if ($resolvedHandoverListStatus !== null && $resolvedHandoverListStatus !== '') {
            $logisticsPayload['handover_list_status'] = $resolvedHandoverListStatus;
        }

        $this->orderLogisticsService->syncPrimary($order, $logisticsPayload);

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
                    $this->thirdPartyLog()->warning('AliExpress markShip failed', [
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
                $this->thirdPartyLog()->error('AliExpress markShip exception', [
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

        $this->thirdPartyLog()->info('AliExpress offlineShip toInTransit request', [
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
            $this->thirdPartyLog()->warning('AliExpress offlineShip toInTransit failed', [
                'order_id' => $order->ae_order_id,
                'response' => $data,
            ]);

            return ['success' => false, 'message' => $errorMsg, 'data' => $data];
        } catch (\Exception $e) {
            $this->thirdPartyLog()->error('AliExpress offlineShip toInTransit exception', [
                'order_id' => $order->ae_order_id,
                'message' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * DBS 线下发货 → 将订单状态变更为 ReadyForPickup
     * POST /api/v1/offline-ship/to-ready-for-pickup
     */
    public function offlineShipToReadyForPickup(Shop $shop, Order $order): array
    {
        $token = $shop->access_token;
        if (!$token) {
            return ['success' => false, 'message' => '店铺未配置access_token'];
        }

        $body = [
            'trade_order_id' => (int) $order->ae_order_id,
        ];

        $this->thirdPartyLog()->info('AliExpress offlineShip toReadyForPickup request', [
            'order_id' => $order->ae_order_id,
        ]);

        try {
            $response = Http::withHeaders([
                'x-auth-token' => $token,
                'x-request-locale' => 'en',
                'Content-Type' => 'application/json',
            ])
                ->withOptions(['verify' => $this->verifySsl])
                ->timeout(30)
                ->post($this->baseUrl . '/api/v1/offline-ship/to-ready-for-pickup', $body);

            $data = $response->json();

            if ($response->successful() && !isset($data['error'])) {
                return [
                    'success' => true,
                    'message' => 'DBS发货状态已更新为ReadyForPickup',
                    'data' => $data['data'] ?? $data,
                ];
            }

            $errorMsg = $data['error']['message'] ?? ($data['message'] ?? ('请求失败: ' . $response->status()));
            $this->thirdPartyLog()->warning('AliExpress offlineShip toReadyForPickup failed', [
                'order_id' => $order->ae_order_id,
                'response' => $data,
            ]);

            return ['success' => false, 'message' => $errorMsg, 'data' => $data];
        } catch (\Exception $e) {
            $this->thirdPartyLog()->error('AliExpress offlineShip toReadyForPickup exception', [
                'order_id' => $order->ae_order_id,
                'message' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * DBS 线下发货 → 将订单状态变更为 Delivered
     * POST /api/v1/offline-ship/to-delivered
     */
    public function offlineShipToDelivered(Shop $shop, Order $order): array
    {
        $token = $shop->access_token;
        if (!$token) {
            return ['success' => false, 'message' => '店铺未配置access_token'];
        }

        $body = [
            'trade_order_id' => (int) $order->ae_order_id,
        ];

        $this->thirdPartyLog()->info('AliExpress offlineShip toDelivered request', [
            'order_id' => $order->ae_order_id,
        ]);

        try {
            $response = Http::withHeaders([
                'x-auth-token' => $token,
                'x-request-locale' => 'en',
                'Content-Type' => 'application/json',
            ])
                ->withOptions(['verify' => $this->verifySsl])
                ->timeout(30)
                ->post($this->baseUrl . '/api/v1/offline-ship/to-delivered', $body);

            $data = $response->json();

            if ($response->successful() && !isset($data['error'])) {
                return [
                    'success' => true,
                    'message' => 'DBS发货状态已更新为Delivered',
                    'data' => $data['data'] ?? $data,
                ];
            }

            $errorMsg = $data['error']['message'] ?? ($data['message'] ?? ('请求失败: ' . $response->status()));
            $this->thirdPartyLog()->warning('AliExpress offlineShip toDelivered failed', [
                'order_id' => $order->ae_order_id,
                'response' => $data,
            ]);

            return ['success' => false, 'message' => $errorMsg, 'data' => $data];
        } catch (\Exception $e) {
            $this->thirdPartyLog()->error('AliExpress offlineShip toDelivered exception', [
                'order_id' => $order->ae_order_id,
                'message' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * FBS 发货（支持 mock）
     */
    public function shipFbs(Shop $shop, Order $order, array $options = []): array
    {
        $forceMock = (bool) ($options['use_mock'] ?? false);
        if ($this->isFbsMockEnabled($forceMock)) {
            return $this->fbsMockService->ship($order, $options);
        }

        $createResult = null;
        if (empty($order->logistic_order_id)) {
            $createResult = $this->createFbsLogisticOrder($shop, $order, $options);
            if (!$createResult['success']) {
                return $createResult;
            }
            if (!empty($createResult['data']['logistic_order_id'])) {
                $this->orderLogisticsService->syncPrimary($order, [
                    'logistics_mode' => 'FBS',
                    'provider_code' => 'aliexpress',
                    'provider_name' => 'AliExpress',
                    'platform_logistic_order_id' => (int) $createResult['data']['logistic_order_id'],
                ]);
            }
        }

        $fbsInfo = null;
        if (!empty($order->logistic_order_id)) {
            $fbsInfoResult = $this->getFbsLogisticOrderInfo($shop, (int) $order->logistic_order_id);
            if ($fbsInfoResult['success']) {
                $fbsInfo = $fbsInfoResult['data'];
            }
        }

        return [
            'success' => true,
            'message' => 'FBS 发货单创建成功，运单号由平台分配',
            'data' => [
                'mock' => false,
                'create_logistic_order' => $createResult['data'] ?? null,
                'fbs_info' => $fbsInfo,
                'tracking_number' => $fbsInfo['platform_tracking_code'] ?? null,
            ],
        ];
    }

    /**
     * 创建 FBS 发货单
     */
    public function createFbsLogisticOrder(Shop $shop, Order $order, array $options = []): array
    {
        $length = (int) ($options['total_length'] ?? config('services.aliexpress.fbs_default_length', 20));
        $width = (int) ($options['total_width'] ?? config('services.aliexpress.fbs_default_width', 10));
        $height = (int) ($options['total_height'] ?? config('services.aliexpress.fbs_default_height', 5));
        $weight = (float) ($options['total_weight'] ?? config('services.aliexpress.fbs_default_weight', 0.5));

        $sourceBySku = $this->extractFbsProductSourceBySku($order);

        $lines = $this->buildFbsLogisticLines($order, $options, $sourceBySku);

        if (empty($lines)) {
            return ['success' => false, 'message' => '无法创建发货单：订单行缺少 sku_id'];
        }

        if ($this->isFbsMockEnabled((bool) ($options['use_mock'] ?? false))) {
            return $this->fbsMockService->createLogisticOrder($order, array_merge($options, [
                'total_length' => max(1, $length),
                'total_width' => max(1, $width),
                'total_height' => max(1, $height),
                'total_weight' => max(0.01, $weight),
                'items' => $lines,
            ]));
        }

        $token = $shop->access_token;
        if (!$token) {
            return ['success' => false, 'message' => '店铺未配置access_token'];
        }

        $body = [
            'orders' => [[
                'trade_order_id' => (int) $order->ae_order_id,
                'total_length' => max(1, $length),
                'total_width' => max(1, $width),
                'total_height' => max(1, $height),
                'total_weight' => max(0.01, $weight),
                'undeliverable_option' => (string) ($options['undeliverable_option'] ?? 'Return'),
                'danger_type' => (string) ($options['danger_type'] ?? 'General'),
                'items' => $lines,
            ]],
        ];

        try {
            $response = Http::withHeaders([
                'x-auth-token' => $token,
                'x-request-locale' => 'en',
                'Content-Type' => 'application/json',
                'accept' => 'application/json',
            ])
                ->withOptions(['verify' => $this->verifySsl])
                ->timeout(30)
                ->post($this->baseUrl . '/seller-api/v1/logistic-order/create', $body);

            $data = $response->json();
            if (!$response->successful() || isset($data['error'])) {
                $msg = $data['error']['message'] ?? ('创建FBS发货单失败: ' . $response->status());
                $this->thirdPartyLog()->warning('AliExpress createFbsLogisticOrder failed', [
                    'order_id' => $order->ae_order_id,
                    'status' => $response->status(),
                    'response' => $data,
                ]);
                return ['success' => false, 'message' => $msg, 'data' => $data];
            }

            $logisticOrderId = (int) (($data['data']['orders'][0]['logistic_orders'][0]['id'] ?? 0));
            $createErrors = is_array(data_get($data, 'data.errors')) ? data_get($data, 'data.errors') : [];

            if (!empty($createErrors) || $logisticOrderId <= 0) {
                $message = $this->extractFbsCreateLogisticOrderErrorMessage($data);
                if ($message === '') {
                    $message = $logisticOrderId <= 0
                        ? '创建FBS发货单失败：平台未返回发货单ID'
                        : '创建FBS发货单失败';
                }

                $this->thirdPartyLog()->warning('AliExpress createFbsLogisticOrder returned invalid payload', [
                    'order_id' => $order->ae_order_id,
                    'status' => $response->status(),
                    'response' => $data,
                ]);

                return [
                    'success' => false,
                    'message' => $message,
                    'data' => $data['data'] ?? null,
                    'raw' => $data,
                    'error_details' => $createErrors,
                ];
            }

            $this->orderLogisticsService->syncPrimary($order, [
                'logistics_mode' => 'FBS',
                'provider_code' => 'aliexpress',
                'provider_name' => 'AliExpress',
                'platform_logistic_order_id' => $logisticOrderId ?: null,
                'payload' => [
                    'fbs' => [
                        'create_logistic_order' => $data['data']['orders'][0]['logistic_orders'][0] ?? null,
                    ],
                ],
            ]);

            return [
                'success' => true,
                'message' => 'FBS 发货单创建成功',
                'data' => [
                    'logistic_order_id' => $logisticOrderId,
                    'trade_order_id' => (int) $order->ae_order_id,
                    'orders' => $data['data']['orders'] ?? [],
                    'raw' => $data,
                ],
            ];
        } catch (\Throwable $e) {
            $this->thirdPartyLog()->error('AliExpress createFbsLogisticOrder exception', [
                'order_id' => $order->ae_order_id,
                'message' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function isFbsMockEnabled(bool $forceMock = false): bool
    {
        return $forceMock || (bool) config('services.aliexpress.fbs_mock', false);
    }

    /**
     * 创建并打印交接单（transfer sheet）
     *
     * 注意：AliExpress 不同区域接口字段可能不同，接口路径通过配置项注入。
     */
    public function createAndPrintTransferSheet(Shop $shop, array $logisticOrderIds): array
    {
        $createResult = $this->createFbsHandoverList($shop, $logisticOrderIds);
        if (!$createResult['success']) {
            return $createResult;
        }

        $handoverListId = (int) ($createResult['data']['handover_list_id'] ?? 0);
        if ($handoverListId <= 0) {
            return ['success' => false, 'message' => '创建交接单成功，但未返回交接单ID', 'data' => $createResult['data'] ?? null];
        }

        $printResult = $this->printFbsHandoverList($shop, $handoverListId);
        if (!$printResult['success']) {
            return [
                'success' => true,
                'message' => '交接单已创建，但打印链接获取失败: ' . ($printResult['message'] ?? '未知错误'),
                'data' => array_merge($createResult['data'] ?? [], [
                    'label_url' => null,
                    'print_error' => $printResult['message'] ?? null,
                ]),
            ];
        }

        return [
            'success' => true,
            'message' => '交接单创建成功',
            'data' => array_merge($createResult['data'] ?? [], [
                'label_url' => $printResult['data']['label_url'] ?? null,
                'print_raw' => $printResult['data']['raw'] ?? null,
            ]),
        ];
    }

    public function getFbsWorkflow(Shop $shop, Order $order): array
    {
        if ($this->isFbsMockEnabled()) {
            return $this->fbsMockService->getWorkflow($order);
        }

        $logisticOrderId = $this->extractPrimaryLogisticOrderId($order);
        $handoverListId = (int) ($order->handover_list_id ?: 0);

        if ($logisticOrderId > 0 && (int) $order->logistic_order_id !== $logisticOrderId) {
            $this->orderLogisticsService->syncPrimary($order, [
                'logistics_mode' => 'FBS',
                'provider_code' => 'aliexpress',
                'provider_name' => 'AliExpress',
                'platform_logistic_order_id' => $logisticOrderId,
            ]);
        }

        $logisticOrder = null;
        if ($logisticOrderId > 0) {
            $logisticResult = $this->getFbsLogisticOrderInfo($shop, $logisticOrderId);
            if (!$logisticResult['success']) {
                return $logisticResult;
            }
            $logisticOrder = $logisticResult['data'];
            $handoverListId = (int) ($logisticOrder['handover_list_id'] ?? $handoverListId);
            $this->orderLogisticsService->syncPrimary($order, [
                'logistics_mode' => 'FBS',
                'provider_code' => 'aliexpress',
                'provider_name' => 'AliExpress',
                'platform_logistic_order_id' => $logisticOrderId > 0 ? $logisticOrderId : null,
                'handover_list_id' => $handoverListId > 0 ? $handoverListId : null,
                'tracking_number' => $logisticOrder['platform_tracking_code'] ?? $order->tracking_number,
                'logistic_status' => $logisticOrder['status'] ?? null,
                'payload' => [
                    'fbs' => [
                        'logistic_order' => $logisticOrder,
                    ],
                ],
            ]);
        }

        $handoverList = null;
        if ($handoverListId > 0) {
            $handoverResult = $this->getFbsHandoverListInfo($shop, $handoverListId);
            if (!$handoverResult['success']) {
                return $handoverResult;
            }
            $handoverList = $handoverResult['data'];
            $this->orderLogisticsService->syncPrimary($order, [
                'logistics_mode' => 'FBS',
                'provider_code' => 'aliexpress',
                'provider_name' => 'AliExpress',
                'handover_list_id' => $handoverListId,
                'handover_list_status' => $handoverList['status'] ?? null,
                'payload' => [
                    'fbs' => [
                        'handover_list' => $handoverList,
                    ],
                ],
            ]);
        }

        return [
            'success' => true,
            'message' => 'ok',
            'data' => [
                'order_id' => $order->id,
                'trade_order_id' => (int) $order->ae_order_id,
                'logistic_order_id' => $logisticOrderId > 0 ? $logisticOrderId : null,
                'handover_list_id' => $handoverListId > 0 ? $handoverListId : null,
                'tracking_number' => $order->tracking_number,
                'logistic_order' => $logisticOrder,
                'handover_list' => $handoverList,
            ],
        ];
    }

    public function createFbsHandoverList(Shop $shop, array $logisticOrderIds, ?string $arrivalDate = null): array
    {
        $ids = collect($logisticOrderIds)
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->toArray();

        if (empty($ids)) {
            return ['success' => false, 'message' => '缺少 logistic_order_id，无法创建交接单'];
        }

        if ($this->isFbsMockEnabled()) {
            $order = $this->findOrderByFbsLogisticOrderIds($ids);
            if (!$order) {
                return ['success' => false, 'message' => '未找到对应订单，无法创建交接清单'];
            }

            return $this->fbsMockService->createHandoverList($order, $ids, $arrivalDate);
        }

        $payload = ['logistic_order_ids' => $ids];
        if (!empty($arrivalDate)) {
            $payload['arrival_date'] = $arrivalDate;
        }

        $result = $this->requestSellerApi($shop, '/seller-api/v1/handover-list/create', $payload, '创建交接清单失败', [
            'logistic_order_ids' => $ids,
        ]);

        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'message' => '交接清单创建成功',
            'data' => [
                'handover_list_id' => $result['data']['handover_list_id'] ?? null,
                'arrival_date' => $payload['arrival_date'] ?? null,
                'raw' => $result['raw'] ?? null,
            ],
        ];
    }

    public function getFbsHandoverListInfo(Shop $shop, int $handoverListId): array
    {
        if ($handoverListId <= 0) {
            return ['success' => false, 'message' => '缺少 handover_list_id'];
        }

        if ($this->isFbsMockEnabled()) {
            $order = $this->findOrderByHandoverListId($handoverListId);
            if (!$order) {
                return ['success' => false, 'message' => '未查询到交接清单信息'];
            }

            return $this->fbsMockService->getHandoverListInfo($order, $handoverListId);
        }

        $result = $this->requestSellerApi($shop, '/seller-api/v1/handover-list/get-by-filter', [
            'handover_list_ids' => [$handoverListId],
            'page_size' => 1,
            'page_number' => 1,
        ], '查询交接清单失败', [
            'handover_list_id' => $handoverListId,
        ]);

        if (!$result['success']) {
            return $result;
        }

        $handoverList = $result['data']['data_source'][0] ?? null;
        if (!$handoverList) {
            return ['success' => false, 'message' => '未查询到交接清单信息', 'data' => $result['data'] ?? null];
        }

        return ['success' => true, 'message' => 'ok', 'data' => $handoverList, 'raw' => $result['raw'] ?? null];
    }

    public function addFbsLogisticOrdersToHandoverList(Shop $shop, int $handoverListId, array $logisticOrderIds): array
    {
        if ($this->isFbsMockEnabled()) {
            $order = $this->findOrderByFbsLogisticOrderIds($logisticOrderIds) ?: $this->findOrderByHandoverListId($handoverListId);
            if (!$order) {
                return ['success' => false, 'message' => '未找到对应订单，无法添加到交接清单'];
            }

            return $this->fbsMockService->addLogisticOrdersToHandoverList($order, $handoverListId, $logisticOrderIds);
        }

        return $this->requestSellerApi($shop, '/seller-api/v1/handover-list/add-logistic-orders', [
            'handover_list_id' => $handoverListId,
            'order_ids' => array_values(array_unique(array_map('intval', $logisticOrderIds))),
        ], '添加发货单到交接清单失败', [
            'handover_list_id' => $handoverListId,
            'logistic_order_ids' => $logisticOrderIds,
        ]);
    }

    public function removeFbsLogisticOrdersFromHandoverList(Shop $shop, int $handoverListId, array $logisticOrderIds): array
    {
        if ($this->isFbsMockEnabled()) {
            $order = $this->findOrderByHandoverListId($handoverListId) ?: $this->findOrderByFbsLogisticOrderIds($logisticOrderIds);
            if (!$order) {
                return ['success' => false, 'message' => '未找到对应订单，无法从交接清单移除'];
            }

            return $this->fbsMockService->removeLogisticOrdersFromHandoverList($order, $handoverListId, $logisticOrderIds);
        }

        return $this->requestSellerApi($shop, '/seller-api/v1/handover-list/remove-logistic-orders', [
            'handover_list_id' => $handoverListId,
            'order_ids' => array_values(array_unique(array_map('intval', $logisticOrderIds))),
        ], '从交接清单移除发货单失败', [
            'handover_list_id' => $handoverListId,
            'logistic_order_ids' => $logisticOrderIds,
        ]);
    }

    public function printFbsHandoverList(Shop $shop, int $handoverListId): array
    {
        if ($handoverListId <= 0) {
            return ['success' => false, 'message' => '缺少 handover_list_id'];
        }

        if ($this->isFbsMockEnabled()) {
            $order = $this->findOrderByHandoverListId($handoverListId);
            if (!$order) {
                return ['success' => false, 'message' => '未查询到交接清单信息'];
            }

            return $this->fbsMockService->printHandoverList($order, $handoverListId);
        }

        $result = $this->requestSellerApi($shop, '/seller-api/v1/labels/handover-lists/get', [
            'handover_list_id' => $handoverListId,
        ], '打印交接清单失败', [
            'handover_list_id' => $handoverListId,
        ]);

        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'message' => '交接清单标签获取成功',
            'data' => [
                'label_url' => $result['data']['label_url'] ?? null,
                'raw' => $result['raw'] ?? null,
            ],
        ];
    }

    public function readyFbsHandoverForPickup(Shop $shop, int $handoverListId): array
    {
        if ($handoverListId <= 0) {
            return ['success' => false, 'message' => '缺少 handover_list_id'];
        }

        if ($this->isFbsMockEnabled()) {
            $order = $this->findOrderByHandoverListId($handoverListId);
            if (!$order) {
                return ['success' => false, 'message' => '未查询到交接清单信息'];
            }

            return $this->fbsMockService->readyForPickup($order, $handoverListId);
        }

        return $this->requestSellerApi($shop, '/seller-api/v1/handover-list/ready-for-pickup', [
            'handover_list_id' => $handoverListId,
        ], '交接清单标记待揽收失败', [
            'handover_list_id' => $handoverListId,
        ]);
    }

    public function setBigBagCountForHandoverList(Shop $shop, int $handoverListId, int $bigBagCount): array
    {
        if ($handoverListId <= 0) {
            return ['success' => false, 'message' => '缺少 handover_list_id'];
        }
        if ($bigBagCount <= 0) {
            return ['success' => false, 'message' => '大袋数量必须大于 0'];
        }

        if ($this->isFbsMockEnabled()) {
            $order = $this->findOrderByHandoverListId($handoverListId);
            if (!$order) {
                return ['success' => false, 'message' => '未查询到交接清单信息'];
            }

            return $this->fbsMockService->setBigBagCount($order, $handoverListId, $bigBagCount);
        }

        return $this->requestSellerApi($shop, '/seller-api/v1/handover-list/set-big-bag-count', [
            'handover_list_id' => $handoverListId,
            'big_bag_count'    => $bigBagCount,
        ], '设置大袋数量失败', [
            'handover_list_id' => $handoverListId,
            'big_bag_count'    => $bigBagCount,
        ]);
    }

    public function transferFbsHandoverList(Shop $shop, int $handoverListId): array
    {
        if ($handoverListId <= 0) {
            return ['success' => false, 'message' => '缺少 handover_list_id'];
        }

        if ($this->isFbsMockEnabled()) {
            $order = $this->findOrderByHandoverListId($handoverListId);
            if (!$order) {
                return ['success' => false, 'message' => '未查询到交接清单信息'];
            }

            return $this->fbsMockService->transferHandoverList($order, $handoverListId);
        }

        return $this->requestSellerApi($shop, '/seller-api/v1/handover-list/transfer', [
            'handover_list_id' => $handoverListId,
        ], '关闭交接清单失败', [
            'handover_list_id' => $handoverListId,
        ]);
    }

    /**
     * 查询单个 FBS 发货单信息
     */
    public function getFbsLogisticOrderInfo(Shop $shop, int $logisticOrderId): array
    {
        if ($this->isFbsMockEnabled()) {
            $order = $this->findOrderByPlatformLogisticOrderId($logisticOrderId);
            if (!$order) {
                return ['success' => false, 'message' => '未查询到发货单信息'];
            }

            return $this->fbsMockService->getLogisticOrderInfo($order, $logisticOrderId);
        }

        $token = $shop->access_token;
        if (!$token) {
            return ['success' => false, 'message' => '店铺未配置access_token'];
        }

        $result = $this->requestSellerApi($shop, '/seller-api/v1/logistic-order/get', [
            'logistic_order_ids' => [$logisticOrderId],
            'page_size' => 1,
            'page' => 1,
            'logistic_order_info' => 'All',
        ], '查询发货单失败', [
            'logistic_order_id' => $logisticOrderId,
        ]);

        if (!$result['success']) {
            return $result;
        }

        $info = $result['data']['logistic_orders'][0] ?? null;
        if (!$info) {
            return ['success' => false, 'message' => '未查询到发货单信息', 'data' => $result['data'] ?? null];
        }

        return ['success' => true, 'message' => 'ok', 'data' => $info, 'raw' => $result['raw'] ?? null];
    }

    /**
     * 获取面单标签（返回 PDF URL 或 base64）
     */
    public function getShippingLabel(Shop $shop, Order $order): array
    {
        if ($this->isFbsMockEnabled()) {
            return $this->fbsMockService->getShippingLabel($order);
        }

        $token = $shop->access_token;
        if (!$token) {
            return ['success' => false, 'message' => '店铺未配置access_token'];
        }

        $rawData = $order->raw_data ?? [];
        $logisticOrderIds = $this->extractFbsLogisticOrderIds($order);

        if (empty($logisticOrderIds)) {
            return ['success' => false, 'message' => '未找到logistic_order_id，请先创建货件并同步订单后再打印'];
        }

        $this->thirdPartyLog()->info('AliExpress getLabel request payload', [
            'order_id' => $order->ae_order_id,
            'logistic_order_ids' => $logisticOrderIds,
        ]);

        try {
            $result = $this->requestSellerApi($shop, '/seller-api/v1/labels/orders/get', [
                'logistic_order_ids' => $logisticOrderIds,
            ], '面单请求失败', [
                'order_id' => $order->ae_order_id,
                'logistic_order_ids' => $logisticOrderIds,
            ]);

            if ($result['success'] && !empty($result['data']['label_url'])) {
                return [
                    'success' => true,
                    'message' => '面单获取成功',
                    'label_url' => $result['data']['label_url'],
                    'used_ids' => $logisticOrderIds,
                    'raw' => $result['data'] ?? [],
                ];
            }

            $errorCode = $result['error_code'] ?? null;
            $errorMessage = $result['message'] ?? '面单请求失败';
            $finalMessage = $errorCode ? ($errorCode . ': ' . $errorMessage) : $errorMessage;

            if ($errorCode === 'LogisticProviderNotFound') {
                $logisticMethod = $rawData['pre_split_postings'][0]['logistic_method'] ?? '';
                if (str_starts_with((string) $logisticMethod, 'OTHER_')) {
                    $finalMessage = '当前物流方式为 ' . $logisticMethod . '（第三方/其他物流），速卖通平台不提供API面单打印，请在线下渠道打印。';
                } else {
                    $finalMessage = '物流服务商不支持面单API打印（' . $errorCode . '），请确认该物流方式是否支持平台面单。';
                }
            }

            $this->thirdPartyLog()->warning('AliExpress getLabel failed', [
                'order_id' => $order->ae_order_id,
                'logistic_order_ids' => $logisticOrderIds,
                'response' => $result['raw'] ?? $result['data'] ?? null,
            ]);

            return [
                'success' => false,
                'message' => $finalMessage,
                'used_ids' => $logisticOrderIds,
                'error_code' => $errorCode,
                'error_details' => $result['error_details'] ?? null,
            ];
        } catch (\Exception $e) {
            $this->thirdPartyLog()->error('AliExpress getLabel exception', [
                'order_id' => $order->ae_order_id,
                'logistic_order_ids' => $logisticOrderIds,
                'message' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function buildFbsLogisticLines(Order $order, array $options, array $sourceBySku): array
    {
        $manualItems = is_array($options['items'] ?? null) ? $options['items'] : [];
        if (!empty($manualItems)) {
            return collect($manualItems)
                ->map(function ($item) use ($sourceBySku) {
                    if (!is_array($item)) {
                        return null;
                    }

                    $skuId = (int) ($item['sku_id'] ?? 0);
                    $quantity = (int) ($item['quantity'] ?? 0);
                    if ($skuId <= 0 || $quantity <= 0) {
                        return null;
                    }

                    $line = [
                        'quantity' => $quantity,
                        'sku_id' => $skuId,
                    ];

                    $sourceKey = (string) $skuId;
                    if (array_key_exists($sourceKey, $sourceBySku)) {
                        $line['product_source_id'] = $sourceBySku[$sourceKey];
                    } elseif (array_key_exists('product_source_id', $item) && $item['product_source_id'] !== '' && $item['product_source_id'] !== null) {
                        $line['product_source_id'] = (int) $item['product_source_id'];
                    }

                    return $line;
                })
                ->filter()
                ->values()
                ->toArray();
        }

        $lines = [];
        $items = $order->relationLoaded('items') ? $order->items : $order->items()->get();
        foreach ($items as $item) {
            if (empty($item->ae_sku_id)) {
                continue;
            }

            $skuId = (int) $item->ae_sku_id;
            $line = [
                'quantity' => (int) ($item->quantity ?: 1),
                'sku_id' => $skuId,
            ];

            $sourceKey = (string) $item->ae_sku_id;
            if (isset($sourceBySku[$sourceKey])) {
                $line['product_source_id'] = $sourceBySku[$sourceKey];
            }

            $lines[] = $line;
        }

        return $lines;
    }

    protected function extractFbsProductSourceBySku(Order $order): array
    {
        $rawData = $order->raw_data ?? [];
        $rawLines = is_array($rawData['order_lines'] ?? null) ? $rawData['order_lines'] : [];
        $sourceBySku = [];

        foreach ($rawLines as $line) {
            $skuId = (string) ($line['sku_id'] ?? '');
            $productSourceId = $line['product_source_id'] ?? null;
            if ($skuId !== '' && $productSourceId !== null) {
                $sourceBySku[$skuId] = (int) $productSourceId;
            }
        }

        $preSplitPostings = is_array($rawData['pre_split_postings'] ?? null) ? $rawData['pre_split_postings'] : [];
        foreach ($preSplitPostings as $posting) {
            $postingLines = is_array($posting['posting_lines'] ?? null) ? $posting['posting_lines'] : [];
            foreach ($postingLines as $line) {
                $skuId = (string) ($line['sku_id'] ?? '');
                $productSourceId = $line['product_source_id'] ?? null;
                if ($skuId !== '' && $productSourceId !== null && !array_key_exists($skuId, $sourceBySku)) {
                    $sourceBySku[$skuId] = (int) $productSourceId;
                }
            }
        }

        return $sourceBySku;
    }

    protected function extractPrimaryLogisticOrderId(Order $order): int
    {
        $ids = $this->extractFbsLogisticOrderIds($order);
        return !empty($ids) ? (int) $ids[0] : 0;
    }

    protected function extractFbsCreateLogisticOrderErrorMessage(array $response): string
    {
        $messages = $this->collectAliExpressNestedMessages(data_get($response, 'data.errors'));
        if (!empty($messages)) {
            return '创建FBS发货单失败：' . implode('；', array_slice($messages, 0, 3));
        }

        $message = trim((string) ($response['message'] ?? ''));
        return $message;
    }

    protected function collectAliExpressNestedMessages($value): array
    {
        if (is_string($value)) {
            $message = trim($value);
            return $message !== '' ? [$message] : [];
        }

        if (!is_array($value)) {
            return [];
        }

        $messages = [];
        foreach ($value as $item) {
            $messages = array_merge($messages, $this->collectAliExpressNestedMessages($item));
        }

        return array_values(array_unique($messages));
    }

    protected function extractFbsLogisticOrderIds(Order $order): array
    {
        $ids = [];
        if (!empty($order->logistic_order_id)) {
            $ids[] = (int) $order->logistic_order_id;
        }

        $rawData = $order->raw_data ?? [];
        $logisticOrders = is_array($rawData['logistic_orders'] ?? null) ? $rawData['logistic_orders'] : [];
        foreach ($logisticOrders as $item) {
            if (!is_array($item) || !array_key_exists('id', $item)) {
                continue;
            }
            $ids[] = (int) $item['id'];
        }

        return collect($ids)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->toArray();
    }

    protected function findOrderByPlatformLogisticOrderId(int $logisticOrderId): ?Order
    {
        if ($logisticOrderId <= 0) {
            return null;
        }

        return Order::with(['shop', 'items', 'currentLogistics'])
            ->whereHas('currentLogistics', function ($query) use ($logisticOrderId) {
                $query->where('platform_logistic_order_id', $logisticOrderId);
            })
            ->first();
    }

    protected function findOrderByFbsLogisticOrderIds(array $logisticOrderIds): ?Order
    {
        $ids = array_values(array_filter(array_map('intval', $logisticOrderIds), fn($id) => $id > 0));
        if (empty($ids)) {
            return null;
        }

        return Order::with(['shop', 'items', 'currentLogistics'])
            ->whereHas('currentLogistics', function ($query) use ($ids) {
                $query->whereIn('platform_logistic_order_id', $ids);
            })
            ->first();
    }

    protected function findOrderByHandoverListId(int $handoverListId): ?Order
    {
        if ($handoverListId <= 0) {
            return null;
        }

        return Order::with(['shop', 'items', 'currentLogistics'])
            ->whereHas('currentLogistics', function ($query) use ($handoverListId) {
                $query->where('handover_list_id', $handoverListId);
            })
            ->first();
    }

    protected function requestSellerApi(Shop $shop, string $path, array $body, string $defaultErrorMessage, array $logContext = []): array
    {
        $token = $shop->access_token;
        if (!$token) {
            return ['success' => false, 'message' => '店铺未配置access_token'];
        }

        try {
            $response = Http::withHeaders([
                'accept' => 'application/json',
                'x-auth-token' => $token,
                'x-request-locale' => 'en',
                'Content-Type' => 'application/json',
            ])
                ->withOptions(['verify' => $this->verifySsl])
                ->timeout(30)
                ->post($this->baseUrl . $path, $body);

            $data = $response->json();
            if (!$response->successful() || isset($data['error'])) {
                $message = $data['error']['message'] ?? $data['message'] ?? ($defaultErrorMessage . ': ' . $response->status());
                $this->thirdPartyLog()->warning('AliExpress seller api failed', array_merge($logContext, [
                    'path' => $path,
                    'status' => $response->status(),
                    'response' => $data,
                ]));

                return [
                    'success' => false,
                    'message' => $message,
                    'data' => $data['data'] ?? null,
                    'raw' => $data,
                    'error_code' => $data['error']['code'] ?? null,
                    'error_details' => $data['error']['details'] ?? null,
                ];
            }

            return [
                'success' => true,
                'message' => 'ok',
                'data' => $data['data'] ?? [],
                'raw' => $data,
            ];
        } catch (\Throwable $e) {
            $this->thirdPartyLog()->error('AliExpress seller api exception', array_merge($logContext, [
                'path' => $path,
                'message' => $e->getMessage(),
            ]));

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function parseDate($dateStr)
    {
        if (!$dateStr) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($dateStr)->setTimezone(config('app.timezone'));
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getProductDetail(Shop $shop, string $productId, bool $skipCategorySync = false): ?array
    {
        $token = $shop->access_token;
        if (!$token) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'x-auth-token' => $token,
                'Content-Type' => 'application/json',
            ])
                ->withOptions(['verify' => $this->verifySsl])
                ->connectTimeout(5)
                ->timeout(12)
                ->retry(1, 500, function (\Throwable $e) {
                    return $this->isTransientNetworkError($e);
                })
                ->post($this->baseUrl . '/api/v1/product/get-seller-product', [
                    'product_id' => $productId,
                ]);

            $data = $response->json();
            if (!$response->successful() || isset($data['error'])) {
                $this->thirdPartyLog()->warning('AliExpress getProductDetail failed', [
                    'product_id' => $productId,
                    'response'   => $data,
                ]);

                $errorMsg = $data['error']['message'] ?? '';
                if ($response->status() === 401 || str_contains($errorMsg, 'GetTokenByID') || str_contains($errorMsg, 'cannot GetToken')) {
                    $shop->update(['token_invalid_at' => now()]);
                }

                return null;
            }

            $detail = $data['data'] ?? null;
            if (is_array($detail)) {
                $this->upsertProduct($shop, $detail, $skipCategorySync);
            }

            return $detail;
        } catch (\Exception $e) {
            $this->thirdPartyLog()->error('AliExpress getProductDetail exception', [
                'product_id' => $productId,
                'message'    => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function syncShopProductPage(Shop $shop, ?string $lastId = null, int $limit = 50): array
    {
        $token = $shop->access_token;
        if (!$token) {
            return [
                'success' => false,
                'processed' => 0,
                'detail_product_ids' => [],
                'has_more' => false,
                'next_last_id' => null,
                'error' => '店铺未配置access_token',
            ];
        }

        $payload = ['limit' => $limit];
        if ($lastId !== null && $lastId !== '') {
            $payload['last_product_id'] = $lastId;
        }

        try {
            $response = Http::withHeaders([
                'x-auth-token' => $token,
                'Content-Type' => 'application/json',
                'x-request-locale' => 'en',
            ])
                ->withOptions(['verify' => $this->verifySsl])
                ->timeout(30)
                ->retry(2, 2000, function (\Throwable $e) {
                    return $this->isTransientNetworkError($e);
                })
                ->post($this->baseUrl . '/api/v1/scroll-short-product-by-filter', $payload);

            $data = $response->json();
            if (!$response->successful() || ($data['error'] ?? null)) {
                $this->thirdPartyLog()->warning('AliExpress syncShopProductPage failed', [
                    'shop_id' => $shop->id,
                    'last_id' => $lastId,
                    'response' => $data,
                ]);

                $errorMsg = $data['error']['message'] ?? '';
                if ($response->status() === 401 || str_contains($errorMsg, 'GetTokenByID') || str_contains($errorMsg, 'cannot GetToken')) {
                    $shop->update(['token_invalid_at' => now()]);
                }

                return [
                    'success' => false,
                    'processed' => 0,
                    'detail_product_ids' => [],
                    'has_more' => false,
                    'next_last_id' => null,
                ];
            }

            $products = is_array($data['data'] ?? null) ? $data['data'] : [];
            if (empty($products)) {
                return [
                    'success' => true,
                    'processed' => 0,
                    'detail_product_ids' => [],
                    'has_more' => false,
                    'next_last_id' => null,
                ];
            }

            $productIds = array_values(array_filter(array_map(function ($product) {
                return (string) ($product['id'] ?? '');
            }, $products)));

            $existingProducts = Product::query()
                ->where('shop_id', $shop->id)
                ->whereIn('ae_item_id', $productIds)
                ->get()
                ->keyBy('ae_item_id');

            $detailProductIds = [];
            foreach ($products as $productData) {
                $aeItemId = (string) ($productData['id'] ?? '');
                if ($aeItemId === '') {
                    continue;
                }

                $existing = $existingProducts->get($aeItemId);
                if ($this->shouldSyncProductDetail($existing, $productData)) {
                    $detailProductIds[] = $aeItemId;
                }

                $this->upsertProductFromShortData($shop, $productData);
            }

            $lastProduct = end($products);
            $nextLastId = $lastProduct ? (string) ($lastProduct['id'] ?? '') : '';

            return [
                'success' => true,
                'processed' => count($products),
                'detail_product_ids' => array_values(array_unique($detailProductIds)),
                'has_more' => count($products) >= $limit && $nextLastId !== '',
                'next_last_id' => $nextLastId !== '' ? $nextLastId : null,
            ];
        } catch (\Throwable $e) {
            $this->thirdPartyLog()->error('AliExpress syncShopProductPage exception', [
                'shop_id' => $shop->id,
                'last_id' => $lastId,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'processed' => 0,
                'detail_product_ids' => [],
                'has_more' => false,
                'next_last_id' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function enrichOrderItemSkuAttributes(Shop $shop, Order $order, bool $force = false): int
    {
        $itemsQuery = $order->items();
        if (!$force) {
            $itemsQuery->whereNull('sku_attributes');
        }
        $items = $itemsQuery->get();
        if ($items->isEmpty()) {
            return 0;
        }

        $token = $shop->access_token;
        if (!$token) {
            return 0;
        }

        $enriched = 0;
        $productIds = $items->pluck('ae_item_id')->unique()->filter()->map(fn($id) => (string) $id)->values()->toArray();

        foreach ($productIds as $productId) {
            $localProduct = Product::where('shop_id', $shop->id)
                ->where('ae_item_id', $productId)
                ->whereNotNull('skus')
                ->first();

            if (!$localProduct || empty($localProduct->skus)) {
                RunShopProductDetailSyncJob::dispatch((int) $shop->id, $productId)->onQueue('products');
                continue;
            }

            $hasFullPropertyData = false;
            foreach ($localProduct->skus as $sku) {
                foreach ($sku['property'] ?? [] as $prop) {
                    if (!empty($prop['name_id']) && !empty($prop['value_id'])) {
                        $hasFullPropertyData = true;
                        break 2;
                    }
                }
            }

            if (!$hasFullPropertyData) {
                RunShopProductDetailSyncJob::dispatch((int) $shop->id, $productId)->onQueue('products');
                continue;
            }

            $categoryId = (string) ($localProduct->category_id ?? '');
            $skuPropertyNames = $categoryId !== '' ? $this->getCategorySkuPropertyNames($token, $categoryId) : [];

            $skuMap = [];
            foreach ($localProduct->skus as $sku) {
                $skuId = (string) ($sku['sku_id'] ?? '');
                if (!$skuId) {
                    continue;
                }

                $attrs = [];
                foreach ($sku['property'] ?? [] as $prop) {
                    $nameId  = $prop['name_id'] ?? '';
                    $valueId = $prop['value_id'] ?? '';
                    if (!$nameId || !$valueId) {
                        continue;
                    }
                    if (!isset($skuPropertyNames[$nameId])) {
                        continue;
                    }

                    $propName  = $skuPropertyNames[$nameId]['name'];
                    $propValue = $prop['value'] ?? '';

                    if (!$propValue || str_starts_with($propValue, 'NO_NAME_')) {
                        $propValue = $categoryId !== ''
                            ? $this->getCategoryPropertyValue($token, $categoryId, $nameId, $valueId)
                            : '';
                    }

                    if ($propValue) {
                        $attrs[$propName] = $propValue;
                    }
                }
                $skuMap[$skuId] = $attrs;
            }

            $relatedItems = $items->where('ae_item_id', $productId);
            foreach ($relatedItems as $item) {
                $skuId = (string) ($item->ae_sku_id ?? '');
                if ($skuId !== '' && array_key_exists($skuId, $skuMap)) {
                    $attrs = $skuMap[$skuId];
                    $item->update(['sku_attributes' => $attrs]);
                    if (!empty($attrs)) {
                        $enriched++;
                    }
                }
            }
        }

        return $enriched;
    }

    protected function getCategorySkuPropertyNames(string $token, string $categoryId): array
    {
        if (!$categoryId) {
            return [];
        }

        $map = $this->buildCategoryPropertyNameMap($categoryId, true);
        if (empty($map)) {
            $this->syncCategoryMeta($token, $categoryId);
            $map = $this->buildCategoryPropertyNameMap($categoryId, true);
        }

        return $map;
    }

    protected function getCategoryPropertyValue(string $token, string $categoryId, string $propertyId, string $valueId): string
    {
        $map = $this->buildCategoryPropertyValueMap($categoryId, $propertyId, true);
        if (empty($map)) {
            $this->syncCategoryPropertyValues($token, $categoryId, $propertyId, true);
            $map = $this->buildCategoryPropertyValueMap($categoryId, $propertyId, true);
        }

        return $map[$valueId] ?? '';
    }

    /**
     * 将商品详情完整信息 upsert 到 products 表。
     */
    /**
     * 全量同步店铺商品到本地 products 表（通过 scroll-short-product-by-filter 拉取，最多 50 条）
     * 注意：该 API 不支持分页，每次只返回最多 50 条商品。
     */
    public function syncShopProducts(Shop $shop): array
    {
        $token = $shop->access_token;
        if (!$token) {
            return ['synced' => 0, 'error' => '店铺未配置access_token'];
        }

        $synced   = 0;
        $limit    = 50;
        $lastId   = null;   // last_product_id for pagination

        try {
            do {
                $payload = ['limit' => $limit];
                if ($lastId !== null) {
                    $payload['last_product_id'] = $lastId;
                }

                $response = Http::withHeaders([
                    'x-auth-token'     => $token,
                    'Content-Type'     => 'application/json',
                    'x-request-locale' => 'en',
                ])
                    ->withOptions(['verify' => $this->verifySsl])
                    ->timeout(60)
                    ->post($this->baseUrl . '/api/v1/scroll-short-product-by-filter', $payload);

                $data = $response->json();

                if (!$response->successful() || ($data['error'] ?? null)) {
                    $this->thirdPartyLog()->warning('AliExpress syncShopProducts page failed', [
                        'shop_id'      => $shop->id,
                        'last_id'      => $lastId,
                        'response'     => $data,
                    ]);
                    break;
                }

                // data 字段直接是商品数组（不是嵌套的 products 键）
                $products = is_array($data['data'] ?? null) ? $data['data'] : [];
                if (empty($products)) {
                    break;
                }

                foreach ($products as $productData) {
                    $aeItemId = (string) ($productData['id'] ?? '');
                    if ($aeItemId === '') {
                        continue;
                    }
                    // 先用短数据快速写入（保证记录存在）
                    $this->upsertProductFromShortData($shop, $productData);
                    // 再调用完整商品接口获取 status_type / title_ru / SKU属性 等完整字段
                    $this->getProductDetail($shop, $aeItemId);
                    $synced++;
                }

                // 取本页最后一个商品 id 作为下一页起点
                $lastProduct = end($products);
                $lastId = $lastProduct ? (string) ($lastProduct['id'] ?? null) : null;

            } while (count($products) >= $limit && $lastId !== null);

        } catch (\Exception $e) {
            $this->thirdPartyLog()->error('AliExpress syncShopProducts exception', [
                'shop_id' => $shop->id,
                'message' => $e->getMessage(),
            ]);
        }

        $this->thirdPartyLog()->info('AliExpress syncShopProducts done', [
            'shop_id' => $shop->id,
            'synced'  => $synced,
        ]);

        return ['synced' => $synced];
    }

    protected function shouldSyncProductDetail(?Product $existing, array $shortData): bool
    {
        if (!$existing) {
            return true;
        }

        if ($existing->category_name === '') {
            return true;
        }

        if ($existing->subjects === null && $existing->properties === null && $existing->detail === null) {
            return true;
        }

        $shortUpdatedAt = $this->parseDate($shortData['ali_updated_at'] ?? null);
        if (!$shortUpdatedAt) {
            return false;
        }

        if (!$existing->ae_updated_at) {
            return true;
        }

        return $existing->ae_updated_at->getTimestamp() !== $shortUpdatedAt->getTimestamp();
    }

    /**
     * 从 scroll-short-product-by-filter 返回的轻量数据 upsert 到 products 表
     */
    protected function upsertProductFromShortData(Shop $shop, array $data): void
    {
        $aeItemId = (string) ($data['id'] ?? '');
        if ($aeItemId === '') {
            return;
        }

        // API 返回 Subject 为英文字符串（scroll-short-product-by-filter 无多语言结构）
        $titleEn = (string) ($data['Subject'] ?? $data['subject'] ?? $data['title'] ?? '');
        $titleRu = '';
        $firstSku = is_array($data['sku'] ?? null) && !empty($data['sku']) ? reset($data['sku']) : [];

        try {
            Product::updateOrCreate(
                ['shop_id' => $shop->id, 'ae_item_id' => $aeItemId],
                [
                    'category_id' => (string) ($data['category_id'] ?? ''),
                    'owner_member_id' => (string) ($data['owner_member_id'] ?? ''),
                    'owner_member_seq' => (string) ($data['owner_member_seq'] ?? ''),
                    'currency_code' => (string) ($data['currency_code'] ?? ''),
                    'delivery_time' => (string) ($data['delivery_time'] ?? ''),
                    'freight_template_id' => (string) ($data['freight_template_id'] ?? ''),
                    'title_en' => $titleEn,
                    'title_ru' => $titleRu,
                    'main_image_url' => (string) ($data['main_image_url'] ?? ''),
                    'price' => (string) ($firstSku['price'] ?? ''),
                    'status_type' => (string) ($data['status'] ?? $data['status_type'] ?? ''),
                    'skus' => !empty($data['sku']) ? $data['sku'] : null,
                    'raw_data' => $data,
                    'ae_created_at' => $this->parseDate($data['ali_created_at'] ?? null),
                    'ae_updated_at' => $this->parseDate($data['ali_updated_at'] ?? null),
                    'synced_at' => now(),
                ]
            );
        } catch (\Throwable $e) {
            $this->thirdPartyLog()->warning('AliExpress upsertProductFromShortData failed', [
                'shop_id'    => $shop->id,
                'ae_item_id' => $aeItemId,
                'message'    => $e->getMessage(),
            ]);
        }
    }

    protected function upsertProduct(Shop $shop, array $detail, bool $skipCategorySync = false): void
    {
        $aeItemId = (string) ($detail['id'] ?? '');
        if ($aeItemId === '') {
            return;
        }

        // 解析多语言标题
        $titleEn = '';
        $titleRu = '';
        foreach ($detail['subject'] ?? [] as $sub) {
            $locale = $sub['locale'] ?? '';
            $name = $sub['name'] ?? '';
            if ($locale === 'en_US') {
                $titleEn = $name;
            } elseif ($locale === 'ru_RU') {
                $titleRu = $name;
            }
        }

        $categoryId = (string) ($detail['category_id'] ?? '');
        $shippingTemplateId = (string) ($detail['freight_template_id'] ?? '');
        $categoryName = '';
        if ($categoryId !== '' && !$skipCategorySync) {
            $category = $this->syncCategoryMeta($shop->access_token, $categoryId, $shippingTemplateId);
            $categoryName = (string) ($category->name ?? '');
        }

        // 取商品价格：优先用商品级别 price，没有则取第一个 SKU 的 price
        $price = (string) ($detail['price'] ?? '');
        if ($price === '' && !empty($detail['sku'][0]['price'])) {
            $price = (string) $detail['sku'][0]['price'];
        }

        try {
            Product::updateOrCreate(
                ['shop_id' => $shop->id, 'ae_item_id' => $aeItemId],
                [
                    'category_id' => $categoryId,
                    'category_name' => $categoryName,
                    'owner_member_id' => (string) ($detail['owner_member_id'] ?? ''),
                    'owner_member_seq' => (string) ($detail['owner_member_seq'] ?? ''),
                    'bulk_discount' => $this->nullableInt($detail['bulk_discount'] ?? null),
                    'bulk_order' => $this->nullableInt($detail['bulk_order'] ?? null),
                    'base_unit' => (string) ($detail['base_unit'] ?? ''),
                    'add_unit' => (string) ($detail['add_unit'] ?? ''),
                    'add_weight' => $this->nullableDecimal($detail['add_weight'] ?? null),
                    'currency_code' => (string) ($detail['currency_code'] ?? ''),
                    'delivery_time' => (string) ($detail['delivery_time'] ?? ''),
                    'freight_template_id' => $shippingTemplateId,
                    'package_height' => (string) ($detail['package_height'] ?? ''),
                    'package_length' => (string) ($detail['package_length'] ?? ''),
                    'package_width' => (string) ($detail['package_width'] ?? ''),
                    'lot_num' => (string) ($detail['lot_num'] ?? ''),
                    'title_en' => $titleEn,
                    'title_ru' => $titleRu,
                    'main_image_url' => (string) ($detail['main_image_url'] ?? ''),
                    'price' => $price,
                    'status_type' => (string) ($detail['status_type'] ?? ''),
                    'unit' => (string) ($detail['unit'] ?? ''),
                    'promise_template_id' => (string) ($detail['promise_template_id'] ?? ''),
                    'product_reduce_strategy' => (string) ($detail['product_reduce_strategy'] ?? ''),
                    'gross_weight' => $this->nullableDecimal($detail['gross_weight'] ?? null),
                    'sizechart_id' => (string) ($detail['sizechart_id'] ?? ''),
                    'package_type' => array_key_exists('package_type', $detail) ? (bool) $detail['package_type'] : null,
                    'descriptions' => is_array($detail['descriptions'] ?? null) ? $detail['descriptions'] : null,
                    'media' => is_array($detail['media'] ?? null) ? $detail['media'] : null,
                    'subjects' => is_array($detail['subject'] ?? null) ? $detail['subject'] : null,
                    'marketing_images' => is_array($detail['marketing_images'] ?? null) ? $detail['marketing_images'] : null,
                    'keywords' => is_array($detail['keywords'] ?? null) ? $detail['keywords'] : null,
                    'gtin' => (string) ($detail['gtin'] ?? ''),
                    'classifier_codes' => is_array($detail['classifier_codes'] ?? null) ? $detail['classifier_codes'] : null,
                    'detail' => $detail['detail'] ?? null,
                    'mobile_detail' => $detail['mobile_detail'] ?? null,
                    'ticket_allocate_type' => (string) ($detail['ticket_allocate_type'] ?? ''),
                    'video' => is_array($detail['video'] ?? null) ? $detail['video'] : null,
                    'properties' => is_array($detail['property'] ?? null) ? $detail['property'] : null,
                    'skus' => is_array($detail['sku'] ?? null) ? $detail['sku'] : null,
                    'raw_data' => $detail,
                    'ae_created_at' => $this->parseDate($detail['ali_created_at'] ?? null),
                    'ae_updated_at' => $this->parseDate($detail['ali_updated_at'] ?? null),
                    'synced_at' => now(),
                ]
            );
        } catch (\Throwable $e) {
            $this->thirdPartyLog()->warning('AliExpress upsertProduct failed', [
                'shop_id'    => $shop->id,
                'ae_item_id' => $aeItemId,
                'message'    => $e->getMessage(),
            ]);
        }
    }

    protected function getCategoryName(string $token, string $categoryId): string
    {
        if (!$categoryId) {
            return '';
        }

        $category = AliCategory::where('category_id', $categoryId)->first();
        if (!$category) {
            $category = $this->syncCategoryMeta($token, $categoryId);
        }

        return (string) ($category->name ?? '');
    }

    public function batchSyncCategories(string $token, array $categoryIds, string $shippingTemplateId = ''): int
    {
        $missing = [];
        foreach (array_unique($categoryIds) as $id) {
            if ($id === '') continue;
            $existing = AliCategory::where('category_id', $id)->first();
            if (!$existing || !$existing->synced_at || !$this->categoryMetaFullySynced($existing)) {
                $missing[] = $id;
            }
        }

        if (empty($missing)) {
            return 0;
        }

        $synced = 0;
        $chunks = array_chunk($missing, 20);

        foreach ($chunks as $chunk) {
            try {
                $response = Http::withHeaders([
                    'x-auth-token' => $token,
                    'Content-Type' => 'application/json',
                    'x-request-locale' => 'en',
                ])
                    ->withOptions(['verify' => $this->verifySsl])
                    ->connectTimeout(5)
                    ->timeout(10)
                    ->retry(1, 500, function (\Throwable $e) {
                        return $this->isTransientNetworkError($e);
                    })
                    ->post($this->baseUrl . '/api/v1/categories/get', [
                        'ids' => $chunk,
                    ]);

                $data = $response->json();
                $categories = $data['categories'] ?? [];

                foreach ($categories as $categoryData) {
                    if (!is_array($categoryData)) continue;
                    $categoryId = (string) ($categoryData['id'] ?? '');
                    if ($categoryId === '') continue;

                    AliCategory::updateOrCreate(
                        ['category_id' => $categoryId],
                        [
                            'parent_id' => (string) ($categoryData['parent_id'] ?? ''),
                            'name' => (string) ($categoryData['name'] ?? ''),
                            'is_leaf' => (bool) ($categoryData['is_leaf'] ?? false),
                            'is_special' => (bool) ($categoryData['is_special'] ?? false),
                            'is_visible' => array_key_exists('is_visible', $categoryData) ? (bool) $categoryData['is_visible'] : null,
                            'raw_data' => $categoryData,
                            'synced_at' => now(),
                        ]
                    );

                    $this->persistCategoryProperties($token, $categoryId, $categoryData, $shippingTemplateId);
                    $synced++;
                }
            } catch (\Throwable $e) {
                $this->thirdPartyLog()->warning('AliExpress batchSyncCategories failed', [
                    'ids' => $chunk,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $synced;
    }

    protected function syncCategoryMeta(string $token, string $categoryId, string $shippingTemplateId = ''): ?AliCategory
    {
        if ($categoryId === '') {
            return null;
        }

        $existing = AliCategory::where('category_id', $categoryId)->first();
        if ($existing && $existing->synced_at && $this->categoryMetaFullySynced($existing)) {
            return $existing;
        }

        $this->batchSyncCategories($token, [$categoryId], $shippingTemplateId);

        return AliCategory::where('category_id', $categoryId)->first() ?: $existing;
    }

    protected function categoryMetaFullySynced(AliCategory $category): bool
    {
        $rawData = is_array($category->raw_data) ? $category->raw_data : [];
        $properties = array_merge(
            is_array($rawData['properties'] ?? null) ? $rawData['properties'] : [],
            is_array($rawData['sku_properties'] ?? null) ? $rawData['sku_properties'] : []
        );

        if (empty($properties)) {
            return true;
        }

        $storedPropertyIds = AliCategoryProperty::where('category_id', $category->category_id)
            ->pluck('property_id')
            ->all();

        foreach ($properties as $property) {
            $propertyId = (string) ($property['id'] ?? '');
            if ($propertyId === '' || !in_array($propertyId, $storedPropertyIds, true)) {
                return false;
            }

            if (!empty($property['is_enum_prop'])) {
                $hasValues = AliCategoryPropertyValue::where('category_id', $category->category_id)
                    ->where('property_id', $propertyId)
                    ->exists();

                if (!$hasValues) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function persistCategoryProperties(string $token, string $categoryId, array $categoryData, string $shippingTemplateId = ''): void
    {
        $propertyGroups = [
            false => is_array($categoryData['properties'] ?? null) ? $categoryData['properties'] : [],
            true => is_array($categoryData['sku_properties'] ?? null) ? $categoryData['sku_properties'] : [],
        ];

        foreach ($propertyGroups as $isSkuProperty => $properties) {
            foreach ($properties as $property) {
                $propertyId = (string) ($property['id'] ?? '');
                if ($propertyId === '') {
                    continue;
                }

                AliCategoryProperty::updateOrCreate(
                    [
                        'category_id' => $categoryId,
                        'property_id' => $propertyId,
                        'is_sku_property' => (bool) $isSkuProperty,
                    ],
                    [
                        'name' => (string) ($property['name'] ?? ''),
                        'is_required' => (bool) ($property['is_required'] ?? false),
                        'is_key' => (bool) ($property['is_key'] ?? false),
                        'is_brand' => (bool) ($property['is_brand'] ?? false),
                        'is_enum_prop' => (bool) ($property['is_enum_prop'] ?? false),
                        'is_multi_select' => (bool) ($property['is_multi_select'] ?? false),
                        'is_input_prop' => (bool) ($property['is_input_prop'] ?? false),
                        'has_unit' => (bool) ($property['has_unit'] ?? false),
                        'has_customized_pic' => (bool) ($property['has_customized_pic'] ?? false),
                        'has_customized_name' => (bool) ($property['has_customized_name'] ?? false),
                        'units' => is_array($property['units'] ?? null) ? $property['units'] : null,
                        'raw_data' => $property,
                        'synced_at' => now(),
                    ]
                );

                if (!empty($property['is_enum_prop'])) {
                    $this->syncCategoryPropertyValues($token, $categoryId, $propertyId, (bool) $isSkuProperty, $shippingTemplateId);
                }
            }
        }
    }

    protected function syncCategoryPropertyValues(string $token, string $categoryId, string $propertyId, bool $isSkuProperty, string $shippingTemplateId = ''): array
    {
        $existingQuery = AliCategoryPropertyValue::where('category_id', $categoryId)
            ->where('property_id', $propertyId)
            ->where('is_sku_property', $isSkuProperty)
            ->where('shipping_template_id', $shippingTemplateId);

        if ($existingQuery->exists()) {
            return $this->buildCategoryPropertyValueMap($categoryId, $propertyId, $isSkuProperty);
        }

        try {
            $payload = [
                'category_id' => $categoryId,
                'property_id' => $propertyId,
                'is_sku_property' => $isSkuProperty,
            ];

            if ($shippingTemplateId !== '') {
                $payload['shipping_template_id'] = $shippingTemplateId;
            }

            $response = Http::withHeaders([
                'x-auth-token' => $token,
                'Content-Type' => 'application/json',
                'x-request-locale' => 'en',
            ])
                ->withOptions(['verify' => $this->verifySsl])
                ->connectTimeout(5)
                ->timeout(8)
                ->retry(1, 500, function (\Throwable $e) {
                    return $this->isTransientNetworkError($e);
                })
                ->post($this->baseUrl . '/api/v1/categories/values-dictionary', $payload);

            $data = $response->json();
            if (!$response->successful() || ($data['error'] ?? null)) {
                return [];
            }

            $map = [];
            foreach ($data['values'] ?? [] as $valueData) {
                $valueId = (string) ($valueData['id'] ?? '');
                if ($valueId === '') {
                    continue;
                }

                $name = (string) ($valueData['name'] ?? '');
                $map[$valueId] = $name;

                AliCategoryPropertyValue::updateOrCreate(
                    [
                        'category_id' => $categoryId,
                        'property_id' => $propertyId,
                        'value_id' => $valueId,
                        'is_sku_property' => $isSkuProperty,
                        'shipping_template_id' => $shippingTemplateId,
                    ],
                    [
                        'name' => $name,
                        'raw_data' => $valueData,
                        'synced_at' => now(),
                    ]
                );
            }

            return $map;
        } catch (\Throwable $e) {
            $this->thirdPartyLog()->warning('AliExpress syncCategoryPropertyValues failed', [
                'category_id' => $categoryId,
                'property_id' => $propertyId,
                'is_sku_property' => $isSkuProperty,
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    protected function buildCategoryPropertyNameMap(string $categoryId, bool $isSkuProperty): array
    {
        $map = [];

        $query = AliCategoryProperty::where('category_id', $categoryId)
            ->where('is_sku_property', $isSkuProperty);

        // SKU 属性只取 is_key 为真的关键属性（如颜色/尺码），避免展示过多无关信息
        if ($isSkuProperty) {
            $keyQuery = clone $query;
            $keyProperties = $keyQuery->where('is_key', true)->get(['property_id', 'name']);
            if ($keyProperties->isNotEmpty()) {
                $keyProperties->each(function (AliCategoryProperty $property) use (&$map) {
                    $map[$property->property_id] = ['name' => $property->name ?: $property->property_id];
                });
                return $map;
            }
        }

        $query->orderBy('property_id')
            ->get(['property_id', 'name'])
            ->each(function (AliCategoryProperty $property) use (&$map) {
                $map[$property->property_id] = ['name' => $property->name ?: $property->property_id];
            });

        return $map;
    }

    protected function buildCategoryPropertyValueMap(string $categoryId, string $propertyId, bool $isSkuProperty): array
    {
        $map = [];

        AliCategoryPropertyValue::where('category_id', $categoryId)
            ->where('property_id', $propertyId)
            ->where('is_sku_property', $isSkuProperty)
            ->orderByRaw("CASE WHEN shipping_template_id = '' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get(['value_id', 'name'])
            ->each(function (AliCategoryPropertyValue $value) use (&$map) {
                if (!array_key_exists($value->value_id, $map)) {
                    $map[$value->value_id] = $value->name;
                }
            });

        return $map;
    }

    protected function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    protected function nullableDecimal($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    /**
     * 批量富化多个订单的 SKU 属性（同步完成后调用）
     */
    public function enrichOrdersSkuBatch(Shop $shop, array $orderIds): int
    {
        if (empty($orderIds)) {
            return 0;
        }

        $orders = Order::with('items')
            ->whereIn('id', $orderIds)
            ->get();

        $total = 0;
        foreach ($orders as $order) {
            $total += $this->enrichOrderItemSkuAttributes($shop, $order);
        }

        return $total;
    }

    protected function dispatchSkuEnrichmentTask(int $shopId, int $orderId): void
    {
        $lockKey = $this->skuEnrichLockKey($orderId);
        if (!Cache::add($lockKey, now()->toDateTimeString(), now()->addSeconds(self::SKU_ENRICH_LOCK_TTL_SECONDS))) {
            return;
        }

        RunOrderSkuEnrichmentTask::dispatch($shopId, $orderId);
    }

    protected function skuEnrichLockKey(int $orderId): string
    {
        return sprintf('ali:order-sku-enrich:lock:%d', $orderId);
    }

    protected function isTransientNetworkError(\Throwable $e): bool
    {
        if ($e instanceof \Illuminate\Http\Client\ConnectionException) {
            return true;
        }

        $msg = $e->getMessage();

        return str_contains($msg, 'cURL error 35')
            || str_contains($msg, 'cURL error 28')
            || str_contains($msg, 'cURL error 6')
            || str_contains($msg, 'cURL error 7')
            || str_contains($msg, 'cURL error 52')
            || str_contains($msg, 'cURL error 56')
            || str_contains($msg, 'unexpected eof while reading')
            || str_contains($msg, 'Connection timed out')
            || str_contains($msg, 'Could not resolve host')
            || str_contains($msg, 'errno=10054');
    }
}
