<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\AliExpressService;
use App\Services\ChinaPostService;
use App\Services\OrderFbsWorkflowService;
use App\Services\OrderLogisticsService;
use App\Services\Sz56tService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class OrderLogisticsController extends Controller
{
    use ApiResponse;

    /**
     * 提交发货（调用速卖通 mark-ship API，同时记录本地）
     * FBS: 直接调用 AliExpress mark-ship
     * DBS: 先创建第三方物流单(雷翼/邮政) → 再调用 AliExpress offline-ship 更新状态
     */
    public function ship(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
            'track_number' => 'nullable|string|max:100',
            'logistic_method' => 'nullable|string|max:100',
            'ship_provider' => 'nullable|string|max:50',
            'provider_name' => 'nullable|string|max:100',
            'use_mock' => 'nullable|boolean',
            'total_length' => 'nullable|integer|min:1|max:200',
            'total_width' => 'nullable|integer|min:1|max:200',
            'total_height' => 'nullable|integer|min:1|max:200',
            'total_weight' => 'nullable|numeric|min:0.01|max:50',
            'undeliverable_option' => 'nullable|in:Recycling,Return',
            'danger_type' => 'nullable|in:General,DangerLiquids,ContainsBattery,SeparateBattery',
        ]);

        $order = Order::with(['shop', 'items', 'currentLogistics'])->findOrFail($request->id);
        if ($response = $this->ensureOrderHasShop($order)) {
            return $response;
        }

        $service = new AliExpressService();
        $isDbs = strtoupper($order->logistics_type ?? '') === 'DBS';
        $useMock = (bool) $request->boolean('use_mock');

        if ($isDbs && empty($request->track_number)) {
            return $this->error('请输入运单号');
        }

        if ($isDbs) {
            $providerName = $request->input('provider_name', 'China Post');
            $result = $service->offlineShipToInTransit(
                $order->shop,
                $order,
                $request->track_number,
                $providerName
            );
        } else {
            $result = $service->shipFbs($order->shop, $order, [
                'track_number' => $request->track_number,
                'logistic_method' => $request->input('logistic_method', ''),
                'use_mock' => $useMock,
                'total_length' => $request->input('total_length'),
                'total_width' => $request->input('total_width'),
                'total_height' => $request->input('total_height'),
                'total_weight' => $request->input('total_weight'),
                'undeliverable_option' => $request->input('undeliverable_option'),
                'danger_type' => $request->input('danger_type'),
            ]);
        }

        $finalTrackingNumber = $request->track_number
            ?: data_get($result, 'data.tracking_number')
            ?: data_get($result, 'data.mark_ship.tracking_number')
            ?: $order->tracking_number;

        $this->orderLogisticsService()->syncTracking($order, $finalTrackingNumber, [
            'logistics_mode' => $isDbs ? 'DBS' : 'FBS',
            'provider_code' => $this->resolveShipProviderCode($request, $order, $isDbs),
            'provider_name' => $isDbs
                ? $request->input('provider_name', data_get($order, 'currentLogistics.provider_name') ?: 'China Post')
                : 'AliExpress',
        ]);

        $order->update([
            'tracking_number' => $finalTrackingNumber,
            'actual_ship_at' => now(),
            'marked_ship_at' => $order->marked_ship_at ?? now(),
        ]);

        if ($result['success']) {
            return $this->success([
                'ae_result' => $result['data'],
                'tracking_number' => $finalTrackingNumber,
                'mode' => $isDbs ? 'DBS_offline' : 'FBS_online',
            ], '发货成功');
        }

        return $this->success([
            'ae_result' => $result['data'] ?? null,
            'tracking_number' => $finalTrackingNumber,
            'ae_error' => $result['message'],
            'mode' => $isDbs ? 'DBS_offline' : 'FBS_online',
        ], '本地已记录发货，速卖通接口返回: ' . $result['message']);
    }

    /**
     * DBS: 通过中国邮政创建物流订单并获取运单号
     */
    public function chinaPostCreateOrder(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
            'biz_product_no' => 'nullable|string|max:10',
            'weight' => 'nullable|integer|min:1',
        ]);

        $order = Order::with(['shop', 'items', 'currentLogistics'])->findOrFail($request->id);

        $service = new ChinaPostService();
        $result = $service->createOrder($order, [
            'biz_product_no' => $request->input('biz_product_no', '001'),
            'weight' => $request->input('weight'),
        ]);

        if ($result['success']) {
            $waybillNo = $result['waybill_no'];
            $this->orderLogisticsService()->syncPrimary($order, [
                'logistics_mode' => $order->logistics_type ?: 'DBS',
                'provider_code' => 'chinapost',
                'provider_name' => 'China Post',
                'template_code' => 'offline_epacket',
                'tracking_number' => $waybillNo,
                'payload' => [
                    'chinapost' => [
                        'waybill_no' => $waybillNo,
                    ],
                ],
            ]);

            return $this->success([
                'waybill_no' => $waybillNo,
                'raw' => $result['raw'] ?? null,
            ], '邮政下单成功，运单号: ' . $waybillNo);
        }

        return $this->error($result['message'], 40000, [
            'error_code' => $result['error_code'] ?? null,
            'raw' => $result['raw'] ?? null,
        ]);
    }

    /**
     * DBS: 获取中国邮政面单 PDF
     */
    public function chinaPostLabel(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
            'page_type' => 'nullable|in:RM,A4',
        ]);

        $order = Order::findOrFail($request->id);

        if (empty($order->tracking_number)) {
            return $this->error('该订单暂无运单号，请先创建邮政订单');
        }

        $service = new ChinaPostService();
        $result = $service->getLabel(
            $order->tracking_number,
            $request->input('page_type', 'RM')
        );

        if ($result['success']) {
            return $this->success([
                'pdf_base64' => base64_encode($result['pdf_content']),
                'waybill_no' => $result['waybill_no'],
            ], '邮政面单获取成功');
        }

        return $this->error($result['message'], 40000, [
            'error_code' => $result['error_code'] ?? null,
        ]);
    }

    /**
     * DBS: 中国邮政撤单
     */
    public function chinaPostCancel(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
        ]);

        $order = Order::findOrFail($request->id);

        if (empty($order->tracking_number)) {
            return $this->error('该订单暂无运单号');
        }

        $service = new ChinaPostService();
        $result = $service->cancelOrder($order->tracking_number);

        if ($result['success']) {
            return $this->success(null, $result['message']);
        }

        return $this->error($result['message']);
    }

    /**
     * DBS: 通过雷翼/sz56t创建物流订单
     */
    public function sz56tCreateOrder(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
            'product_id' => 'nullable|string|max:50',
            'weight' => 'nullable|integer|min:1',
        ]);

        $order = Order::with(['shop', 'items', 'currentLogistics'])->findOrFail($request->id);

        $service = new Sz56tService();
        $result = $service->createOrder($order, [
            'product_id' => $request->input('product_id'),
            'weight' => $request->input('weight'),
        ]);

        if ($result['success']) {
            $this->orderLogisticsService()->syncPrimary($order, [
                'logistics_mode' => $order->logistics_type ?: 'DBS',
                'provider_code' => 'sz56t',
                'provider_name' => 'SZ56T',
                'template_code' => 'offline_leiyi',
                'external_order_id' => $result['order_id'],
                'tracking_number' => $result['tracking_number'] ?? null,
                'payload' => [
                    'sz56t' => [
                        'is_delay' => $result['is_delay'] ?? null,
                        'is_remote' => $result['is_remote'] ?? null,
                        'reference_number' => $result['reference_number'] ?? null,
                    ],
                ],
            ]);

            return $this->success([
                'order_id' => $result['order_id'],
                'tracking_number' => $result['tracking_number'],
                'is_delay' => $result['is_delay'],
                'is_remote' => $result['is_remote'],
                'reference_number' => $result['reference_number'] ?? '',
            ], $result['message']);
        }

        return $this->error($result['message'], 40000, [
            'raw' => $result['raw'] ?? null,
        ]);
    }

    /**
     * DBS: 获取雷翼面单打印URL
     */
    public function sz56tLabel(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
            'print_type' => 'nullable|string|max:30',
        ]);

        $order = Order::findOrFail($request->id);

        if (empty($order->sz56t_order_id)) {
            return $this->error('该订单暂无雷翼订单号，请先创建雷翼订单');
        }

        $service = new Sz56tService();
        $printType = $request->input('print_type', 'lab10_10');
        $labelUrl = $service->getLabelUrl($order->sz56t_order_id, $printType);

        return $this->success([
            'label_url' => $labelUrl,
            'sz56t_order_id' => $order->sz56t_order_id,
        ], '面单URL获取成功');
    }

    /**
     * DBS: 雷翼交寄预报（标记发货）
     */
    public function sz56tMarkShipped(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
        ]);

        $order = Order::findOrFail($request->id);

        $invoiceCode = $order->ae_order_id;
        $service = new Sz56tService();
        $result = $service->markShipped($invoiceCode);

        if ($result['success']) {
            return $this->success($result['data'], '交寄预报成功');
        }

        return $this->error($result['message']);
    }

    /**
     * DBS: 获取雷翼跟踪号（延迟获取）
     */
    public function sz56tGetTrackingNumber(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
        ]);

        $order = Order::findOrFail($request->id);

        $service = new Sz56tService();
        $result = $service->getTrackingNumber($order->ae_order_id);

        if ($result['success'] && !empty($result['tracking_number'])) {
            $this->orderLogisticsService()->syncTracking($order, $result['tracking_number'], [
                'logistics_mode' => $order->logistics_type ?: 'DBS',
                'provider_code' => 'sz56t',
                'provider_name' => 'SZ56T',
            ]);

            return $this->success([
                'tracking_number' => $result['tracking_number'],
            ], '跟踪号获取成功');
        }

        return $this->error($result['message'] ?? '跟踪号暂未生成，请稍后再试');
    }

    /**
     * 获取发货面单（PDF URL）
     */
    public function printLabel(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
        ]);

        $order = Order::with(['shop', 'currentLogistics'])->findOrFail($request->id);
        if ($response = $this->ensureOrderHasShop($order)) {
            return $response;
        }

        if (empty($order->logistic_order_id)) {
            $rawData = $order->raw_data ?? [];
            $logisticOrders = is_array($rawData['logistic_orders'] ?? null) ? $rawData['logistic_orders'] : [];
            $fallbackId = (!empty($logisticOrders) && !empty($logisticOrders[0]['id'])) ? (int) $logisticOrders[0]['id'] : null;
            if ($fallbackId) {
                $this->orderLogisticsService()->syncPrimary($order, [
                    'logistics_mode' => 'FBS',
                    'provider_code' => 'aliexpress',
                    'provider_name' => 'AliExpress',
                    'platform_logistic_order_id' => $fallbackId,
                ]);
                $order->load('currentLogistics');
            }
        }

        if (empty($order->logistic_order_id)) {
            return $this->error('该订单暂无 logistic_order_id，暂不可打印面单');
        }

        $service = new AliExpressService();
        $result = $service->getShippingLabel($order->shop, $order);

        if ($result['success']) {
            return $this->success([
                'label_url' => $result['label_url'],
                'used_ids' => $result['used_ids'] ?? [],
                'pdf_content' => $result['pdf_content'] ?? null,
                'raw' => $result['raw'] ?? [],
            ], '面单获取成功');
        }

        return $this->error($result['message'] ?? '面单获取失败', 40000, [
            'used_ids' => $result['used_ids'] ?? [],
            'error_code' => $result['error_code'] ?? null,
            'error_details' => $result['error_details'] ?? null,
        ]);
    }

    /**
     * 创建并打印交接单（transfer sheet）
     */
    public function transferSheet(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
        ]);

        $order = Order::with(['shop', 'currentLogistics'])->findOrFail($request->id);
        if ($response = $this->ensureOrderHasShop($order)) {
            return $response;
        }

        $result = $this->fbsWorkflowService()->createAndPrintTransferSheet($order);

        if ($result['success']) {
            return $this->success($result['data'] ?? [], $result['message'] ?? '交接单处理成功');
        }

        return $this->error($result['message'] ?? '交接单处理失败');
    }

    public function fbsWorkflow(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
        ]);

        $order = Order::with(['shop', 'items', 'currentLogistics'])->findOrFail($request->id);
        if ($response = $this->ensureOrderHasShop($order)) {
            return $response;
        }
        if ($response = $this->ensureFbsOrder($order)) {
            return $response;
        }

        $result = $this->fbsWorkflowService()->getWorkflow($order);

        if ($result['success']) {
            return $this->success($result['data'] ?? [], $result['message'] ?? 'FBS 工作流信息获取成功');
        }

        return $this->error($result['message'] ?? 'FBS 工作流信息获取失败');
    }

    public function fbsCreateLogisticOrder(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
            'total_length' => 'required|integer|min:1|max:200',
            'total_width' => 'required|integer|min:1|max:200',
            'total_height' => 'required|integer|min:1|max:200',
            'total_weight' => 'required|numeric|min:0.01|max:50',
            'undeliverable_option' => 'nullable|in:Recycling,Return',
            'danger_type' => 'nullable|in:General,DangerLiquids,ContainsBattery,SeparateBattery',
            'items' => 'required|array|min:1',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.sku_id' => 'required|integer|min:1',
            'items.*.product_source_id' => 'nullable|integer|min:0',
        ]);

        $order = Order::with(['shop', 'items', 'currentLogistics'])->findOrFail($request->id);
        if ($response = $this->ensureOrderHasShop($order)) {
            return $response;
        }
        if ($response = $this->ensureFbsOrder($order)) {
            return $response;
        }

        $result = $this->fbsWorkflowService()->createLogisticOrder($order, [
            'total_length' => $request->input('total_length'),
            'total_width' => $request->input('total_width'),
            'total_height' => $request->input('total_height'),
            'total_weight' => $request->input('total_weight'),
            'undeliverable_option' => $request->input('undeliverable_option'),
            'danger_type' => $request->input('danger_type'),
            'items' => $request->input('items', []),
        ]);

        if (!$result['success']) {
            return $this->error($result['message'] ?? '创建 FBS 发货单失败');
        }

        return $this->success($result['data'] ?? [], $result['message'] ?? 'FBS 发货单创建成功');
    }

    public function fbsCreateHandoverList(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
            'logistic_order_ids' => 'nullable|array|min:1',
            'logistic_order_ids.*' => 'integer|min:1',
            'arrival_date' => 'nullable|date',
        ]);

        $order = Order::with(['shop', 'currentLogistics'])->findOrFail($request->id);
        if ($response = $this->ensureOrderHasShop($order)) {
            return $response;
        }
        if ($response = $this->ensureFbsOrder($order)) {
            return $response;
        }

        $result = $this->fbsWorkflowService()->createHandoverList($order, [
            'logistic_order_ids' => $request->input('logistic_order_ids', []),
            'arrival_date' => $request->input('arrival_date'),
        ]);

        if (!$result['success']) {
            return $this->error($result['message'] ?? '创建交接清单失败');
        }

        return $this->success($result['data'] ?? [], $result['message'] ?? '交接清单创建成功');
    }

    public function fbsAddLogisticOrdersToHandover(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
            'handover_list_id' => 'required|integer|min:1',
            'logistic_order_ids' => 'nullable|array|min:1',
            'logistic_order_ids.*' => 'integer|min:1',
        ]);

        $order = Order::with(['shop', 'currentLogistics'])->findOrFail($request->id);
        if ($response = $this->ensureOrderHasShop($order)) {
            return $response;
        }

        $result = $this->fbsWorkflowService()->addLogisticOrdersToHandover(
            $order,
            (int) $request->handover_list_id,
            $request->input('logistic_order_ids', [])
        );

        if (!$result['success']) {
            return $this->error($result['message'] ?? '添加发货单到交接清单失败');
        }

        return $this->success($result['data'] ?? [], $result['message'] ?? '已添加到交接清单');
    }

    public function fbsRemoveLogisticOrdersFromHandover(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
            'handover_list_id' => 'required|integer|min:1',
            'logistic_order_ids' => 'nullable|array|min:1',
            'logistic_order_ids.*' => 'integer|min:1',
        ]);

        $order = Order::with(['shop', 'currentLogistics'])->findOrFail($request->id);
        if ($response = $this->ensureOrderHasShop($order)) {
            return $response;
        }

        $result = $this->fbsWorkflowService()->removeLogisticOrdersFromHandover(
            $order,
            (int) $request->handover_list_id,
            $request->input('logistic_order_ids', [])
        );

        if (!$result['success']) {
            return $this->error($result['message'] ?? '从交接清单移除发货单失败');
        }

        return $this->success($result['data'] ?? [], $result['message'] ?? '已从交接清单移除发货单');
    }

    public function fbsPrintHandoverList(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
            'handover_list_id' => 'nullable|integer|min:1',
        ]);

        $order = Order::with(['shop', 'currentLogistics'])->findOrFail($request->id);
        if ($response = $this->ensureOrderHasShop($order)) {
            return $response;
        }

        $result = $this->fbsWorkflowService()->printHandoverList(
            $order,
            (int) $request->input('handover_list_id', 0) ?: null
        );

        if ($result['success']) {
            return $this->success($result['data'] ?? [], $result['message'] ?? '交接清单标签获取成功');
        }

        return $this->error($result['message'] ?? '交接清单标签获取失败');
    }

    public function fbsReadyForPickup(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
            'handover_list_id' => 'nullable|integer|min:1',
        ]);

        $order = Order::with(['shop', 'currentLogistics'])->findOrFail($request->id);
        if ($response = $this->ensureOrderHasShop($order)) {
            return $response;
        }

        $result = $this->fbsWorkflowService()->readyForPickup(
            $order,
            (int) $request->input('handover_list_id', 0) ?: null
        );

        if (!$result['success']) {
            return $this->error($result['message'] ?? '交接清单标记待揽收失败');
        }

        return $this->success($result['data'] ?? [], $result['message'] ?? '交接清单已标记为待揽收');
    }

    public function fbsTransferHandoverList(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
            'handover_list_id' => 'nullable|integer|min:1',
        ]);

        $order = Order::with(['shop', 'currentLogistics'])->findOrFail($request->id);
        if ($response = $this->ensureOrderHasShop($order)) {
            return $response;
        }

        $result = $this->fbsWorkflowService()->transferHandoverList(
            $order,
            (int) $request->input('handover_list_id', 0) ?: null
        );

        if (!$result['success']) {
            return $this->error($result['message'] ?? '交接清单关闭失败');
        }

        return $this->success($result['data'] ?? [], $result['message'] ?? '交接清单状态已更新');
    }

    private function ensureOrderHasShop(Order $order)
    {
        if (!$order->shop) {
            return $this->error('订单关联店铺不存在');
        }

        return null;
    }

    private function ensureFbsOrder(Order $order)
    {
        if (strtoupper($order->logistics_type ?? '') === 'DBS') {
            return $this->error('当前订单不是 FBS 模式');
        }

        return null;
    }

    private function orderLogisticsService(): OrderLogisticsService
    {
        return new OrderLogisticsService();
    }

    private function fbsWorkflowService(): OrderFbsWorkflowService
    {
        return new OrderFbsWorkflowService();
    }

    private function resolveShipProviderCode(Request $request, Order $order, bool $isDbs): string
    {
        if (!$isDbs) {
            return 'aliexpress';
        }

        $providerCode = strtolower((string) $request->input('ship_provider', ''));
        if ($providerCode === 'leiyi') {
            return 'sz56t';
        }

        if ($providerCode !== '') {
            return $providerCode;
        }

        $templateCode = $order->logistics_template;
        return $this->orderLogisticsService()->resolveProviderCodeByTemplate($templateCode) ?: 'manual';
    }
}