<?php

namespace App\Http\Controllers\Api;

use App\Data\ChinaRegions;
use App\Data\ChinaRegionsPart3;
use App\Data\ChinaRegionsPart4;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderAddressBook;
use App\Models\Shop;
use App\Models\Team;
use App\Services\AliExpressService;
use App\Services\ChinaPostService;
use App\Services\OrderFbsWorkflowService;
use App\Services\OrderLogisticsService;
use App\Services\Sz56tService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderLogisticsController extends Controller
{
    use ApiResponse;

    protected function thirdPartyLog()
    {
        return Log::channel('third_party');
    }

    /**
     * 提交发货（调用速卖通 mark-ship API，同时记录本地）
     * FBS: 直接调用 AliExpress mark-ship
     * DBS: 先创建第三方物流单(雷翼/邮政) → 再调用 AliExpress offline-ship 更新状态
     */
    public function ship(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
            'track_number' => 'nullable|string',
            'logistic_method' => 'nullable|string',
            'ship_provider' => 'nullable|string',
            'provider_name' => 'nullable|string',
            'biz_product_no' => 'nullable|string',
            'product_type' => 'nullable|string',
            'api_code' => 'nullable|string',
            'sender_no' => 'nullable|string',
            'msg_type' => 'nullable|string',
            'version' => 'nullable|string',
            'user_code' => 'nullable|string',
            'product_id' => 'nullable|string',
            'weight' => 'nullable|integer|min:1|max:50000',
            'logistics_interface' => 'nullable|array',
            'sz56t_form' => 'nullable|array',
            'sz56t_form.order_returnsign' => 'nullable|in:Y,N',
            'sz56t_form.length' => 'nullable|numeric|min:1|max:200',
            'sz56t_form.width' => 'nullable|numeric|min:1|max:200',
            'sz56t_form.height' => 'nullable|numeric|min:1|max:200',
            'sz56t_form.consignee_name' => 'nullable|string',
            'sz56t_form.consignee_address' => 'nullable|string',
            'sz56t_form.consignee_telephone' => 'nullable|string',
            'sz56t_form.consignee_mobile' => 'nullable|string',
            'sz56t_form.consignee_city' => 'nullable|string',
            'sz56t_form.consignee_state' => 'nullable|string',
            'sz56t_form.consignee_postcode' => 'nullable|string',
            'sz56t_form.country' => 'nullable|string',
            'sz56t_form.consignee_email' => 'nullable|string',
            'sz56t_items' => 'nullable|array',
            'sz56t_items.*.invoice_title' => 'nullable|string',
            'sz56t_items.*.invoice_amount' => 'nullable|numeric|min:0.01|max:999999',
            'sz56t_items.*.invoice_pcs' => 'nullable|integer|min:1|max:9999',
            'sz56t_items.*.invoice_weight' => 'nullable|numeric|min:0.01|max:50000',
            'sz56t_items.*.sku_code' => 'nullable|string',
            'sz56t_items.*.invoice_currency' => 'nullable|string',
            'sz56t_items.*.origin_country' => 'nullable|string',
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
        $dbsShipment = null;

        if ($isDbs) {
            $dbsShipment = $this->prepareDbsShipment($order, $request);
            if (!$dbsShipment['success']) {
                return $this->error($dbsShipment['message'] ?? 'DBS 发货准备失败', 40000, $dbsShipment['data'] ?? []);
            }

            $result = [
                'success' => true,
                'message' => 'DBS 本地发货已记录，请手动点击按钮发送到速卖通',
                'data' => null,
            ];
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

        $finalTrackingNumber = ($dbsShipment['tracking_number'] ?? null)
            ?: $request->track_number
            ?: data_get($result, 'data.tracking_number')
            ?: data_get($result, 'data.mark_ship.tracking_number')
            ?: $order->tracking_number;

        $providerCode = $dbsShipment['provider_code'] ?? $this->resolveShipProviderCode($request, $order, $isDbs);
        $providerName = $dbsShipment['provider_name']
            ?? ($isDbs
                ? $request->input('provider_name', data_get($order, 'currentLogistics.provider_name') ?: 'China Post')
                : 'AliExpress');

        $this->orderLogisticsService()->syncTracking($order, $finalTrackingNumber, [
            'logistics_mode' => $isDbs ? 'DBS' : 'FBS',
            'provider_code' => $providerCode,
            'provider_name' => $providerName,
        ]);

        $order->update([
            'tracking_number' => $finalTrackingNumber,
            'actual_ship_at' => now(),
            'marked_ship_at' => $isDbs ? $order->marked_ship_at : ($order->marked_ship_at ?? now()),
        ]);

        if ($result['success']) {
            if ($isDbs) {
                return $this->success([
                    'ae_result' => null,
                    'provider_result' => $dbsShipment['provider_result'] ?? null,
                    'tracking_number' => $finalTrackingNumber,
                    'actual_ship_at' => optional($order->actual_ship_at)->toDateTimeString(),
                    'marked_ship_at' => optional($order->marked_ship_at)->toDateTimeString(),
                    'platform_sync_required' => true,
                    'mode' => 'DBS_local',
                ], $result['message'] ?? 'DBS 本地发货已记录');
            }

            return $this->success([
                'ae_result' => $result['data'],
                'provider_result' => $dbsShipment['provider_result'] ?? null,
                'tracking_number' => $finalTrackingNumber,
                'mode' => $isDbs ? 'DBS_offline' : 'FBS_online',
            ], '发货成功');
        }

        return $this->success([
            'ae_result' => $result['data'] ?? null,
            'provider_result' => $dbsShipment['provider_result'] ?? null,
            'tracking_number' => $finalTrackingNumber,
            'ae_error' => $result['message'],
            'mode' => $isDbs ? 'DBS_offline' : 'FBS_online',
        ], '本地已记录发货，速卖通接口返回: ' . $result['message']);
    }

    /**
     * FBS 发货（直接调用速卖通 mark-ship）
     */
    public function shipFbs(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
            'track_number' => 'nullable|string',
            'logistic_method' => 'nullable|string',
            'total_length' => 'nullable|integer|min:1|max:200',
            'total_width' => 'nullable|integer|min:1|max:200',
            'total_height' => 'nullable|integer|min:1|max:200',
            'total_weight' => 'nullable|numeric|min:0.01|max:50',
            'undeliverable_option' => 'nullable|in:Recycling,Return',
            'danger_type' => 'nullable|in:General,DangerLiquids,ContainsBattery,SeparateBattery',
            'use_mock' => 'nullable|boolean',
        ]);

        $order = Order::with(['shop', 'items', 'currentLogistics'])->findOrFail($request->id);
        if ($response = $this->ensureOrderHasShop($order)) {
            return $response;
        }
        if ($response = $this->ensureFbsOrder($order)) {
            return $response;
        }

        $useMock = (bool) $request->boolean('use_mock');
        $service = new AliExpressService();
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

        $trackingNumber = $request->track_number
            ?: data_get($result, 'data.tracking_number')
            ?: data_get($result, 'data.mark_ship.tracking_number')
            ?: $order->tracking_number;

        $this->orderLogisticsService()->syncTracking($order, $trackingNumber, [
            'logistics_mode' => 'FBS',
            'provider_code' => 'aliexpress',
            'provider_name' => 'AliExpress',
        ]);

        $order->update([
            'tracking_number' => $trackingNumber,
            'actual_ship_at' => now(),
            'marked_ship_at' => $order->marked_ship_at ?? now(),
        ]);

        if ($result['success']) {
            return $this->success([
                'ae_result' => $result['data'],
                'tracking_number' => $trackingNumber,
                'mode' => 'FBS_online',
            ], '发货成功');
        }

        return $this->success([
            'ae_result' => $result['data'] ?? null,
            'tracking_number' => $trackingNumber,
            'ae_error' => $result['message'],
            'mode' => 'FBS_online',
        ], '本地已记录发货，速卖通接口返回: ' . $result['message']);
    }

    /**
     * DBS 中国邮政发货（本地记录，不自动同步速卖通）
     */
    public function shipDbsChinaPost(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
            'track_number' => 'nullable|string',
            'biz_product_no' => 'nullable|string',
            'product_type' => 'nullable|string',
            'api_code' => 'nullable|string',
            'sender_no' => 'nullable|string',
            'msg_type' => 'nullable|string',
            'version' => 'nullable|string',
            'user_code' => 'nullable|string',
            'weight' => 'nullable|integer|min:1|max:50000',
            'logistics_interface' => 'nullable|array',
        ]);

        $order = Order::with(['shop', 'items', 'currentLogistics'])->findOrFail($request->id);
        if ($response = $this->ensureOrderHasShop($order)) {
            return $response;
        }

        if (strtoupper($order->logistics_type ?? '') !== 'DBS') {
            return $this->error('当前订单不是 DBS 模式');
        }

        $trackingNumber = trim((string) $request->input('track_number', ''));

        if ($trackingNumber === '') {
            $result = $this->createChinaPostOrder($order, [
                'biz_product_no' => $request->input('biz_product_no', '019'),
                'product_type' => $request->input('product_type', 'E邮宝'),
                'api_code' => $request->input('api_code'),
                'sender_no' => $request->input('sender_no'),
                'msg_type' => $request->input('msg_type'),
                'version' => $request->input('version'),
                'user_code' => $request->input('user_code'),
                'weight' => $request->input('weight'),
                'logistics_interface' => $request->input('logistics_interface'),
            ]);

            if (!$result['success']) {
                return $this->error($result['message'] ?? 'DBS 邮政发货失败', 40000, $result['provider_result'] ?? []);
            }

            $trackingNumber = $result['tracking_number'];
        } else {
            $this->orderLogisticsService()->syncPrimary($order, [
                'logistics_mode' => 'DBS',
                'provider_code' => 'chinapost',
                'provider_name' => 'China Post',
                'tracking_number' => $trackingNumber,
                'logistic_status' => 'created',
            ]);
        }

        $order->update([
            'tracking_number' => $trackingNumber,
            'actual_ship_at' => now(),
        ]);

        return $this->success([
            'tracking_number' => $trackingNumber,
            'actual_ship_at' => optional($order->actual_ship_at)->toDateTimeString(),
            'platform_sync_required' => true,
            'mode' => 'DBS_chinapost_local',
        ], 'DBS 邮政本地发货已记录，请手动点击按钮发送到速卖通');
    }

    /**
     * DBS 雷翼发货（本地记录，不自动同步速卖通）
     */
    public function shipDbsLeiyi(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
            'track_number' => 'nullable|string',
            'product_id' => 'nullable|string',
            'weight' => 'nullable|integer|min:1',
            'sz56t_form' => 'nullable|array',
            'sz56t_form.order_returnsign' => 'nullable|in:Y,N',
            'sz56t_form.length' => 'nullable|numeric|min:1|max:200',
            'sz56t_form.width' => 'nullable|numeric|min:1|max:200',
            'sz56t_form.height' => 'nullable|numeric|min:1|max:200',
            'sz56t_form.consignee_name' => 'nullable|string',
            'sz56t_form.consignee_address' => 'nullable|string',
            'sz56t_form.consignee_telephone' => 'nullable|string',
            'sz56t_form.consignee_mobile' => 'nullable|string',
            'sz56t_form.consignee_city' => 'nullable|string',
            'sz56t_form.consignee_state' => 'nullable|string',
            'sz56t_form.consignee_postcode' => 'nullable|string',
            'sz56t_form.country' => 'nullable|string',
            'sz56t_form.consignee_email' => 'nullable|string',
            'sz56t_items' => 'nullable|array',
            'sz56t_items.*.invoice_title' => 'nullable|string',
            'sz56t_items.*.invoice_amount' => 'nullable|numeric|min:0.01|max:999999',
            'sz56t_items.*.invoice_pcs' => 'nullable|integer|min:1|max:9999',
            'sz56t_items.*.invoice_weight' => 'nullable|numeric|min:0.01|max:50000',
            'sz56t_items.*.sku_code' => 'nullable|string',
            'sz56t_items.*.invoice_currency' => 'nullable|string',
            'sz56t_items.*.origin_country' => 'nullable|string',
        ]);

        $order = Order::with(['shop', 'items', 'currentLogistics'])->findOrFail($request->id);
        if ($response = $this->ensureOrderHasShop($order)) {
            return $response;
        }

        if (strtoupper($order->logistics_type ?? '') !== 'DBS') {
            return $this->error('当前订单不是 DBS 模式');
        }

        $trackingNumber = trim((string) $request->input('track_number', ''));

        if ($trackingNumber === '') {
            $options = $this->buildSz56tOrderOptions($request);
            if ($message = $this->validateSz56tOrderOptions($options)) {
                return $this->error($message);
            }

            $result = $this->createSz56tOrder($order, $options);

            if (!$result['success']) {
                return $this->error($result['message'] ?? 'DBS 雷翼发货失败', 40000, $result['provider_result'] ?? []);
            }

            $trackingNumber = $result['tracking_number'] ?? '';
            $providerResult = $result['provider_result'] ?? null;
        } else {
            $this->orderLogisticsService()->syncPrimary($order, [
                'logistics_mode' => 'DBS',
                'provider_code' => 'sz56t',
                'provider_name' => 'SZ56T',
                'tracking_number' => $trackingNumber,
                'logistic_status' => 'created',
            ]);
            $providerResult = null;
        }

        $order->update([
            'tracking_number' => $trackingNumber ?: $order->tracking_number,
            'actual_ship_at' => now(),
        ]);

        return $this->success([
            'tracking_number' => $trackingNumber,
            'actual_ship_at' => optional($order->actual_ship_at)->toDateTimeString(),
            'provider_result' => $providerResult,
            'platform_sync_required' => true,
            'mode' => 'DBS_leiyi_local',
        ], 'DBS 雷翼本地发货已记录，请手动点击按钮发送到速卖通');
    }

    /**
     * DBS: 手动发送发货信息到速卖通
     */
    public function dbsSyncPlatform(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
        ]);

        $order = Order::with(['shop', 'currentLogistics'])->findOrFail($request->id);
        if ($response = $this->ensureOrderHasShop($order)) {
            return $response;
        }

        if (strtoupper($order->logistics_type ?? '') !== 'DBS') {
            return $this->error('当前订单不是 DBS 模式');
        }

        if (!$order->actual_ship_at) {
            return $this->error('请先记录实际发货，再发送到速卖通');
        }

        $platformContext = $this->resolveDbsPlatformShipmentContext($order);
        if ($platformContext['current_status'] !== '') {
            return $this->error('该订单已发送到速卖通，无需重复发送');
        }

        $trackingNumber = trim((string) ($order->tracking_number ?: data_get($order, 'currentLogistics.tracking_number') ?: ''));
        if ($trackingNumber === '') {
            return $this->error('当前订单暂无运单号，请先记录实际发货');
        }

        $providerCode = (string) (
            data_get($order, 'currentLogistics.provider_code')
            ?: $this->orderLogisticsService()->resolveProviderCodeByTemplate($order->logistics_template)
            ?: 'manual'
        );

        $providerName = (string) (
            data_get($order, 'currentLogistics.provider_name')
            ?: match ($providerCode) {
                'chinapost' => 'China Post',
                'sz56t' => 'SZ56T',
                default => 'Manual',
            }
        );

        $service = new AliExpressService();
        $result = $service->offlineShipToInTransit(
            $order->shop,
            $order,
            $trackingNumber,
            $providerName
        );

        if (!$result['success']) {
            return $this->error($result['message'] ?? '发送到速卖通失败', 40000, [
                'tracking_number' => $trackingNumber,
                'provider_name' => $providerName,
                'ae_result' => $result['data'] ?? null,
            ]);
        }

        $this->orderLogisticsService()->syncTracking($order, $trackingNumber, [
            'logistics_mode' => 'DBS',
            'provider_code' => $providerCode,
            'provider_name' => $providerName,
            'payload' => $this->buildDbsPlatformShipmentPayload($order, 'in_transit', $result['data'] ?? null),
        ]);

        $order->update([
            'tracking_number' => $trackingNumber,
            'marked_ship_at' => now(),
        ]);

        $platformPayload = data_get($this->buildDbsPlatformShipmentPayload($order, 'in_transit', $result['data'] ?? null), 'aliexpress_offline_ship', []);

        return $this->success([
            'tracking_number' => $trackingNumber,
            'actual_ship_at' => optional($order->actual_ship_at)->toDateTimeString(),
            'marked_ship_at' => optional($order->marked_ship_at)->toDateTimeString(),
            'platform_status' => 'in_transit',
            'platform_status_payload' => $platformPayload,
            'ae_result' => $result['data'] ?? null,
            'mode' => 'DBS_platform_sync',
        ], $result['message'] ?? '已发送到速卖通');
    }

    /**
     * DBS: 手动发送准备取货状态到速卖通
     */
    public function dbsReadyForPickup(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
        ]);

        $order = Order::with(['shop', 'currentLogistics'])->findOrFail($request->id);
        if ($response = $this->ensureOrderHasShop($order)) {
            return $response;
        }

        if (strtoupper($order->logistics_type ?? '') !== 'DBS') {
            return $this->error('当前订单不是 DBS 模式');
        }

        $platformContext = $this->resolveDbsPlatformShipmentContext($order);
        if ($platformContext['current_status'] !== 'in_transit') {
            return $this->error('当前订单平台状态不是已发货，无法标记为准备取货');
        }

        $provider = $this->resolveDbsProvider($order);
        $trackingNumber = trim((string) ($order->tracking_number ?: data_get($order, 'currentLogistics.tracking_number') ?: ''));

        $service = new AliExpressService();
        $result = $service->offlineShipToReadyForPickup($order->shop, $order);

        if (!$result['success']) {
            return $this->error($result['message'] ?? '发送准备取货状态失败', 40000, [
                'ae_result' => $result['data'] ?? null,
            ]);
        }

        $payload = $this->buildDbsPlatformShipmentPayload($order, 'ready_for_pickup', $result['data'] ?? null);
        $this->orderLogisticsService()->syncTracking($order, $trackingNumber !== '' ? $trackingNumber : null, [
            'logistics_mode' => 'DBS',
            'provider_code' => $provider['code'],
            'provider_name' => $provider['name'],
            'payload' => $payload,
        ]);

        return $this->success([
            'tracking_number' => $trackingNumber,
            'actual_ship_at' => optional($order->actual_ship_at)->toDateTimeString(),
            'marked_ship_at' => optional($order->marked_ship_at)->toDateTimeString(),
            'platform_status' => 'ready_for_pickup',
            'platform_status_payload' => data_get($payload, 'aliexpress_offline_ship', []),
            'ae_result' => $result['data'] ?? null,
            'mode' => 'DBS_platform_ready_for_pickup',
        ], $result['message'] ?? '已发送准备取货状态到速卖通');
    }

    /**
     * DBS: 手动发送已交付状态到速卖通
     */
    public function dbsDelivered(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
        ]);

        $order = Order::with(['shop', 'currentLogistics'])->findOrFail($request->id);
        if ($response = $this->ensureOrderHasShop($order)) {
            return $response;
        }

        if (strtoupper($order->logistics_type ?? '') !== 'DBS') {
            return $this->error('当前订单不是 DBS 模式');
        }

        $platformContext = $this->resolveDbsPlatformShipmentContext($order);
        if ($platformContext['current_status'] !== 'ready_for_pickup') {
            return $this->error('当前订单平台状态不是准备取货，无法标记为已交付');
        }

        $provider = $this->resolveDbsProvider($order);
        $trackingNumber = trim((string) ($order->tracking_number ?: data_get($order, 'currentLogistics.tracking_number') ?: ''));

        $service = new AliExpressService();
        $result = $service->offlineShipToDelivered($order->shop, $order);

        if (!$result['success']) {
            return $this->error($result['message'] ?? '发送已交付状态失败', 40000, [
                'ae_result' => $result['data'] ?? null,
            ]);
        }

        $payload = $this->buildDbsPlatformShipmentPayload($order, 'delivered', $result['data'] ?? null);
        $this->orderLogisticsService()->syncTracking($order, $trackingNumber !== '' ? $trackingNumber : null, [
            'logistics_mode' => 'DBS',
            'provider_code' => $provider['code'],
            'provider_name' => $provider['name'],
            'payload' => $payload,
        ]);

        return $this->success([
            'tracking_number' => $trackingNumber,
            'actual_ship_at' => optional($order->actual_ship_at)->toDateTimeString(),
            'marked_ship_at' => optional($order->marked_ship_at)->toDateTimeString(),
            'platform_status' => 'delivered',
            'platform_status_payload' => data_get($payload, 'aliexpress_offline_ship', []),
            'ae_result' => $result['data'] ?? null,
            'mode' => 'DBS_platform_delivered',
        ], $result['message'] ?? '已发送已交付状态到速卖通');
    }

    /**
     * DBS: 生成中国邮政请求参数预览
     */
    public function chinaPostPreview(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
            'biz_product_no' => 'nullable|string',
            'product_type' => 'nullable|string',
            'api_code' => 'nullable|string',
            'sender_no' => 'nullable|string',
            'msg_type' => 'nullable|string',
            'version' => 'nullable|string',
            'user_code' => 'nullable|string',
            'weight' => 'nullable|integer|min:1',
            'logistics_interface' => 'nullable|array',
        ]);

        $order = Order::with(['shop', 'items', 'currentLogistics'])->findOrFail($request->id);
        $service = new ChinaPostService($order);
        $result = $service->previewCreateOrderRequest(
            $order,
            $this->normalizeChinaPostOrderOptions($order, $request->except(['id']))
        );

        if (!$result['success']) {
            return $this->error($result['message'] ?? '中国邮政请求参数生成失败', 40000);
        }

        return $this->success($result['request'] ?? [], '中国邮政请求参数已生成');
    }

    /**
     * DBS: 通过中国邮政创建物流订单并获取运单号
     */
    public function chinaPostCreateOrder(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
            'biz_product_no' => 'nullable|string',
            'product_type' => 'nullable|string',
            'api_code' => 'nullable|string',
            'sender_no' => 'nullable|string',
            'msg_type' => 'nullable|string',
            'version' => 'nullable|string',
            'user_code' => 'nullable|string',
            'weight' => 'nullable|integer|min:1',
            'logistics_interface' => 'nullable|array',
            'sender' => 'nullable|array',
            'receiver' => 'nullable|array',
            'items' => 'nullable|array',
        ]);

        $order = Order::with(['shop', 'items', 'currentLogistics'])->findOrFail($request->id);
        $options = $request->except(['id']);
        $result = $this->createChinaPostOrder($order, $options);

        if ($result['success']) {
            $waybillNo = $result['tracking_number'];

            return $this->success([
                'waybill_no' => $waybillNo,
                'raw' => data_get($result, 'provider_result.raw'),
            ], '邮政下单成功，运单号: ' . $waybillNo);
        }

        return $this->error($result['message'], 40000, [
            'error_code' => data_get($result, 'provider_result.error_code'),
            'raw' => data_get($result, 'provider_result.raw'),
        ]);
    }

    /**
     * DBS: 中国邮政条码分配
     */
    public function chinaPostAllocateBarcode(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
            'api_code' => 'nullable|string',
            'logistics_interface' => 'nullable|array',
        ]);

        $order = Order::with(['shop', 'items', 'currentLogistics'])->findOrFail($request->id);
        $result = $this->allocateChinaPostBarcode($order, $request->except(['id']));

        if ($result['success']) {
            return $this->success([
                'tracking_number' => $result['tracking_number'] ?? '',
                'waybill_no' => $result['tracking_number'] ?? '',
                'raw' => data_get($result, 'provider_result.raw'),
            ], $result['message'] ?? '邮政条码分配成功');
        }

        return $this->error($result['message'], 40000, [
            'error_code' => data_get($result, 'provider_result.error_code'),
            'raw' => data_get($result, 'provider_result.raw'),
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

        $service = new ChinaPostService($order);
        $result = $service->getLabel(
            $order->tracking_number,
            $request->input('page_type', 'RM')
        );

        if ($result['success']) {
            $pdfBase64 = $result['pdf_base64'] ?? null;
            if (!$pdfBase64 && isset($result['pdf_content']) && $result['pdf_content'] !== null) {
                $pdfBase64 = base64_encode($result['pdf_content']);
            }

            return $this->success([
                'pdf_base64' => $pdfBase64,
                'waybill_no' => $result['waybill_no'],
                'raw' => $result['raw'] ?? null,
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

        $service = new ChinaPostService($order);
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
            'product_id' => 'nullable|string',
            'weight' => 'nullable|integer|min:1',
            'sz56t_form' => 'nullable|array',
            'sz56t_form.order_returnsign' => 'nullable|in:Y,N',
            'sz56t_form.length' => 'nullable|numeric|min:1|max:200',
            'sz56t_form.width' => 'nullable|numeric|min:1|max:200',
            'sz56t_form.height' => 'nullable|numeric|min:1|max:200',
            'sz56t_form.consignee_name' => 'nullable|string',
            'sz56t_form.consignee_address' => 'nullable|string',
            'sz56t_form.consignee_telephone' => 'nullable|string',
            'sz56t_form.consignee_mobile' => 'nullable|string',
            'sz56t_form.consignee_city' => 'nullable|string',
            'sz56t_form.consignee_state' => 'nullable|string',
            'sz56t_form.consignee_postcode' => 'nullable|string',
            'sz56t_form.country' => 'nullable|string',
            'sz56t_form.consignee_email' => 'nullable|string',
            'sz56t_items' => 'nullable|array',
            'sz56t_items.*.invoice_title' => 'nullable|string',
            'sz56t_items.*.invoice_amount' => 'nullable|numeric|min:0.01|max:999999',
            'sz56t_items.*.invoice_pcs' => 'nullable|integer|min:1|max:9999',
            'sz56t_items.*.invoice_weight' => 'nullable|numeric|min:0.01|max:50000',
            'sz56t_items.*.sku_code' => 'nullable|string',
            'sz56t_items.*.invoice_currency' => 'nullable|string',
            'sz56t_items.*.origin_country' => 'nullable|string',
        ]);

        $order = Order::with(['shop', 'items', 'currentLogistics'])->findOrFail($request->id);
        $options = $this->buildSz56tOrderOptions($request);
        if ($message = $this->validateSz56tOrderOptions($options)) {
            return $this->error($message);
        }

        $result = $this->createSz56tOrder($order, $options);

        if ($result['success']) {
            $providerResult = $result['provider_result'] ?? [];

            return $this->success([
                'order_id' => $providerResult['order_id'] ?? null,
                'tracking_number' => $result['tracking_number'] ?? null,
                'is_delay' => $providerResult['is_delay'] ?? false,
                'is_remote' => $providerResult['is_remote'] ?? null,
                'reference_number' => $providerResult['reference_number'] ?? '',
            ], $result['message']);
        }

        return $this->error($result['message'], 40000, [
            'raw' => data_get($result, 'provider_result.raw'),
        ]);
    }

    /**
     * DBS: 获取雷翼运输方式列表
     */
    public function sz56tProducts(Request $request)
    {
        $order = null;
        if ($request->filled('id')) {
            $order = Order::with(['shop'])->find((int) $request->id);
        }

        $service = new Sz56tService($order);
        $result = $service->getProductList($request->boolean('refresh'));

        if (!$result['success']) {
            return $this->error($result['message'] ?? '获取雷翼运输方式失败');
        }

        $items = collect(is_array($result['data'] ?? null) ? $result['data'] : [])
            ->map(function ($item) {
                $productId = trim((string) data_get($item, 'product_id', ''));
                $shortName = trim((string) data_get($item, 'product_shortname', ''));
                $expressType = trim((string) data_get($item, 'express_type', ''));

                if ($productId === '') {
                    return null;
                }

                $label = $shortName !== '' ? $shortName . ' (' . $productId . ')' : $productId;

                return [
                    'product_id' => $productId,
                    'product_shortname' => $shortName,
                    'express_type' => $expressType,
                    'label' => $label,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return $this->success([
            'items' => $items,
            'cached' => (bool) ($result['cached'] ?? false),
        ], '获取雷翼运输方式成功');
    }

    /**
     * DBS: 获取雷翼面单打印URL
     */
    public function sz56tLabel(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
            'print_type' => 'nullable|string',
        ]);

        $order = Order::with('currentLogistics')->findOrFail($request->id);
        $logistics = $this->orderLogisticsService()->current($order);
        $sz56tOrderId = (string) (
            data_get($logistics, 'external_order_id')
            ?: $order->sz56t_order_id
            ?: data_get($logistics, 'payload.sz56t.order_id')
            ?: ''
        );
        $service = new Sz56tService($order);

        $this->thirdPartyLog()->info('Sz56t getLabel request', [
            'order_id' => $order->ae_order_id,
            'tracking_number' => $order->tracking_number,
            'sz56t_order_id' => $sz56tOrderId,
        ]);

        if ($sz56tOrderId === '') {
            $trackingResult = $service->getTrackingNumber((string) $order->ae_order_id);

            $this->thirdPartyLog()->info('Sz56t getLabel resolve order id by documentCode', [
                'order_id' => $order->ae_order_id,
                'tracking_number' => $order->tracking_number,
                'external_order_id' => data_get($logistics, 'external_order_id'),
                'tracking_result' => $trackingResult,
            ]);

            if (!($trackingResult['success'] ?? false) || empty($trackingResult['order_id'])) {
                return $this->error($trackingResult['message'] ?? '该订单暂无雷翼订单号，且未能根据订单号获取');
            }

            $sz56tOrderId = (string) $trackingResult['order_id'];
            $syncPayload = [
                'logistics_mode' => $order->logistics_type ?: 'DBS',
                'provider_code' => 'sz56t',
                'provider_name' => 'SZ56T',
                'template_code' => 'leiyi',
                'external_order_id' => $sz56tOrderId,
                'payload' => [
                    'sz56t' => [
                        'order_id' => $sz56tOrderId,
                        'tracking_lookup' => [
                            'document_code' => (string) $order->ae_order_id,
                            'response' => $trackingResult['raw'] ?? null,
                            'resolved_at' => now()->toDateTimeString(),
                        ],
                    ],
                ],
            ];

            if (!empty($trackingResult['tracking_number'])) {
                $syncPayload['tracking_number'] = (string) $trackingResult['tracking_number'];
            }

            $this->orderLogisticsService()->syncPrimary($order, $syncPayload);
            $order->refresh();
            $order->load('currentLogistics');
        }

        $printType = $request->input('print_type', 'lab10_10');
        $result = $service->getLabel($sz56tOrderId, $printType);

        if (!$result['success']) {
            return $this->error($result['message'] ?? '雷翼面单暂不可用', 40000, [
                'sz56t_order_id' => $sz56tOrderId,
            ]);
        }

        return $this->success([
            'label_url' => $result['label_url'] ?? null,
            'pdf_base64' => $result['pdf_base64'] ?? null,
            'sz56t_order_id' => $sz56tOrderId,
        ], '面单获取成功');
    }

    /**
     * DBS: 雷翼交寄预报（标记发货）
     */
    public function sz56tMarkShipped(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
        ]);

        $order = Order::with('currentLogistics')->findOrFail($request->id);

        $invoiceCode = $this->resolveSz56tCustomerInvoiceCode($order);
        $service = new Sz56tService($order);
        $result = $service->markShipped($invoiceCode);

        if ($result['success']) {
            $this->orderLogisticsService()->syncPrimary($order, [
                'logistics_mode' => $order->logistics_type ?: 'DBS',
                'provider_code' => 'sz56t',
                'provider_name' => 'SZ56T',
                'template_code' => 'leiyi',
                'logistic_status' => 'posted',
                'payload' => [
                    'sz56t' => [
                        'mark_shipped' => [
                            'success' => true,
                            'message' => $result['message'] ?? '交寄预报成功',
                            'response' => $result['data'] ?? null,
                            'posted_at' => now()->toDateTimeString(),
                            'order_customerinvoicecode' => $invoiceCode,
                        ],
                    ],
                ],
            ]);

            return $this->success($result['data'], $result['message'] ?? '交寄预报成功');
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

        $service = new Sz56tService($order);
        $result = $service->getTrackingNumber((string) $order->ae_order_id, $order->sz56t_order_id ? (string) $order->sz56t_order_id : null);

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
     * DBS: 取消雷翼订单
     */
    public function sz56tCancelOrder(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
            'reason' => 'nullable|string',
        ]);

        $order = Order::with('currentLogistics')->findOrFail($request->id);
        $logistics = $this->orderLogisticsService()->current($order);

        $orderId = (string) (
            data_get($logistics, 'external_order_id')
            ?: $order->sz56t_order_id
            ?: data_get($logistics, 'payload.sz56t.order_id')
            ?: ''
        );

        if ($orderId === '') {
            return $this->error('该订单暂无雷翼订单号，无法取消');
        }

        $customerId = (string) (data_get($logistics, 'payload.sz56t.customer_id') ?: '');
        $reason = $request->input('reason', 'manual cancel');

        $service = new Sz56tService($order);
        $result = $service->cancelOrder($orderId, $customerId, $reason);

        if (!$result['success']) {
            return $this->error($result['message'], 40000, [
                'raw' => $result['raw'] ?? null,
                'order_id' => $orderId,
            ]);
        }

        $this->orderLogisticsService()->syncPrimary($order, [
            'logistics_mode' => $order->logistics_type ?: 'DBS',
            'provider_code' => 'sz56t',
            'provider_name' => 'SZ56T',
            'template_code' => 'leiyi',
            'external_order_id' => null,
            'tracking_number' => null,
            'logistic_status' => 'cancelled',
            'payload' => [
                'sz56t' => [
                    'order_id' => null,
                    'cancelled_order_id' => $orderId,
                    'customer_id' => $customerId ?: ($result['customer_id'] ?? null),
                    'cancel_reason' => $reason,
                    'cancelled_at' => now()->toDateTimeString(),
                    'cancel_response' => $result['raw'] ?? null,
                ],
            ],
        ]);

        $order->forceFill([
            'actual_ship_at' => null,
            'marked_ship_at' => null,
        ])->saveQuietly();

        return $this->success([
            'order_id' => $orderId,
            'msg_code' => $result['msg_code'] ?? null,
            'raw' => $result['raw'] ?? null,
        ], $result['message'] ?? '雷翼订单取消成功');
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
            $currentLogistics = $this->orderLogisticsService()->current($order);
            $rawData = $order->raw_data ?? [];
            $logisticOrders = is_array($rawData['logistic_orders'] ?? null) ? $rawData['logistic_orders'] : [];
            $fallbackId = (int) (
                data_get($currentLogistics, 'platform_logistic_order_id')
                ?: data_get($currentLogistics, 'payload.aliexpress.logistic_orders.0.id')
                ?: ((!empty($logisticOrders) && !empty($logisticOrders[0]['id'])) ? (int) $logisticOrders[0]['id'] : 0)
            );
            if ($fallbackId) {
                $this->orderLogisticsService()->syncPrimary($order, [
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

    public function fbsSetBigBagCount(Request $request)
    {
        $request->validate([
            'id'              => 'required|exists:orders,id',
            'big_bag_count'   => 'required|integer|min:1',
            'handover_list_id' => 'nullable|integer|min:1',
        ]);

        $order = Order::with(['shop', 'currentLogistics'])->findOrFail($request->id);
        if ($response = $this->ensureOrderHasShop($order)) {
            return $response;
        }

        $result = $this->fbsWorkflowService()->setBigBagCount(
            $order,
            (int) $request->big_bag_count,
            (int) $request->input('handover_list_id', 0) ?: null
        );

        if (!$result['success']) {
            return $this->error($result['message'] ?? '设置大袋数量失败');
        }

        return $this->success($result['data'] ?? [], $result['message'] ?? '大袋数量已设置');
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

    public function addressBookList(Request $request)
    {
        $request->validate([
            'type' => 'required|in:sender,receiver',
            'keyword' => 'nullable|string',
            'nation' => 'nullable|string',
        ]);

        $query = OrderAddressBook::query()
            ->where('user_id', $request->user()->id)
            ->where('type', $request->input('type'));

        if ($request->filled('keyword')) {
            $keyword = trim((string) $request->input('keyword'));
            $query->where(function ($subQuery) use ($keyword) {
                $subQuery->where('name', 'like', '%' . $keyword . '%')
                    ->orWhere('company', 'like', '%' . $keyword . '%')
                    ->orWhere('mobile', 'like', '%' . $keyword . '%')
                    ->orWhere('phone', 'like', '%' . $keyword . '%')
                    ->orWhere('address', 'like', '%' . $keyword . '%');
            });
        }

        if ($request->filled('nation')) {
            $query->where('nation', strtoupper(trim((string) $request->input('nation'))));
        }

        return $this->success([
            'items' => $query->orderByDesc('updated_at')->orderByDesc('id')->get(),
        ]);
    }

    public function addressBookSave(Request $request)
    {
        $request->validate([
            'id' => 'nullable|integer',
            'type' => 'required|in:sender,receiver',
            'name' => 'required|string',
            'company' => 'nullable|string',
            'post_code' => 'nullable|string',
            'phone' => 'nullable|string',
            'mobile' => 'nullable|string',
            'email' => 'nullable|string',
            'id_type' => 'nullable|string',
            'id_no' => 'nullable|string',
            'nation' => 'nullable|string',
            'province' => 'nullable|string',
            'city' => 'nullable|string',
            'county' => 'nullable|string',
            'address' => 'required|string',
            'gis' => 'nullable|string',
            'linker' => 'nullable|string',
        ]);

        $user = $request->user();
        $payload = $this->normalizeAddressBookPayload($request->all());

        $entry = null;
        if ($request->filled('id')) {
            $entry = OrderAddressBook::query()
                ->where('user_id', $user->id)
                ->find($request->integer('id'));

            if (!$entry) {
                return $this->error('地址簿记录不存在');
            }
        }

        if (!$entry) {
            $entry = OrderAddressBook::query()
                ->where('user_id', $user->id)
                ->where('type', $payload['type'])
                ->where('name', $payload['name'])
                ->where('address', $payload['address'])
                ->where('mobile', $payload['mobile'])
                ->where('phone', $payload['phone'])
                ->first();
        }

        if (!$entry) {
            $entry = new OrderAddressBook();
            $entry->user_id = $user->id;
        }

        $entry->fill($payload);
        $entry->last_used_at = now();
        $entry->save();

        return $this->success([
            'item' => $entry->fresh(),
        ], '地址簿保存成功');
    }

    public function addressBookDelete(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
        ]);

        $entry = OrderAddressBook::query()
            ->where('user_id', $request->user()->id)
            ->find($request->integer('id'));

        if (!$entry) {
            return $this->error('地址簿记录不存在');
        }

        $entry->delete();

        return $this->success(null, '地址簿记录已删除');
    }

    public function addressBookRegionOptions(Request $request)
    {
        $request->validate([
            'type' => 'required|in:sender,receiver',
            'nation' => 'nullable|string',
            'province' => 'nullable|string',
            'city' => 'nullable|string',
            'county' => 'nullable|string',
        ]);

        $filters = [
            'nation' => strtoupper(trim((string) $request->input('nation', ''))),
            'province' => trim((string) $request->input('province', '')),
            'city' => trim((string) $request->input('city', '')),
            'county' => trim((string) $request->input('county', '')),
        ];

        $rows = $this->buildAddressRegionRows($request->user(), (string) $request->input('type'));

        return $this->success([
            'nations' => $this->extractRegionOptions($rows, [], 'nation'),
            'provinces' => $this->extractRegionOptions($rows, [
                'nation' => $filters['nation'],
            ], 'province'),
            'cities' => $this->extractRegionOptions($rows, [
                'nation' => $filters['nation'],
                'province' => $filters['province'],
            ], 'city'),
            'counties' => $this->extractRegionOptions($rows, [
                'nation' => $filters['nation'],
                'province' => $filters['province'],
                'city' => $filters['city'],
            ], 'county'),
            'post_codes' => $this->extractRegionOptions($rows, [
                'nation' => $filters['nation'],
                'province' => $filters['province'],
                'city' => $filters['city'],
                'county' => $filters['county'],
            ], 'post_code'),
        ]);
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

    private function resolveDbsProvider(Order $order): array
    {
        $providerCode = (string) (
            data_get($order, 'currentLogistics.provider_code')
            ?: $this->orderLogisticsService()->resolveProviderCodeByTemplate($order->logistics_template)
            ?: 'manual'
        );

        $providerName = (string) (
            data_get($order, 'currentLogistics.provider_name')
            ?: match ($providerCode) {
                'chinapost' => 'China Post',
                'sz56t' => 'SZ56T',
                default => 'Manual',
            }
        );

        return [
            'code' => $providerCode,
            'name' => $providerName,
        ];
    }

    private function resolveDbsPlatformShipmentContext(Order $order): array
    {
        $logistics = $this->orderLogisticsService()->current($order);
        $payload = is_array(data_get($logistics, 'payload')) ? data_get($logistics, 'payload') : [];
        $offlineShip = is_array(data_get($payload, 'aliexpress_offline_ship')) ? data_get($payload, 'aliexpress_offline_ship') : [];
        $currentStatus = strtolower(trim((string) ($offlineShip['current_status'] ?? '')));

        if ($currentStatus === '') {
            if (!empty($offlineShip['delivered_at'])) {
                $currentStatus = 'delivered';
            } elseif (!empty($offlineShip['ready_for_pickup_at'])) {
                $currentStatus = 'ready_for_pickup';
            } elseif (!empty($offlineShip['in_transit_at']) || $order->marked_ship_at) {
                $currentStatus = 'in_transit';
            }
        }

        return [
            'logistics' => $logistics,
            'offline_ship' => $offlineShip,
            'current_status' => $currentStatus,
        ];
    }

    private function buildDbsPlatformShipmentPayload(Order $order, string $status, $responseData = null): array
    {
        $context = $this->resolveDbsPlatformShipmentContext($order);
        $offlineShip = is_array($context['offline_ship'] ?? null) ? $context['offline_ship'] : [];
        $timestamp = now()->toDateTimeString();

        $offlineShip['current_status'] = $status;
        $offlineShip['last_response'] = $responseData;
        $offlineShip['last_synced_at'] = $timestamp;

        if (empty($offlineShip['in_transit_at']) && $order->marked_ship_at) {
            $offlineShip['in_transit_at'] = $order->marked_ship_at->toDateTimeString();
        }

        if ($status === 'in_transit' && empty($offlineShip['in_transit_at'])) {
            $offlineShip['in_transit_at'] = $timestamp;
        }

        if ($status === 'ready_for_pickup') {
            if (empty($offlineShip['in_transit_at'])) {
                $offlineShip['in_transit_at'] = $timestamp;
            }
            $offlineShip['ready_for_pickup_at'] = $timestamp;
        }

        if ($status === 'delivered') {
            if (empty($offlineShip['in_transit_at'])) {
                $offlineShip['in_transit_at'] = $timestamp;
            }
            if (empty($offlineShip['ready_for_pickup_at'])) {
                $offlineShip['ready_for_pickup_at'] = $timestamp;
            }
            $offlineShip['delivered_at'] = $timestamp;
        }

        return [
            'aliexpress_offline_ship' => $offlineShip,
        ];
    }

    private function fbsWorkflowService(): OrderFbsWorkflowService
    {
        return new OrderFbsWorkflowService();
    }

    private function normalizeAddressBookPayload(array $input): array
    {
        $fields = [
            'type', 'name', 'company', 'post_code', 'phone', 'mobile', 'email', 'id_type', 'id_no',
            'nation', 'province', 'city', 'county', 'address', 'gis', 'linker',
        ];

        $payload = [];
        foreach ($fields as $field) {
            $payload[$field] = trim((string) ($input[$field] ?? ''));
        }

        $payload['type'] = strtolower($payload['type']);
        $payload['nation'] = strtoupper($payload['nation']);

        return $payload;
    }

    private function buildAddressRegionRows($user, string $type): array
    {
        $rows = OrderAddressBook::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->get(['nation', 'province', 'city', 'county', 'post_code'])
            ->map(function (OrderAddressBook $entry) {
                return $this->normalizeAddressRegionRow($entry->toArray());
            })
            ->all();

        if ($type === 'sender') {
            $rows[] = $this->normalizeAddressRegionRow(config('services.chinapost.sender', []));

            $chinaRegions = array_merge(
                ChinaRegions::all(),
                ChinaRegions::allPart2(),
                ChinaRegionsPart3::all(),
                ChinaRegionsPart4::all()
            );
            foreach ($chinaRegions as $province => $cities) {
                foreach ($cities as $city => $counties) {
                    foreach ($counties as $county) {
                        $rows[] = [
                            'nation' => 'CN',
                            'province' => $province,
                            'city' => $city,
                            'county' => $county,
                            'post_code' => '',
                        ];
                    }
                }
            }
        }

        if ($type === 'receiver') {
            $orderRows = $this->accessibleOrderRegionQuery($user)
                ->where(function ($query) {
                    $query->whereNotNull('receiver_region')
                        ->orWhereNotNull('receiver_city')
                        ->orWhereNotNull('receiver_zip');
                })
                ->distinct()
                ->get([
                    'receiver_country as nation',
                    'receiver_region as province',
                    'receiver_city as city',
                    'receiver_zip as post_code',
                ])
                ->map(function ($row) {
                    return $this->normalizeAddressRegionRow([
                        'nation' => $row->nation,
                        'province' => $row->province,
                        'city' => $row->city,
                        'county' => '',
                        'post_code' => $row->post_code,
                    ]);
                })
                ->all();

            $rows = array_merge($rows, $orderRows);
        }

        return array_values(array_filter($rows, function (array $row) {
            return $row['nation'] !== '' || $row['province'] !== '' || $row['city'] !== '' || $row['county'] !== '' || $row['post_code'] !== '';
        }));
    }

    private function accessibleOrderRegionQuery($user)
    {
        $query = Order::query();

        if ($user->hasRole('super-admin')) {
            return $query;
        }

        if ($this->isTeamAdmin($user)) {
            $teamIds = Team::where('admin_user_id', $user->id)->pluck('id');
            $shopIds = Shop::whereIn('team_id', $teamIds)->pluck('id');

            return $query->whereIn('shop_id', $shopIds);
        }

        $shopIds = Shop::where('user_id', $user->id)->pluck('id');

        return $query->whereIn('shop_id', $shopIds);
    }

    private function extractRegionOptions(array $rows, array $filters, string $field): array
    {
        return collect($rows)
            ->filter(function (array $row) use ($filters) {
                return $this->rowMatchesRegionFilters($row, $filters);
            })
            ->pluck($field)
            ->map(function ($value) {
                return trim((string) $value);
            })
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->map(function (string $value) {
                return [
                    'label' => $value,
                    'value' => $value,
                ];
            })
            ->all();
    }

    private function rowMatchesRegionFilters(array $row, array $filters): bool
    {
        foreach ($filters as $field => $value) {
            $expected = trim((string) $value);
            if ($expected === '') {
                continue;
            }

            if (trim((string) ($row[$field] ?? '')) !== $expected) {
                return false;
            }
        }

        return true;
    }

    private function normalizeAddressRegionRow(array $row): array
    {
        return [
            'nation' => strtoupper(trim((string) ($row['nation'] ?? ''))),
            'province' => trim((string) ($row['province'] ?? '')),
            'city' => trim((string) ($row['city'] ?? '')),
            'county' => trim((string) ($row['county'] ?? '')),
            'post_code' => trim((string) ($row['post_code'] ?? '')),
        ];
    }

    private function isTeamAdmin($user): bool
    {
        return $user->hasRole('team-admin');
    }

    private function prepareDbsShipment(Order $order, Request $request): array
    {
        $providerCode = strtolower((string) $request->input('ship_provider', 'manual'));
        if ($providerCode === 'sz56t') {
            $providerCode = 'leiyi';
        }

        $trackingNumber = trim((string) $request->input('track_number', ''));

        if ($providerCode === 'chinapost') {
            if ($trackingNumber === '') {
                return $this->createChinaPostOrder($order, [
                    'biz_product_no' => $request->input('biz_product_no', '019'),
                    'product_type' => $request->input('product_type', 'E邮宝'),
                    'api_code' => $request->input('api_code'),
                    'sender_no' => $request->input('sender_no'),
                    'msg_type' => $request->input('msg_type'),
                    'version' => $request->input('version'),
                    'user_code' => $request->input('user_code'),
                    'weight' => $request->input('weight'),
                    'logistics_interface' => $request->input('logistics_interface'),
                ]);
            }

            return [
                'success' => true,
                'tracking_number' => $trackingNumber,
                'provider_code' => 'chinapost',
                'provider_name' => 'China Post',
                'provider_result' => null,
            ];
        }

        if ($providerCode === 'leiyi') {
            if ($trackingNumber === '') {
                $options = $this->buildSz56tOrderOptions($request);
                if ($message = $this->validateSz56tOrderOptions($options)) {
                    return [
                        'success' => false,
                        'message' => $message,
                    ];
                }

                $result = $this->createSz56tOrder($order, $options);

                if (!$result['success']) {
                    return $result;
                }

                if (empty($result['tracking_number'])) {
                    return [
                        'success' => false,
                        'message' => '雷翼下单成功，但暂未获取到跟踪号，无法同步速卖通状态，请稍后重试。',
                        'data' => [
                            'provider_result' => $result['provider_result'] ?? null,
                            'order_id' => data_get($result, 'provider_result.order_id'),
                        ],
                    ];
                }

                return $result;
            }

            return [
                'success' => true,
                'tracking_number' => $trackingNumber,
                'provider_code' => 'sz56t',
                'provider_name' => 'SZ56T',
                'provider_result' => null,
            ];
        }

        if ($trackingNumber === '') {
            return [
                'success' => false,
                'message' => '请输入运单号',
            ];
        }

        return [
            'success' => true,
            'tracking_number' => $trackingNumber,
            'provider_code' => 'manual',
            'provider_name' => $request->input('provider_name', data_get($order, 'currentLogistics.provider_name') ?: 'Manual'),
            'provider_result' => null,
        ];
    }

    private function normalizeChinaPostOrderOptions(Order $order, array $options = []): array
    {
        $options['biz_product_no'] = '019';

        if (!array_key_exists('product_type', $options) || trim((string) $options['product_type']) === '') {
            $options['product_type'] = 'E邮宝';
        }

        if (isset($options['logistics_interface']) && is_array($options['logistics_interface'])) {
            $options['logistics_interface']['biz_product_no'] = '019';
            $options['logistics_interface']['logistics_order_no'] = (string) ($options['logistics_interface']['logistics_order_no'] ?? $order->ae_order_id);

            if (empty($options['sender_no']) && !empty($options['logistics_interface']['sender_no'])) {
                $options['sender_no'] = (string) $options['logistics_interface']['sender_no'];
            }
        }

        return $options;
    }

    private function createChinaPostOrder(Order $order, array $options = []): array
    {
        $service = new ChinaPostService($order);
        $options = $this->normalizeChinaPostOrderOptions($order, $options);

        $result = $service->createOrder($order, $options);

        if (!$result['success']) {
            return [
                'success' => false,
                'message' => $result['message'] ?? '邮政下单失败',
                'provider_result' => $result,
            ];
        }

        $trackingNumber = (string) ($result['waybill_no'] ?? '');
        if ($trackingNumber === '') {
            return [
                'success' => false,
                'message' => '邮政下单成功，但未返回运单号',
                'provider_result' => $result,
            ];
        }

        $this->orderLogisticsService()->syncPrimary($order, [
            'logistics_mode' => $order->logistics_type ?: 'DBS',
            'provider_code' => 'chinapost',
            'provider_name' => 'China Post',
            'template_code' => 'chinapost',
            'tracking_number' => $trackingNumber,
            'logistic_status' => 'created',
            'payload' => [
                'chinapost' => [
                    'waybill_no' => $trackingNumber,
                    'biz_product_no' => '019',
                    'request' => $result['request'] ?? null,
                ],
            ],
        ]);

        return [
            'success' => true,
            'tracking_number' => $trackingNumber,
            'provider_code' => 'chinapost',
            'provider_name' => 'China Post',
            'provider_result' => $result,
        ];
    }

    private function allocateChinaPostBarcode(Order $order, array $options = []): array
    {
        $service = new ChinaPostService($order);
        $result = $service->allocateBarcode($order, $options);

        if (!$result['success']) {
            return [
                'success' => false,
                'message' => $result['message'] ?? '邮政条码分配失败',
                'provider_result' => $result,
            ];
        }

        $trackingNumber = (string) ($result['tracking_number'] ?? $result['waybill_no'] ?? '');
        if ($trackingNumber !== '') {
            $this->orderLogisticsService()->syncPrimary($order, [
                'logistics_mode' => $order->logistics_type ?: 'DBS',
                'provider_code' => 'chinapost',
                'provider_name' => 'China Post',
                'template_code' => 'chinapost',
                'tracking_number' => $trackingNumber,
                'logistic_status' => 'created',
                'payload' => [
                    'chinapost' => [
                        'waybill_no' => $trackingNumber,
                        'barcode_allocation' => $result['ret_body'] ?? [],
                    ],
                ],
            ]);
        }

        return [
            'success' => true,
            'message' => $trackingNumber !== '' ? '邮政条码分配成功' : '邮政条码分配成功，但未解析到运单号',
            'tracking_number' => $trackingNumber,
            'provider_code' => 'chinapost',
            'provider_name' => 'China Post',
            'provider_result' => $result,
        ];
    }

    private function createSz56tOrder(Order $order, array $options = []): array
    {
        $weightInKg = null;
        if (array_key_exists('weight', $options) && $options['weight'] !== null && $options['weight'] !== '') {
            $weightInKg = round(((float) $options['weight']) / 1000, 3);
        }

        $serviceForm = $this->normalizeSz56tFormForService(is_array($options['form'] ?? null) ? $options['form'] : []);
        $invoiceItems = $this->normalizeSz56tInvoiceItems(is_array($options['invoice_items'] ?? null) ? $options['invoice_items'] : []);

        $service = new Sz56tService($order);
        $result = $service->createOrder($order, [
            'product_id' => $options['product_id'] ?? null,
            'weight' => $weightInKg,
            'form' => $serviceForm,
            'invoice_items' => $invoiceItems,
        ]);

        if (!$result['success']) {
            return [
                'success' => false,
                'message' => $result['message'] ?? '雷翼下单失败',
                'provider_result' => $result,
            ];
        }

        $trackingNumber = (string) ($result['tracking_number'] ?? '');
        if ($trackingNumber === '' && !empty($result['order_id'])) {
            $trackingResult = $service->getTrackingNumber((string) $order->ae_order_id, (string) $result['order_id']);
            if (!empty($trackingResult['success']) && !empty($trackingResult['tracking_number'])) {
                $trackingNumber = (string) $trackingResult['tracking_number'];
                $result['tracking_number'] = $trackingNumber;
                $result['tracking_raw'] = $trackingResult['raw'] ?? null;
            }
        }

        $customerInvoiceCode = $this->resolveSz56tCustomerInvoiceCode($order, $options);
        $markShippedResult = $service->markShipped($customerInvoiceCode);

        $payload = [
            'sz56t' => [
                'product_id' => $options['product_id'] ?? null,
                'form' => array_merge($options['form'] ?? [], [
                    'weight' => $options['weight'] ?? null,
                ]),
                'items' => $options['invoice_items'] ?? [],
                'order_id' => $result['order_id'] ?? null,
                'customer_id' => $result['customer_id'] ?? null,
                'customer_userid' => $result['customer_userid'] ?? null,
                'is_delay' => $result['is_delay'] ?? null,
                'is_remote' => $result['is_remote'] ?? null,
                'reference_number' => $result['reference_number'] ?? null,
                'tracking_raw' => $result['tracking_raw'] ?? null,
                'mark_shipped' => [
                    'success' => $markShippedResult['success'] ?? false,
                    'message' => $markShippedResult['message'] ?? '',
                    'response' => $markShippedResult['data'] ?? ($markShippedResult['raw'] ?? null),
                    'posted_at' => !empty($markShippedResult['success']) ? now()->toDateTimeString() : null,
                    'order_customerinvoicecode' => $customerInvoiceCode,
                ],
            ],
        ];

        $syncAttributes = [
            'logistics_mode' => $order->logistics_type ?: 'DBS',
            'provider_code' => 'sz56t',
            'provider_name' => 'SZ56T',
            'template_code' => 'leiyi',
            'external_order_id' => $result['order_id'] ?? null,
            'tracking_number' => $trackingNumber ?: null,
            'logistic_status' => 'created',
            'payload' => $payload,
        ];

        if (!empty($markShippedResult['success'])) {
            $syncAttributes['logistic_status'] = 'posted';
        }

        $this->orderLogisticsService()->syncPrimary($order, $syncAttributes);

        $providerResult = $result;
        $providerResult['mark_shipped'] = [
            'success' => $markShippedResult['success'] ?? false,
            'message' => $markShippedResult['message'] ?? '',
            'response' => $markShippedResult['data'] ?? ($markShippedResult['raw'] ?? null),
            'order_customerinvoicecode' => $customerInvoiceCode,
        ];

        return [
            'success' => true,
            'tracking_number' => $trackingNumber,
            'provider_code' => 'sz56t',
            'provider_name' => 'SZ56T',
            'provider_result' => $providerResult,
            'message' => !empty($markShippedResult['success'])
                ? (($result['message'] ?? '雷翼下单成功') . '，已提交交寄预报')
                : (($result['message'] ?? '雷翼下单成功') . '，但交寄预报失败，请稍后重试'),
        ];
    }

    private function resolveSz56tCustomerInvoiceCode(Order $order, array $options = []): string
    {
        return trim((string) (
            data_get($options, 'form.order_customerinvoicecode')
            ?: data_get($order, 'currentLogistics.payload.sz56t.form.order_customerinvoicecode')
            ?: $order->ae_order_id
            ?: ''
        ));
    }

    private function normalizeSz56tFormForService(array $form): array
    {
        if (!is_array($form['orderVolumeParam'] ?? null)) {
            return $form;
        }

        $form['orderVolumeParam'] = collect($form['orderVolumeParam'])
            ->map(function ($item) {
                if (!is_array($item)) {
                    return null;
                }

                return [
                    'volume_length' => $item['volume_length'] ?? null,
                    'volume_width' => $item['volume_width'] ?? null,
                    'volume_height' => $item['volume_height'] ?? null,
                    'volume_weight' => isset($item['volume_weight']) && $item['volume_weight'] !== ''
                        ? round(((float) $item['volume_weight']) / 1000, 3)
                        : null,
                ];
            })
            ->filter(function ($item) {
                if (!is_array($item)) {
                    return false;
                }

                return $item['volume_length'] !== null
                    || $item['volume_width'] !== null
                    || $item['volume_height'] !== null
                    || $item['volume_weight'] !== null;
            })
            ->values()
            ->all();

        return $form;
    }

    private function normalizeSz56tInvoiceItems(array $items): array
    {
        return collect($items)->map(function ($item) {
            return [
                'sku' => $item['sku'] ?? '',
                'invoice_title' => $item['invoice_title'] ?? '',
                'invoice_amount' => $item['invoice_amount'] ?? null,
                'invoice_pcs' => $item['invoice_pcs'] ?? null,
                'invoice_weight' => isset($item['invoice_weight']) && $item['invoice_weight'] !== ''
                    ? round(((float) $item['invoice_weight']) / 1000, 3)
                    : null,
                'sku_code' => $item['sku_code'] ?? '',
                'hs_code' => $item['hs_code'] ?? '',
                'transaction_url' => $item['transaction_url'] ?? '',
                'invoice_currency' => $item['invoice_currency'] ?? 'USD',
                'invoiceunit_code' => $item['invoiceunit_code'] ?? 'PCS',
                'origin_country' => $item['origin_country'] ?? 'CN',
                'invoice_brand' => $item['invoice_brand'] ?? '',
                'invoice_rule' => $item['invoice_rule'] ?? '',
                'invoice_taxno' => $item['invoice_taxno'] ?? '',
                'invoice_material' => $item['invoice_material'] ?? '',
                'invoice_purpose' => $item['invoice_purpose'] ?? '',
                'invoice_export_unitprice' => $item['invoice_export_unitprice'] ?? null,
                'invoice_export_currency' => $item['invoice_export_currency'] ?? 'USD',
                'invoice_production_sales_suppliers_name' => $item['invoice_production_sales_suppliers_name'] ?? '',
                'invoice_production_sales_suppliers_credit_code' => $item['invoice_production_sales_suppliers_credit_code'] ?? '',
                'import_hs_code' => $item['import_hs_code'] ?? '',
                'invoice_imgurl' => $item['invoice_imgurl'] ?? '',
            ];
        })->all();
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

    private function buildSz56tOrderOptions(Request $request): array
    {
        return [
            'product_id' => $request->input('product_id'),
            'weight' => $request->input('weight'),
            'form' => is_array($request->input('sz56t_form')) ? $request->input('sz56t_form') : [],
            'invoice_items' => is_array($request->input('sz56t_items')) ? array_values($request->input('sz56t_items')) : [],
        ];
    }

    private function validateSz56tOrderOptions(array $options): ?string
    {
        if (trim((string) ($options['product_id'] ?? '')) === '') {
            return '请选择雷翼运输方式';
        }

        if ((int) ($options['weight'] ?? 0) < 1) {
            return '请填写总重量';
        }

        $form = $options['form'] ?? [];
        $requiredFields = [
            'consignee_name' => '请填写收件人',
            'country' => '请填写国家/地区',
            'consignee_state' => '请填写州/省',
            'consignee_city' => '请填写城市',
            'consignee_postcode' => '请填写邮编',
            'consignee_address' => '请填写详细地址',
        ];

        foreach ($requiredFields as $field => $message) {
            if (trim((string) ($form[$field] ?? '')) === '') {
                return $message;
            }
        }

        $telephone = trim((string) ($form['consignee_telephone'] ?? ''));
        $mobile = trim((string) ($form['consignee_mobile'] ?? ''));
        if ($telephone === '' && $mobile === '') {
            return '联系电话和手机号至少填写一个';
        }

        $items = $options['invoice_items'] ?? [];
        if (empty($items)) {
            return '请至少填写一条申报信息';
        }

        foreach ($items as $index => $item) {
            $rowNumber = $index + 1;
            if (trim((string) ($item['invoice_title'] ?? '')) === '') {
                return '第' . $rowNumber . '条申报信息缺少申报品名';
            }
            if ((float) ($item['invoice_amount'] ?? 0) <= 0) {
                return '第' . $rowNumber . '条申报信息的申报总金额必须大于0';
            }
            if ((int) ($item['invoice_pcs'] ?? 0) <= 0) {
                return '第' . $rowNumber . '条申报信息的数量必须大于0';
            }
            if ((float) ($item['invoice_weight'] ?? 0) <= 0) {
                return '第' . $rowNumber . '条申报信息的单件重量必须大于0';
            }
            if (trim((string) ($item['origin_country'] ?? '')) === '') {
                return '第' . $rowNumber . '条申报信息缺少原产国';
            }
        }

        return null;
    }
}