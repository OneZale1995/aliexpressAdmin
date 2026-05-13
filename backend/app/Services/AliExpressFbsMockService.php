<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;

class AliExpressFbsMockService
{
    protected OrderLogisticsService $orderLogisticsService;

    public function __construct()
    {
        $this->orderLogisticsService = new OrderLogisticsService();
    }

    public function getWorkflow(Order $order): array
    {
        $state = $this->getState($order);

        $this->persistState($order, $state);

        return [
            'success' => true,
            'message' => 'ok',
            'data' => [
                'order_id' => $order->id,
                'trade_order_id' => (int) $order->ae_order_id,
                'logistic_order_id' => data_get($state, 'logistic_order.id'),
                'handover_list_id' => data_get($state, 'handover_list.id'),
                'tracking_number' => data_get($state, 'logistic_order.platform_tracking_code') ?: $order->tracking_number,
                'logistic_order' => $state['logistic_order'] ?? null,
                'handover_list' => $state['handover_list'] ?? null,
            ],
        ];
    }

    public function createLogisticOrder(Order $order, array $options = []): array
    {
        $state = $this->getState($order);
        $logisticOrderId = (int) (data_get($state, 'logistic_order.id') ?: $this->generateLogisticOrderId($order));
        $trackingCode = (string) (data_get($state, 'logistic_order.platform_tracking_code') ?: $this->generateTrackingCode($order));
        $cutOffDate = (string) (data_get($state, 'logistic_order.cut_off_date') ?: $this->resolveCutOffDate($order));

        $logisticOrder = [
            'id' => $logisticOrderId,
            'trade_order_id' => (int) $order->ae_order_id,
            'status' => 'AwaitingHandoverList',
            'state_status_name' => '等待加入交接清单',
            'platform_tracking_code' => $trackingCode,
            'cut_off_date' => $cutOffDate,
            'total_length' => (int) ($options['total_length'] ?? 20),
            'total_width' => (int) ($options['total_width'] ?? 10),
            'total_height' => (int) ($options['total_height'] ?? 5),
            'total_weight' => (float) ($options['total_weight'] ?? 0.5),
            'undeliverable_option' => (string) ($options['undeliverable_option'] ?? 'Return'),
            'danger_type' => (string) ($options['danger_type'] ?? 'General'),
            'items' => is_array($options['items'] ?? null) ? array_values($options['items']) : [],
            'is_mock' => true,
        ];

        $this->persistState($order, array_merge($state, [
            'logistic_order' => $logisticOrder,
        ]));

        return [
            'success' => true,
            'message' => 'FBS 发货单创建成功（Mock）',
            'data' => [
                'logistic_order_id' => $logisticOrderId,
                'trade_order_id' => (int) $order->ae_order_id,
                'orders' => [[
                    'trade_order_id' => (int) $order->ae_order_id,
                    'logistic_orders' => [$logisticOrder],
                ]],
                'raw' => [
                    'mock' => true,
                    'logistic_order' => $logisticOrder,
                ],
            ],
        ];
    }

    public function getLogisticOrderInfo(Order $order, int $logisticOrderId): array
    {
        $state = $this->getState($order);
        $logisticOrder = $state['logistic_order'] ?? null;

        if (!$logisticOrder || (int) ($logisticOrder['id'] ?? 0) !== $logisticOrderId) {
            return ['success' => false, 'message' => '未查询到发货单信息'];
        }

        return [
            'success' => true,
            'message' => 'ok',
            'data' => $logisticOrder,
            'raw' => ['mock' => true, 'logistic_order' => $logisticOrder],
        ];
    }

    public function createHandoverList(Order $order, array $logisticOrderIds, ?string $arrivalDate = null): array
    {
        $state = $this->getState($order);
        if (empty($state['logistic_order']['id'])) {
            return ['success' => false, 'message' => '缺少 logistic_order_id，无法创建交接单'];
        }

        $handoverListId = (int) (data_get($state, 'handover_list.id') ?: $this->generateHandoverListId($order));
        $resolvedArrivalDate = $arrivalDate ?: $this->resolveArrivalDate($order);
        $createdAt = (string) (data_get($state, 'handover_list.gmt_create') ?: $this->nowIso());

        $handoverList = [
            'id' => $handoverListId,
            'status' => 'Created',
            'arrival_date' => $resolvedArrivalDate,
            'shipment_type' => 'Pickup',
            'gmt_create' => $createdAt,
            'pickup_date' => data_get($state, 'handover_list.pickup_date'),
            'pickup_time_from' => data_get($state, 'handover_list.pickup_time_from'),
            'pickup_time_to' => data_get($state, 'handover_list.pickup_time_to'),
            'logistic_order_ids' => array_values(array_unique(array_map('intval', $logisticOrderIds))),
            'is_mock' => true,
        ];

        $logisticOrder = $state['logistic_order'];
        $logisticOrder['status'] = 'AwaitingDispatch';
        $logisticOrder['state_status_name'] = '准备发货';
        $logisticOrder['handover_list_id'] = $handoverListId;

        $this->persistState($order, array_merge($state, [
            'logistic_order' => $logisticOrder,
            'handover_list' => $handoverList,
        ]));

        return [
            'success' => true,
            'message' => '交接清单创建成功（Mock）',
            'data' => [
                'handover_list_id' => $handoverListId,
                'arrival_date' => $resolvedArrivalDate,
                'raw' => [
                    'mock' => true,
                    'handover_list' => $handoverList,
                ],
            ],
        ];
    }

    public function getHandoverListInfo(Order $order, int $handoverListId): array
    {
        $state = $this->getState($order);
        $handoverList = $state['handover_list'] ?? null;

        if (!$handoverList || (int) ($handoverList['id'] ?? 0) !== $handoverListId) {
            return ['success' => false, 'message' => '未查询到交接清单信息'];
        }

        return [
            'success' => true,
            'message' => 'ok',
            'data' => $handoverList,
            'raw' => ['mock' => true, 'handover_list' => $handoverList],
        ];
    }

    public function addLogisticOrdersToHandoverList(Order $order, int $handoverListId, array $logisticOrderIds): array
    {
        $state = $this->getState($order);
        if (empty($state['logistic_order']['id'])) {
            return ['success' => false, 'message' => '缺少 logistic_order_id'];
        }

        $existingIds = is_array(data_get($state, 'handover_list.logistic_order_ids')) ? data_get($state, 'handover_list.logistic_order_ids') : [];
        $mergedIds = array_values(array_unique(array_merge($existingIds, array_map('intval', $logisticOrderIds))));

        $handoverList = [
            'id' => $handoverListId,
            'status' => 'Created',
            'arrival_date' => data_get($state, 'handover_list.arrival_date') ?: $this->resolveArrivalDate($order),
            'shipment_type' => data_get($state, 'handover_list.shipment_type') ?: 'Pickup',
            'gmt_create' => data_get($state, 'handover_list.gmt_create') ?: $this->nowIso(),
            'pickup_date' => data_get($state, 'handover_list.pickup_date'),
            'pickup_time_from' => data_get($state, 'handover_list.pickup_time_from'),
            'pickup_time_to' => data_get($state, 'handover_list.pickup_time_to'),
            'logistic_order_ids' => $mergedIds,
            'is_mock' => true,
        ];

        $logisticOrder = $state['logistic_order'];
        $logisticOrder['status'] = 'AwaitingDispatch';
        $logisticOrder['state_status_name'] = '准备发货';
        $logisticOrder['handover_list_id'] = $handoverListId;

        $this->persistState($order, array_merge($state, [
            'logistic_order' => $logisticOrder,
            'handover_list' => $handoverList,
        ]));

        return [
            'success' => true,
            'message' => '已添加到交接清单（Mock）',
            'data' => [
                'handover_list_id' => $handoverListId,
                'logistic_order_ids' => $mergedIds,
                'raw' => [
                    'mock' => true,
                    'handover_list' => $handoverList,
                ],
            ],
        ];
    }

    public function removeLogisticOrdersFromHandoverList(Order $order, int $handoverListId, array $logisticOrderIds): array
    {
        $state = $this->getState($order);
        $handoverList = $state['handover_list'] ?? null;
        if (!$handoverList || (int) ($handoverList['id'] ?? 0) !== $handoverListId) {
            return ['success' => false, 'message' => '缺少 handover_list_id'];
        }

        $remainingIds = array_values(array_filter(
            array_map('intval', $handoverList['logistic_order_ids'] ?? []),
            fn ($id) => !in_array($id, array_map('intval', $logisticOrderIds), true)
        ));

        $logisticOrder = $state['logistic_order'] ?? null;
        if ($logisticOrder) {
            $logisticOrder['status'] = 'AwaitingHandoverList';
            $logisticOrder['state_status_name'] = '等待加入交接清单';
            unset($logisticOrder['handover_list_id']);
        }

        $this->persistState($order, array_merge($state, [
            'logistic_order' => $logisticOrder,
            'handover_list' => empty($remainingIds) ? null : array_merge($handoverList, [
                'logistic_order_ids' => $remainingIds,
            ]),
        ]));

        return [
            'success' => true,
            'message' => '已从交接清单移除发货单（Mock）',
            'data' => [
                'handover_list_id' => $handoverListId,
                'logistic_order_ids' => $remainingIds,
                'raw' => [
                    'mock' => true,
                ],
            ],
        ];
    }

    public function printHandoverList(Order $order, int $handoverListId): array
    {
        $info = $this->getHandoverListInfo($order, $handoverListId);
        if (!$info['success']) {
            return $info;
        }

        $handoverList = $info['data'];

        return [
            'success' => true,
            'message' => '交接清单标签获取成功（Mock）',
            'data' => [
                'label_url' => $this->buildDocumentDataUrl('FBS Mock Handover List', [
                    '交接单ID：' . ($handoverList['id'] ?? '-'),
                    '状态：' . ($handoverList['status'] ?? '-'),
                    '交接日期：' . ($handoverList['arrival_date'] ?? '-'),
                    '类型：' . ($handoverList['shipment_type'] ?? '-'),
                    '订单号：' . $order->ae_order_id,
                ]),
                'raw' => [
                    'mock' => true,
                    'handover_list' => $handoverList,
                ],
            ],
        ];
    }

    public function readyForPickup(Order $order, int $handoverListId): array
    {
        $state = $this->getState($order);
        $handoverList = $state['handover_list'] ?? null;
        if (!$handoverList || (int) ($handoverList['id'] ?? 0) !== $handoverListId) {
            return ['success' => false, 'message' => '缺少 handover_list_id'];
        }

        $pickupDate = Carbon::now()->addDay()->format('Y-m-d');
        $handoverList['status'] = 'Accepted';
        $handoverList['pickup_date'] = $pickupDate;
        $handoverList['pickup_time_from'] = '10:00';
        $handoverList['pickup_time_to'] = '18:00';

        $logisticOrder = $state['logistic_order'] ?? null;
        if ($logisticOrder) {
            $logisticOrder['status'] = 'OrderReceivedFromSeller';
            $logisticOrder['state_status_name'] = '已从卖家接收货件';
        }

        $this->persistState($order, array_merge($state, [
            'logistic_order' => $logisticOrder,
            'handover_list' => $handoverList,
        ]));

        return [
            'success' => true,
            'message' => '交接清单已标记为待揽收（Mock）',
            'data' => [
                'pickup_date' => $pickupDate,
                'pickup_time_from' => '10:00',
                'pickup_time_to' => '18:00',
                'status' => 'Accepted',
                'raw' => [
                    'mock' => true,
                ],
            ],
        ];
    }

    public function setBigBagCount(Order $order, int $handoverListId, int $bigBagCount): array
    {
        $state = $this->getState($order);
        $handoverList = $state['handover_list'] ?? null;
        if (!$handoverList || (int) ($handoverList['id'] ?? 0) !== $handoverListId) {
            return ['success' => false, 'message' => '缺少 handover_list_id'];
        }

        $handoverList['big_bag_count'] = $bigBagCount;
        $this->persistState($order, array_merge($state, ['handover_list' => $handoverList]));

        return [
            'success' => true,
            'message' => "大袋数量已设置为 {$bigBagCount}（Mock）",
            'data' => ['big_bag_count' => $bigBagCount],
        ];
    }

    public function transferHandoverList(Order $order, int $handoverListId): array
    {
        $state = $this->getState($order);
        $handoverList = $state['handover_list'] ?? null;
        if (!$handoverList || (int) ($handoverList['id'] ?? 0) !== $handoverListId) {
            return ['success' => false, 'message' => '缺少 handover_list_id'];
        }

        $handoverList['status'] = 'Transferred';

        $logisticOrder = $state['logistic_order'] ?? null;
        if ($logisticOrder) {
            $logisticOrder['status'] = 'ProviderPostingReceive';
            $logisticOrder['state_status_name'] = '物流已接收';
        }

        $this->persistState($order, array_merge($state, [
            'logistic_order' => $logisticOrder,
            'handover_list' => $handoverList,
        ]));

        return [
            'success' => true,
            'message' => '交接清单状态已更新（Mock）',
            'data' => [
                'status' => 'Transferred',
                'raw' => [
                    'mock' => true,
                ],
            ],
        ];
    }

    public function getShippingLabel(Order $order): array
    {
        $state = $this->getState($order);
        $logisticOrder = $state['logistic_order'] ?? null;
        if (!$logisticOrder) {
            return ['success' => false, 'message' => '未找到logistic_order_id，请先创建货件并同步订单后再打印'];
        }

        return [
            'success' => true,
            'message' => '面单获取成功（Mock）',
            'label_url' => $this->buildDocumentDataUrl('FBS Mock Shipping Label', [
                '订单号：' . $order->ae_order_id,
                '发货单ID：' . ($logisticOrder['id'] ?? '-'),
                '状态：' . ($logisticOrder['status'] ?? '-'),
                '平台运单号：' . ($logisticOrder['platform_tracking_code'] ?? '-'),
                '截止发货：' . ($logisticOrder['cut_off_date'] ?? '-'),
            ]),
            'used_ids' => [(int) ($logisticOrder['id'] ?? 0)],
            'raw' => [
                'mock' => true,
                'logistic_order' => $logisticOrder,
            ],
        ];
    }

    public function ship(Order $order, array $options = []): array
    {
        $createResult = $this->createLogisticOrder($order, $options);
        if (!$createResult['success']) {
            return $createResult;
        }

        $workflow = $this->getWorkflow($order);
        $logisticOrder = $workflow['data']['logistic_order'] ?? [];

        return [
            'success' => true,
            'message' => 'FBS Mock 发货成功',
            'data' => [
                'mock' => true,
                'create_logistic_order' => $createResult['data'] ?? null,
                'fbs_info' => $logisticOrder,
                'tracking_number' => $logisticOrder['platform_tracking_code'] ?? null,
            ],
        ];
    }

    private function getState(Order $order): array
    {
        $order->loadMissing('currentLogistics');

        $payload = data_get($order, 'currentLogistics.payload');
        $mockState = is_array($payload['mock_fbs'] ?? null) ? $payload['mock_fbs'] : [];

        if (!empty($mockState)) {
            return $mockState;
        }

        $logisticOrderId = (int) ($order->logistic_order_id ?: 0);
        $handoverListId = (int) ($order->handover_list_id ?: 0);
        $trackingNumber = $order->tracking_number ?: ($logisticOrderId > 0 ? $this->generateTrackingCode($order) : null);

        return [
            'logistic_order' => $logisticOrderId > 0 ? [
                'id' => $logisticOrderId,
                'trade_order_id' => (int) $order->ae_order_id,
                'status' => $handoverListId > 0 ? 'AwaitingDispatch' : 'AwaitingHandoverList',
                'state_status_name' => $handoverListId > 0 ? '准备发货' : '等待加入交接清单',
                'platform_tracking_code' => $trackingNumber,
                'cut_off_date' => $this->resolveCutOffDate($order),
                'handover_list_id' => $handoverListId ?: null,
                'is_mock' => true,
            ] : null,
            'handover_list' => $handoverListId > 0 ? [
                'id' => $handoverListId,
                'status' => $order->handover_list_status ?: 'Created',
                'arrival_date' => $this->resolveArrivalDate($order),
                'shipment_type' => 'Pickup',
                'gmt_create' => $this->nowIso(),
                'pickup_date' => null,
                'pickup_time_from' => null,
                'pickup_time_to' => null,
                'logistic_order_ids' => $logisticOrderId > 0 ? [$logisticOrderId] : [],
                'is_mock' => true,
            ] : null,
        ];
    }

    private function persistState(Order $order, array $state): void
    {
        $logisticOrder = is_array($state['logistic_order'] ?? null) ? $state['logistic_order'] : null;
        $handoverList = is_array($state['handover_list'] ?? null) ? $state['handover_list'] : null;

        $this->orderLogisticsService->syncPrimary($order, [
            'logistics_mode' => 'FBS',
            'provider_code' => 'aliexpress',
            'provider_name' => 'AliExpress',
            'template_code' => 'online',
            'platform_logistic_order_id' => $logisticOrder['id'] ?? null,
            'handover_list_id' => $handoverList['id'] ?? null,
            'handover_list_status' => $handoverList['status'] ?? null,
            'tracking_number' => $logisticOrder['platform_tracking_code'] ?? $order->tracking_number,
            'logistic_status' => $logisticOrder['status'] ?? null,
            'payload' => [
                'mock_fbs' => [
                    'logistic_order' => $logisticOrder,
                    'handover_list' => $handoverList,
                ],
            ],
        ]);

        $order->refresh();
        $order->load('currentLogistics');
    }

    private function generateLogisticOrderId(Order $order): int
    {
        return 910000000 + (int) $order->id;
    }

    private function generateHandoverListId(Order $order): int
    {
        return 920000000 + (int) $order->id;
    }

    private function generateTrackingCode(Order $order): string
    {
        return 'MOCKFBS' . str_pad((string) $order->id, 8, '0', STR_PAD_LEFT);
    }

    private function resolveCutOffDate(Order $order): string
    {
        if ($order->shipping_deadline) {
            return Carbon::parse($order->shipping_deadline)->format('Y-m-d\TH:i:s.000\Z');
        }

        return Carbon::now()->addDays(2)->format('Y-m-d\TH:i:s.000\Z');
    }

    private function resolveArrivalDate(Order $order): string
    {
        if ($order->shipping_deadline) {
            return Carbon::parse($order->shipping_deadline)->startOfDay()->format('Y-m-d\T00:00:00.000\Z');
        }

        return Carbon::now()->addDay()->startOfDay()->format('Y-m-d\T00:00:00.000\Z');
    }

    private function nowIso(): string
    {
        return Carbon::now()->format('Y-m-d\TH:i:s.000\Z');
    }

    private function buildDocumentDataUrl(string $title, array $lines): string
    {
        $items = implode('', array_map(fn ($line) => '<li>' . e((string) $line) . '</li>', $lines));
        $html = '<!doctype html><html><head><meta charset="utf-8"><title>' . e($title) . '</title><style>body{font-family:Arial,sans-serif;padding:24px;}h1{font-size:24px;}ul{line-height:1.8;}</style></head><body><h1>' . e($title) . '</h1><p>这是本地 Mock 文档，用于联调 FBS 流程。</p><ul>' . $items . '</ul></body></html>';

        return 'data:text/html;charset=UTF-8,' . rawurlencode($html);
    }
}