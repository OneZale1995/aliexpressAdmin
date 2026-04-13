<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;

class OrderFbsWorkflowService
{
    protected AliExpressService $aliExpressService;

    protected OrderLogisticsService $orderLogisticsService;

    public function __construct()
    {
        $this->aliExpressService = new AliExpressService();
        $this->orderLogisticsService = new OrderLogisticsService();
    }

    public function getWorkflow(Order $order): array
    {
        $result = $this->aliExpressService->getFbsWorkflow($order->shop, $order->fresh(['shop', 'items', 'currentLogistics']));

        if (!$result['success']) {
            return ['success' => false, 'message' => $result['message'] ?? 'FBS 工作流信息获取失败'];
        }

        return [
            'success' => true,
            'message' => 'FBS 工作流信息获取成功',
            'data' => $result['data'] ?? [],
        ];
    }

    public function createLogisticOrder(Order $order, array $payload): array
    {
        $result = $this->aliExpressService->createFbsLogisticOrder($order->shop, $order, $payload);
        if (!$result['success']) {
            return ['success' => false, 'message' => $result['message'] ?? '创建 FBS 发货单失败'];
        }

        $workflow = $this->refreshWorkflow($order);

        return [
            'success' => true,
            'message' => $result['message'] ?? 'FBS 发货单创建成功',
            'data' => [
                'create_result' => $result['data'] ?? null,
                'workflow' => $workflow['data'] ?? null,
            ],
        ];
    }

    public function createHandoverList(Order $order, array $payload): array
    {
        $logisticOrderIds = $this->resolveLogisticOrderIds($order, $payload['logistic_order_ids'] ?? []);
        $arrivalDate = $this->normalizeAliExpressDate($payload['arrival_date'] ?? null);

        $result = $this->aliExpressService->createFbsHandoverList($order->shop, $logisticOrderIds, $arrivalDate);
        if (!$result['success']) {
            return ['success' => false, 'message' => $result['message'] ?? '创建交接清单失败'];
        }

        $handoverListId = data_get($result, 'data.handover_list_id');
        if ($handoverListId) {
            $this->orderLogisticsService->syncPrimary($order, [
                'logistics_mode' => 'FBS',
                'provider_code' => 'aliexpress',
                'provider_name' => 'AliExpress',
                'handover_list_id' => (int) $handoverListId,
                'handover_list_status' => 'Created',
                'payload' => [
                    'fbs' => [
                        'handover_list_create' => $result['data'] ?? null,
                    ],
                ],
            ]);
        }

        $workflow = $this->refreshWorkflow($order);

        return [
            'success' => true,
            'message' => $result['message'] ?? '交接清单创建成功',
            'data' => [
                'create_result' => $result['data'] ?? null,
                'workflow' => $workflow['data'] ?? null,
            ],
        ];
    }

    public function addLogisticOrdersToHandover(Order $order, int $handoverListId, array $logisticOrderIds): array
    {
        $ids = $this->resolveLogisticOrderIds($order, $logisticOrderIds);
        $result = $this->aliExpressService->addFbsLogisticOrdersToHandoverList($order->shop, $handoverListId, $ids);
        if (!$result['success']) {
            return ['success' => false, 'message' => $result['message'] ?? '添加发货单到交接清单失败'];
        }

        $this->orderLogisticsService->syncPrimary($order, [
            'logistics_mode' => 'FBS',
            'provider_code' => 'aliexpress',
            'provider_name' => 'AliExpress',
            'handover_list_id' => $handoverListId,
        ]);

        $workflow = $this->refreshWorkflow($order);

        return [
            'success' => true,
            'message' => '已添加到交接清单',
            'data' => [
                'action_result' => $result['data'] ?? null,
                'workflow' => $workflow['data'] ?? null,
            ],
        ];
    }

    public function removeLogisticOrdersFromHandover(Order $order, int $handoverListId, array $logisticOrderIds): array
    {
        $ids = $this->resolveLogisticOrderIds($order, $logisticOrderIds);
        $result = $this->aliExpressService->removeFbsLogisticOrdersFromHandoverList($order->shop, $handoverListId, $ids);
        if (!$result['success']) {
            return ['success' => false, 'message' => $result['message'] ?? '从交接清单移除发货单失败'];
        }

        $this->orderLogisticsService->syncPrimary($order, [
            'logistics_mode' => 'FBS',
            'provider_code' => 'aliexpress',
            'provider_name' => 'AliExpress',
            'handover_list_id' => null,
            'handover_list_status' => null,
        ]);

        $workflow = $this->refreshWorkflow($order);

        return [
            'success' => true,
            'message' => '已从交接清单移除发货单',
            'data' => [
                'action_result' => $result['data'] ?? null,
                'workflow' => $workflow['data'] ?? null,
            ],
        ];
    }

    public function printHandoverList(Order $order, ?int $handoverListId = null): array
    {
        $targetId = $handoverListId ?: (int) $order->handover_list_id;
        if ($targetId <= 0) {
            return ['success' => false, 'message' => '当前订单暂无交接清单ID'];
        }

        $result = $this->aliExpressService->printFbsHandoverList($order->shop, $targetId);
        if (!$result['success']) {
            return ['success' => false, 'message' => $result['message'] ?? '交接清单标签获取失败'];
        }

        return [
            'success' => true,
            'message' => '交接清单标签获取成功',
            'data' => $result['data'] ?? [],
        ];
    }

    public function readyForPickup(Order $order, ?int $handoverListId = null): array
    {
        $targetId = $handoverListId ?: (int) $order->handover_list_id;
        if ($targetId <= 0) {
            return ['success' => false, 'message' => '当前订单暂无交接清单ID'];
        }

        $result = $this->aliExpressService->readyFbsHandoverForPickup($order->shop, $targetId);
        if (!$result['success']) {
            return ['success' => false, 'message' => $result['message'] ?? '交接清单标记待揽收失败'];
        }

        $workflow = $this->refreshWorkflow($order);
        $this->syncWorkflowShipState($order, $workflow);

        return [
            'success' => true,
            'message' => '交接清单已标记为待揽收',
            'data' => [
                'action_result' => $result['data'] ?? null,
                'workflow' => $workflow['data'] ?? null,
            ],
        ];
    }

    public function transferHandoverList(Order $order, ?int $handoverListId = null): array
    {
        $targetId = $handoverListId ?: (int) $order->handover_list_id;
        if ($targetId <= 0) {
            return ['success' => false, 'message' => '当前订单暂无交接清单ID'];
        }

        $result = $this->aliExpressService->transferFbsHandoverList($order->shop, $targetId);
        if (!$result['success']) {
            return ['success' => false, 'message' => $result['message'] ?? '交接清单关闭失败'];
        }

        $workflow = $this->refreshWorkflow($order);
        $this->syncWorkflowShipState($order, $workflow);

        return [
            'success' => true,
            'message' => '交接清单状态已更新',
            'data' => [
                'action_result' => $result['data'] ?? null,
                'workflow' => $workflow['data'] ?? null,
            ],
        ];
    }

    public function createAndPrintTransferSheet(Order $order): array
    {
        $logisticOrderId = (int) $order->logistic_order_id;
        if ($logisticOrderId <= 0) {
            return ['success' => false, 'message' => '该订单暂无 logistic_order_id，暂不可创建交接单'];
        }

        if (!empty($order->handover_list_id)) {
            $result = $this->aliExpressService->printFbsHandoverList($order->shop, (int) $order->handover_list_id);
        } else {
            $result = $this->aliExpressService->createAndPrintTransferSheet($order->shop, [$logisticOrderId]);
            $handoverListId = data_get($result, 'data.handover_list_id');
            if ($handoverListId) {
                $this->orderLogisticsService->syncPrimary($order, [
                    'logistics_mode' => 'FBS',
                    'provider_code' => 'aliexpress',
                    'provider_name' => 'AliExpress',
                    'handover_list_id' => (int) $handoverListId,
                    'handover_list_status' => 'Created',
                ]);
            }
        }

        if (!$result['success']) {
            return ['success' => false, 'message' => $result['message'] ?? '交接单处理失败'];
        }

        return [
            'success' => true,
            'message' => $result['message'] ?? '交接单处理成功',
            'data' => $result['data'] ?? [],
        ];
    }

    private function resolveLogisticOrderIds(Order $order, array $logisticOrderIds): array
    {
        $ids = $logisticOrderIds;
        if (empty($ids) && !empty($order->logistic_order_id)) {
            $ids = [(int) $order->logistic_order_id];
        }

        return array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));
    }

    private function refreshWorkflow(Order $order): array
    {
        return $this->aliExpressService->getFbsWorkflow($order->shop, $order->fresh(['shop', 'items', 'currentLogistics']));
    }

    private function syncWorkflowShipState(Order $order, array $workflow): void
    {
        $order->update([
            'actual_ship_at' => $order->actual_ship_at ?: now(),
            'marked_ship_at' => $order->marked_ship_at ?: now(),
        ]);

        $this->orderLogisticsService->syncPrimary($order, [
            'handover_list_status' => data_get($workflow, 'data.handover_list.status'),
        ]);
    }

    private function normalizeAliExpressDate(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        if (str_contains($value, 'T')) {
            return $value;
        }

        try {
            return Carbon::parse($value)->startOfDay()->format('Y-m-d\T00:00:00.000\Z');
        } catch (\Throwable $e) {
            return null;
        }
    }
}