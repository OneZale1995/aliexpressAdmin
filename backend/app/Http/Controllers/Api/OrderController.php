<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderSyncTask;
use App\Models\Shop;
use App\Models\Team;
use App\Services\AliExpressService;
use App\Services\ChinaPostService;
use App\Services\Sz56tService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ApiResponse;

    /**
     * 订单列表（本地数据库）
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Order::with(['items', 'shop:id,name,email']);

        // 权限过滤
        if ($user->hasRole('super-admin')) {
            // 不加限制
        } elseif ($this->isTeamAdmin($user)) {
            $teamIds = Team::where('admin_user_id', $user->id)->pluck('id');
            $shopIds = Shop::whereIn('team_id', $teamIds)->pluck('id');
            $query->whereIn('shop_id', $shopIds);
        } else {
            $shopIds = Shop::where('user_id', $user->id)->pluck('id');
            $query->whereIn('shop_id', $shopIds);
        }

        // 筛选条件
        if ($request->filled('shop_id')) {
            $query->where('shop_id', $request->shop_id);
        }
        if ($request->filled('ae_order_id')) {
            $query->where('ae_order_id', $request->ae_order_id);
        }
        if ($request->filled('tracking_number')) {
            $query->where('tracking_number', 'like', '%' . $request->tracking_number . '%');
        }
        if ($request->filled('receiver_name')) {
            $query->where('receiver_name', 'like', '%' . $request->receiver_name . '%');
        }
        if ($request->filled('receiver_phone')) {
            $query->where('receiver_phone', 'like', '%' . $request->receiver_phone . '%');
        }
        if ($request->filled('buyer_name')) {
            $query->where('buyer_name', 'like', '%' . $request->buyer_name . '%');
        }
        if ($request->filled('buyer_phone')) {
            $query->where('buyer_phone', 'like', '%' . $request->buyer_phone . '%');
        }
        if ($request->filled('address_keyword')) {
            $kw = $request->address_keyword;
            $query->where(function ($q) use ($kw) {
                $q->where('delivery_address', 'like', '%' . $kw . '%')
                    ->orWhere('receiver_street', 'like', '%' . $kw . '%')
                    ->orWhere('receiver_city', 'like', '%' . $kw . '%')
                    ->orWhere('receiver_region', 'like', '%' . $kw . '%');
            });
        }
        if ($request->filled('shop_keyword')) {
            $kw = $request->shop_keyword;
            $query->whereHas('shop', function ($q) use ($kw) {
                $q->where('name', 'like', '%' . $kw . '%')
                    ->orWhere('email', 'like', '%' . $kw . '%');
            });
        }
        if ($request->filled('seller_comment')) {
            $query->where('seller_comment', 'like', '%' . $request->seller_comment . '%');
        }
        if ($request->filled('admin_remark')) {
            $query->where('admin_remark', 'like', '%' . $request->admin_remark . '%');
        }
        if ($request->filled('has_purchase_image')) {
            if ((string) $request->has_purchase_image === '1') {
                $query->whereNotNull('purchase_image')->where('purchase_image', '!=', '');
            } else {
                $query->where(function ($q) {
                    $q->whereNull('purchase_image')->orWhere('purchase_image', '');
                });
            }
        }
        if ($request->filled('has_shipping_image')) {
            if ((string) $request->has_shipping_image === '1') {
                $query->whereNotNull('shipping_image')->where('shipping_image', '!=', '');
            } else {
                $query->where(function ($q) {
                    $q->whereNull('shipping_image')->orWhere('shipping_image', '');
                });
            }
        }
        if ($request->filled('issue_status')) {
            $query->whereHas('items', function ($q) use ($request) {
                $q->where('issue_status', $request->issue_status);
            });
        }

        // 下单日期范围
        if ($request->filled('date_start')) {
            $query->where('ae_created_at', '>=', $request->date_start);
        }
        if ($request->filled('date_end')) {
            $query->where('ae_created_at', '<=', $request->date_end . ' 23:59:59');
        }
        if ($request->filled('purchase_date_start')) {
            $query->whereDate('purchase_date', '>=', $request->purchase_date_start);
        }
        if ($request->filled('purchase_date_end')) {
            $query->whereDate('purchase_date', '<=', $request->purchase_date_end);
        }
        if ($request->filled('shipping_date_start')) {
            $query->whereDate('shipping_date', '>=', $request->shipping_date_start);
        }
        if ($request->filled('shipping_date_end')) {
            $query->whereDate('shipping_date', '<=', $request->shipping_date_end);
        }

        // 按展示状态过滤（Tab筛选）
        if ($request->filled('display_status')) {
            $status = $request->display_status;
            switch ($status) {
                case 'WaitSendGoods':
                    $query->where('order_display_status', 'WaitSendGoods');
                    break;
                case 'PaymentPending':
                    $query->whereIn('order_display_status', ['WaitExamineMoney', 'PaymentPending']);
                    break;
                case 'MarkedShip':
                    $query->whereNotNull('marked_ship_at')->whereNull('actual_ship_at');
                    break;
                case 'PartialSendGoods':
                    $query->where('order_display_status', 'PartialSendGoods');
                    break;
                case 'Shipped':
                    $query->whereNotNull('actual_ship_at')
                          ->whereNotIn('order_display_status', ['Complete', 'Close']);
                    break;
                case 'WaitAcceptGoods':
                    $query->where('order_display_status', 'WaitAcceptGoods');
                    break;
                case 'Complete':
                    $query->where('order_display_status', 'Complete');
                    break;
                case 'InIssue':
                    $query->whereIn('order_display_status', ['InIssue', 'InFrozen']);
                    break;
                case 'Close':
                    $query->where('order_display_status', 'Close');
                    break;
            }
        }

        $query->orderBy('ae_created_at', 'desc');

        return $this->paginate($query, $request);
    }

    /**
     * 各状态订单数量统计
     */
    public function statusCounts(Request $request)
    {
        $user = $request->user();
        $baseQuery = Order::query();

        if ($user->hasRole('super-admin')) {
            // 不加限制
        } elseif ($this->isTeamAdmin($user)) {
            $teamIds = Team::where('admin_user_id', $user->id)->pluck('id');
            $shopIds = Shop::whereIn('team_id', $teamIds)->pluck('id');
            $baseQuery->whereIn('shop_id', $shopIds);
        } else {
            $shopIds = Shop::where('user_id', $user->id)->pluck('id');
            $baseQuery->whereIn('shop_id', $shopIds);
        }

        if ($request->filled('shop_id')) {
            $baseQuery->where('shop_id', $request->shop_id);
        }

        $total = (clone $baseQuery)->count();
        $counts = [
            'all' => $total,
            'WaitSendGoods' => (clone $baseQuery)->where('order_display_status', 'WaitSendGoods')->count(),
            'PaymentPending' => (clone $baseQuery)->whereIn('order_display_status', ['WaitExamineMoney', 'PaymentPending'])->count(),
            'MarkedShip' => (clone $baseQuery)->whereNotNull('marked_ship_at')->whereNull('actual_ship_at')->count(),
            'Shipped' => (clone $baseQuery)->whereNotNull('actual_ship_at')->whereNotIn('order_display_status', ['Complete', 'Close'])->count(),
            'WaitAcceptGoods' => (clone $baseQuery)->where('order_display_status', 'WaitAcceptGoods')->count(),
            'Complete' => (clone $baseQuery)->where('order_display_status', 'Complete')->count(),
            'InIssue' => (clone $baseQuery)->whereIn('order_display_status', ['InIssue', 'InFrozen'])->count(),
            'Close' => (clone $baseQuery)->where('order_display_status', 'Close')->count(),
        ];

        return $this->success($counts);
    }

    /**
     * 同步订单（从速卖通拉取）
     */
    public function sync(Request $request)
    {
        // 兼容旧接口，内部改为异步任务启动
        return $this->syncStart($request);
    }

    /**
     * 启动异步同步任务
     */
    public function syncStart(Request $request)
    {
        $user = $request->user();
        $shops = $this->getSyncableShops($user, $request->input('shop_id'));

        if ($shops->isEmpty()) {
            return $this->error('没有可同步的店铺，请先配置店铺的access_token');
        }

        $task = OrderSyncTask::create([
            'operator_user_id' => $user->id,
            'trigger_type' => 'manual',
            'status' => 'pending',
            'total_shops' => $shops->count(),
            'options' => [
                'shop_ids' => $shops->pluck('id')->values()->toArray(),
                'date_start' => $request->input('date_start'),
                'date_end' => $request->input('date_end'),
            ],
            'message' => '任务已创建，等待执行',
        ]);

        $this->launchSyncProcess($task->id);

        return $this->success([
            'task_id' => $task->id,
            'status' => $task->status,
            'total_shops' => $task->total_shops,
        ], '同步任务已启动');
    }

    /**
     * 查询同步进度
     */
    public function syncProgress(Request $request)
    {
        $user = $request->user();
        $taskId = $request->input('task_id');

        $query = OrderSyncTask::query()->orderByDesc('id');
        if (!$user->hasRole('super-admin')) {
            $query->where('operator_user_id', $user->id);
        }

        if ($taskId) {
            $query->where('id', $taskId);
        }

        $task = $query->first();
        if (!$task) {
            return $this->error('未找到同步任务');
        }

        return $this->success($task);
    }

    /**
     * 更新备注
     */
    public function updateComment(Request $request)
    {
        // 兼容旧接口，转到新的后台更新逻辑
        return $this->updateBackendFields($request);
    }

    /**
     * 后台更新订单补充信息
     */
    public function updateBackendFields(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
            'seller_comment' => 'nullable|string|max:1000',
            'admin_remark' => 'nullable|string|max:2000',
            'purchase_image' => 'nullable|string|max:500',
            'shipping_image' => 'nullable|string|max:500',
            'purchase_date' => 'nullable|date',
            'shipping_date' => 'nullable|date',
            'lianlian_fee' => 'nullable|numeric|min:0',
            'purchase_amount' => 'nullable|numeric|min:0',
            'express_fee' => 'nullable|numeric|min:0',
            'logistics_fee' => 'nullable|numeric|min:0',
            'logistics_template' => 'nullable|in:online,offline_leiyi,offline_epacket',
            'eub_amazon_ratio' => 'nullable|numeric|min:0|max:999.99',
            'eub_base_fee' => 'nullable|numeric|min:0',
            'calculated_logistics_fee' => 'nullable|numeric|min:0',
            'logistics_fee_override' => 'nullable|boolean',
            'apply_qianze_at' => 'nullable|date',
            'ship_qianze_at' => 'nullable|date',
        ]);

        $order = Order::findOrFail($request->id);

        $payload = [
            'seller_comment' => $request->input('seller_comment', $order->seller_comment),
            'admin_remark' => $request->input('admin_remark', $order->admin_remark),
            'purchase_image' => $request->input('purchase_image', $order->purchase_image),
            'shipping_image' => $request->input('shipping_image', $order->shipping_image),
            'purchase_date' => $request->input('purchase_date', $order->purchase_date),
            'shipping_date' => $request->input('shipping_date', $order->shipping_date),
            'lianlian_fee' => $request->input('lianlian_fee', $order->lianlian_fee),
            'purchase_amount' => $request->input('purchase_amount', $order->purchase_amount),
            'express_fee' => $request->input('express_fee', $order->express_fee),
            'logistics_fee' => $request->input('logistics_fee', $order->logistics_fee),
            'logistics_template' => $request->input('logistics_template', $order->logistics_template ?: 'online'),
            'eub_amazon_ratio' => $request->input('eub_amazon_ratio', $order->eub_amazon_ratio ?? 0),
            'eub_base_fee' => $request->input('eub_base_fee', $order->eub_base_fee ?? 0),
            'calculated_logistics_fee' => $request->input('calculated_logistics_fee', $order->calculated_logistics_fee ?? 0),
            'logistics_fee_override' => (bool) $request->input('logistics_fee_override', $order->logistics_fee_override ?? false),
            'apply_qianze_at' => $request->input('apply_qianze_at', $order->apply_qianze_at),
            'ship_qianze_at' => $request->input('ship_qianze_at', $order->ship_qianze_at),
        ];

        $order->update($payload);

        return $this->success($order, '订单后台信息更新成功');
    }

    /**
     * 提交发货（调用速卖通 mark-ship API，同时记录本地）
     * FBS: 直接调用 AliExpress mark-ship
     * DBS: 先创建第三方物流单(雷翼/邮政) → 再调用 AliExpress offline-ship 更新状态
     */
    public function ship(Request $request)
    {
        $request->validate([
            'id'              => 'required|exists:orders,id',
            'track_number'    => 'required|string|max:100',
            'logistic_method' => 'nullable|string|max:100',
            'ship_provider'   => 'nullable|in:aliexpress,chinapost,leiyi',
            'provider_name'   => 'nullable|string|max:100',
        ]);

        $order = Order::with('shop')->findOrFail($request->id);

        if (!$order->shop) {
            return $this->error('订单关联店铺不存在');
        }

        $aeService = new AliExpressService();
        $isDBS = strtoupper($order->logistics_type ?? '') === 'DBS';

        if ($isDBS) {
            // DBS: 调用 AliExpress offline-ship/to-in-transit
            $providerName = $request->input('provider_name', 'China Post');
            $result = $aeService->offlineShipToInTransit(
                $order->shop,
                $order,
                $request->track_number,
                $providerName
            );
        } else {
            // FBS: 调用 AliExpress mark-ship
            $result = $aeService->markShip(
                $order->shop,
                $order,
                $request->track_number,
                $request->input('logistic_method', '')
            );
        }

        // 无论接口成功与否，本地都记录发货信息
        $order->update([
            'tracking_number' => $request->track_number,
            'actual_ship_at'  => now(),
            'marked_ship_at'  => $order->marked_ship_at ?? now(),
        ]);

        if ($result['success']) {
            return $this->success([
                'ae_result' => $result['data'],
                'tracking_number' => $request->track_number,
                'mode' => $isDBS ? 'DBS_offline' : 'FBS_online',
            ], '发货成功');
        }

        // API 失败但本地已记录
        return $this->success([
            'ae_result' => $result['data'] ?? null,
            'tracking_number' => $request->track_number,
            'ae_error' => $result['message'],
            'mode' => $isDBS ? 'DBS_offline' : 'FBS_online',
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

        $order = Order::with(['shop', 'items'])->findOrFail($request->id);

        $service = new ChinaPostService();
        $result = $service->createOrder($order, [
            'biz_product_no' => $request->input('biz_product_no', '001'),
            'weight' => $request->input('weight'),
        ]);

        if ($result['success']) {
            $waybillNo = $result['waybill_no'];
            $order->update([
                'tracking_number' => $waybillNo,
                'logistics_template' => 'offline_epacket',
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
            // 返回 base64 编码的 PDF
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

        $order = Order::with(['shop', 'items'])->findOrFail($request->id);

        $service = new Sz56tService();
        $result = $service->createOrder($order, [
            'product_id' => $request->input('product_id'),
            'weight' => $request->input('weight'),
        ]);

        if ($result['success']) {
            $updateData = [
                'sz56t_order_id' => $result['order_id'],
                'logistics_template' => 'offline_leiyi',
            ];

            if (!empty($result['tracking_number'])) {
                $updateData['tracking_number'] = $result['tracking_number'];
            }

            $order->update($updateData);

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
            $order->update(['tracking_number' => $result['tracking_number']]);

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

        $order = Order::with('shop')->findOrFail($request->id);

        if (!$order->shop) {
            return $this->error('订单关联店铺不存在');
        }

        if (empty($order->logistic_order_id)) {
            $rawData = $order->raw_data ?? [];
            $logisticOrders = is_array($rawData['logistic_orders'] ?? null) ? $rawData['logistic_orders'] : [];
            $fallbackId = (!empty($logisticOrders) && !empty($logisticOrders[0]['id'])) ? (int) $logisticOrders[0]['id'] : null;
            if ($fallbackId) {
                $order->update(['logistic_order_id' => $fallbackId]);
                $order->refresh();
            }
        }

        if (empty($order->logistic_order_id)) {
            return $this->error('该订单暂无 logistic_order_id，暂不可打印面单');
        }

        $service = new AliExpressService();
        $result = $service->getShippingLabel($order->shop, $order);

        if ($result['success']) {
            return $this->success([
                'label_url'   => $result['label_url'],
                'used_ids'    => $result['used_ids'] ?? [],
                'pdf_content' => $result['pdf_content'] ?? null,
                'raw'         => $result['raw'] ?? [],
            ], '面单获取成功');
        }

        return $this->error($result['message'] ?? '面单获取失败', 40000, [
            'used_ids' => $result['used_ids'] ?? [],
            'error_code' => $result['error_code'] ?? null,
            'error_details' => $result['error_details'] ?? null,
        ]);
    }

    /**
     * 导出订单（CSV）
     */
    public function export(Request $request)
    {
        $user = $request->user();
        $query = Order::with(['shop:id,name,email']);

        if ($user->hasRole('super-admin')) {
            // no-op
        } elseif ($this->isTeamAdmin($user)) {
            $teamIds = Team::where('admin_user_id', $user->id)->pluck('id');
            $shopIds = Shop::whereIn('team_id', $teamIds)->pluck('id');
            $query->whereIn('shop_id', $shopIds);
        } else {
            $shopIds = Shop::where('user_id', $user->id)->pluck('id');
            $query->whereIn('shop_id', $shopIds);
        }

        if ($request->filled('shop_id')) {
            $query->where('shop_id', $request->shop_id);
        }
        if ($request->filled('display_status')) {
            $query->where('order_display_status', $request->display_status);
        }
        if ($request->filled('date_start')) {
            $query->where('ae_created_at', '>=', $request->date_start);
        }
        if ($request->filled('date_end')) {
            $query->where('ae_created_at', '<=', $request->date_end . ' 23:59:59');
        }
        if ($request->filled('ae_order_id')) {
            $query->where('ae_order_id', $request->ae_order_id);
        }
        if ($request->filled('tracking_number')) {
            $query->where('tracking_number', 'like', '%' . $request->tracking_number . '%');
        }

        $rows = $query->orderByDesc('id')->limit(10000)->get();
        $filename = 'orders_' . date('YmdHis') . '.csv';

        $headers = [
            '店铺', '店主邮箱', '平台单号', '订单状态', '买家', '收件人', '国际单号',
            '物流模板', '物流费(最终)', '物流费(计算)', '人工覆盖',
            '连连费', '采购额', '快递费', '下单时间', '后台备注',
        ];

        $csv = chr(0xEF) . chr(0xBB) . chr(0xBF);
        $csv .= implode(',', array_map(fn($h) => '"' . str_replace('"', '""', $h) . '"', $headers)) . "\n";

        foreach ($rows as $o) {
            $line = [
                $o->shop->name ?? '',
                $o->shop->email ?? '',
                $o->ae_order_id,
                $o->order_display_status,
                $o->buyer_name,
                $o->receiver_name,
                $o->tracking_number,
                $o->logistics_template,
                $o->logistics_fee,
                $o->calculated_logistics_fee,
                $o->logistics_fee_override ? '是' : '否',
                $o->lianlian_fee,
                $o->purchase_amount,
                $o->express_fee,
                optional($o->ae_created_at)->format('Y-m-d H:i:s'),
                $o->admin_remark,
            ];
            $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', (string) $v) . '"', $line)) . "\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function isTeamAdmin($user): bool
    {
        return $user->hasRole('team-admin');
    }

    private function getSyncableShops($user, $shopId = null)
    {
        if ($shopId) {
            return Shop::where('id', $shopId)
                ->whereNotNull('access_token')
                ->where('access_token', '!=', '')
                ->get();
        }

        if ($user->hasRole('super-admin')) {
            return Shop::whereNotNull('access_token')->where('access_token', '!=', '')->get();
        }

        if ($this->isTeamAdmin($user)) {
            $teamIds = Team::where('admin_user_id', $user->id)->pluck('id');
            return Shop::whereIn('team_id', $teamIds)
                ->whereNotNull('access_token')
                ->where('access_token', '!=', '')
                ->get();
        }

        return Shop::where('user_id', $user->id)
            ->whereNotNull('access_token')
            ->where('access_token', '!=', '')
            ->get();
    }

    private function launchSyncProcess(int $taskId): void
    {
        $php = PHP_BINARY;
        $artisan = base_path('artisan');
        $command = '"' . $php . '" "' . $artisan . '" order:sync-task ' . $taskId;

        if (strncasecmp(PHP_OS_FAMILY, 'Windows', 7) === 0) {
            // Windows 下后台启动
            pclose(popen('start "" /B ' . $command . ' > NUL 2>&1', 'r'));
            return;
        }

        exec($command . ' > /dev/null 2>&1 &');
    }
}
