<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunOrderSyncTask;
use App\Models\DictType;
use App\Models\Order;
use App\Models\OrderSyncTask;
use App\Models\Shop;
use App\Models\Team;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    use ApiResponse;

    protected const BACKEND_STATUS_DICT_CODE = 'order_backend_status';

    protected const STATUS_COUNT_KEYS = [
        'Unknown',
        'PlaceOrderSuccess',
        'PaymentPending',
        'WaitExamineMoney',
        'WaitGroup',
        'WaitSendGoods',
        'PartialSendGoods',
        'WaitAcceptGoods',
        'InCancel',
        'Close',
        'Complete',
        'InFrozen',
        'InIssue',
    ];

    /**
     * 订单列表（本地数据库）
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Order::with(['items', 'shop:id,name,email', 'currentLogistics']);

        $this->applyOrderPermissionScope($query, $user);

        // 筛选条件
        if ($request->filled('shop_id')) {
            $query->where('shop_id', $request->shop_id);
        }
        if ($request->filled('ae_order_id')) {
            $query->where('ae_order_id', 'like', '%' . $request->ae_order_id . '%');
        }
        if ($request->filled('tracking_number')) {
            $this->applyTrackingNumberFilter($query, $request->tracking_number);
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
        if ($request->filled('shipment_status')) {
            $query->whereHas('currentLogistics', function ($q) use ($request) {
                $q->where('logistic_status', $request->shipment_status);
            });
        }
        if ($request->filled('backend_status')) {
            $query->where('backend_status', $request->backend_status);
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
            $this->applyDisplayStatusFilter($query, $request->display_status);
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

        $this->applyOrderPermissionScope($baseQuery, $user);
        $this->applyShopFilter($baseQuery, $request);
        if ($request->filled('backend_status')) {
            $baseQuery->where('backend_status', $request->backend_status);
        }

        $total = (clone $baseQuery)->count();
        $counts = ['all' => $total];
        foreach (self::STATUS_COUNT_KEYS as $status) {
            $counts[$status] = $this->applyDisplayStatusFilter(clone $baseQuery, $status)->count();
        }

        return $this->success($counts);
    }

    public function statistics(Request $request)
    {
        $dailyDate = $request->input('daily_date', now()->format('Y-m-d'));
        $monthlyMonth = $request->input('monthly_month', now()->format('Y-m'));
        $defaultRangeStart = now()->subDays(30)->format('Y-m-d');
        $defaultRangeEnd = now()->format('Y-m-d');

        $orderCountStartDate = $request->input('order_count_start_date', $defaultRangeStart);
        $orderCountEndDate = $request->input('order_count_end_date', $defaultRangeEnd);
        $shippedStartDate = $request->input('shipped_start_date', $defaultRangeStart);
        $shippedEndDate = $request->input('shipped_end_date', $defaultRangeEnd);
        $profitStartDate = $request->input('profit_start_date', $defaultRangeStart);
        $profitEndDate = $request->input('profit_end_date', $defaultRangeEnd);

        $dailyStart = Carbon::parse($dailyDate)->startOfDay();
        $dailyEnd = Carbon::parse($dailyDate)->endOfDay();
        $monthlyStart = Carbon::parse($monthlyMonth . '-01')->startOfMonth();
        $monthlyEnd = Carbon::parse($monthlyMonth . '-01')->endOfMonth();

        $dailyQuery = $this->buildStatisticsBaseQuery($request)
            ->whereBetween('ae_created_at', [$dailyStart, $dailyEnd]);
        $monthlyQuery = $this->buildStatisticsBaseQuery($request)
            ->whereBetween('ae_created_at', [$monthlyStart, $monthlyEnd]);
        $orderCountQuery = $this->buildStatisticsBaseQuery($request)
            ->whereBetween('ae_created_at', [Carbon::parse($orderCountStartDate)->startOfDay(), Carbon::parse($orderCountEndDate)->endOfDay()]);
        $shippedQuery = $this->buildStatisticsBaseQuery($request)
            ->whereBetween('ae_created_at', [Carbon::parse($shippedStartDate)->startOfDay(), Carbon::parse($shippedEndDate)->endOfDay()])
            ->whereNotNull('actual_ship_at');
        $profitQuery = $this->buildStatisticsBaseQuery($request)
            ->whereBetween('ae_created_at', [Carbon::parse($profitStartDate)->startOfDay(), Carbon::parse($profitEndDate)->endOfDay()])
            ->whereNotNull('actual_ship_at');

        $data = [
            'daily' => [
                'date' => $dailyDate,
                'totals' => $this->getAggregateStats(clone $dailyQuery),
                'shipped_totals' => $this->getAggregateStats((clone $dailyQuery)->whereNotNull('actual_ship_at')),
            ],
            'monthly' => [
                'month' => $monthlyMonth,
                'totals' => $this->getAggregateStats(clone $monthlyQuery),
                'shipped_totals' => $this->getAggregateStats((clone $monthlyQuery)->whereNotNull('actual_ship_at')),
            ],
            'order_count' => [
                'start_date' => $orderCountStartDate,
                'end_date' => $orderCountEndDate,
                'total_orders' => (clone $orderCountQuery)->count(),
                'daily_stats' => $this->getCountByDate(clone $orderCountQuery, 'order_count'),
                'shop_stats' => $this->getCountByShop(clone $orderCountQuery, 'order_count'),
            ],
            'shipped_count' => [
                'start_date' => $shippedStartDate,
                'end_date' => $shippedEndDate,
                'total_shipped' => (clone $shippedQuery)->count(),
                'daily_stats' => $this->getCountByDate(clone $shippedQuery, 'shipped_count'),
                'shop_stats' => $this->getCountByShop(clone $shippedQuery, 'shipped_count'),
            ],
            'profit' => [
                'start_date' => $profitStartDate,
                'end_date' => $profitEndDate,
                'totals' => $this->getAggregateStats(clone $profitQuery),
                'daily_stats' => $this->getAggregateByDate(clone $profitQuery),
                'shop_stats' => $this->getAggregateByShop(clone $profitQuery),
            ],
        ];

        return $this->success($data);
    }

    /**
     * 后台状态数量统计
     */
    public function backendStatusCounts(Request $request)
    {
        $user = $request->user();
        $baseQuery = Order::query();

        $this->applyOrderPermissionScope($baseQuery, $user);
        $this->applyShopFilter($baseQuery, $request);

        if ($request->filled('display_status')) {
            $this->applyDisplayStatusFilter($baseQuery, $request->display_status);
        }

        $total = (clone $baseQuery)->count();
        $counts = ['all' => $total];

        foreach ($this->getBackendStatusKeys() as $status) {
            $counts[$status] = (clone $baseQuery)->where('backend_status', $status)->count();
        }

        return $this->success($counts);
    }

    protected function applyDisplayStatusFilter($query, string $status)
    {
        if (in_array($status, self::STATUS_COUNT_KEYS, true)) {
            $query->where('order_display_status', $status);
        }

        return $query;
    }

    protected function buildStatisticsBaseQuery(Request $request)
    {
        $query = Order::query();

        $this->applyOrderPermissionScope($query, $request->user());
        $this->applyShopFilter($query, $request);

        return $query->where('order_display_status', '!=', 'Close');
    }

    protected function applyOrderPermissionScope($query, $user): void
    {
        if ($user->hasRole('super-admin')) {
            return;
        }

        if ($this->isTeamAdmin($user)) {
            $teamIds = Team::where('admin_user_id', $user->id)->pluck('id');
            $shopIds = Shop::whereIn('team_id', $teamIds)->pluck('id');
            $query->whereIn('shop_id', $shopIds);

            return;
        }

        $shopIds = Shop::where('user_id', $user->id)->pluck('id');
        $query->whereIn('shop_id', $shopIds);
    }

    protected function applyShopFilter($query, Request $request): void
    {
        if ($request->filled('shop_id')) {
            $query->where('shop_id', $request->shop_id);
        }
    }

    protected function getBackendStatusKeys(): array
    {
        $dictType = DictType::where('code', self::BACKEND_STATUS_DICT_CODE)
            ->where('status', 1)
            ->first();

        if (!$dictType) {
            return [];
        }

        return $dictType->items()
            ->where('status', 1)
            ->orderBy('sort')
            ->pluck('value')
            ->map(fn($value) => (string) $value)
            ->filter()
            ->values()
            ->all();
    }

    protected function getAggregateStats($query): array
    {
        $stats = $query->selectRaw('
            COUNT(*) as total_orders,
            COALESCE(SUM(total_amount), 0) as total_sales,
            COALESCE(SUM(purchase_amount), 0) as total_purchase_cost,
            COALESCE(SUM(logistics_fee), 0) as total_logistics_fee,
            COALESCE(SUM(lianlian_fee), 0) as total_lianlian_fee,
            COALESCE(SUM(platform_fee + affiliate_fee), 0) as total_platform_fee,
            COALESCE(SUM(total_amount - platform_fee - affiliate_fee - lianlian_fee - purchase_amount - logistics_fee), 0) as total_profit
        ')->first();

        return [
            'total_orders' => (int) ($stats->total_orders ?? 0),
            'total_sales' => round((float) ($stats->total_sales ?? 0), 2),
            'total_purchase_cost' => round((float) ($stats->total_purchase_cost ?? 0), 2),
            'total_logistics_fee' => round((float) ($stats->total_logistics_fee ?? 0), 2),
            'total_lianlian_fee' => round((float) ($stats->total_lianlian_fee ?? 0), 2),
            'total_platform_fee' => round((float) ($stats->total_platform_fee ?? 0), 2),
            'total_profit' => round((float) ($stats->total_profit ?? 0), 2),
        ];
    }

    protected function getCountByDate($query, string $alias)
    {
        return $query
            ->selectRaw('DATE(ae_created_at) as date, COUNT(*) as ' . $alias)
            ->groupBy(DB::raw('DATE(ae_created_at)'))
            ->orderBy('date', 'desc')
            ->get();
    }

    protected function getCountByShop($query, string $alias)
    {
        return $query
            ->join('shops', 'orders.shop_id', '=', 'shops.id')
            ->selectRaw('shops.id as shop_id, shops.name as shop_name, COUNT(*) as ' . $alias)
            ->groupBy('shops.id', 'shops.name')
            ->orderBy($alias, 'desc')
            ->get();
    }

    protected function getAggregateByDate($query)
    {
        return $query
            ->selectRaw('
                DATE(ae_created_at) as date,
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_sales,
                COALESCE(SUM(purchase_amount), 0) as total_purchase_cost,
                COALESCE(SUM(logistics_fee), 0) as total_logistics_fee,
                COALESCE(SUM(total_amount - platform_fee - affiliate_fee - lianlian_fee - purchase_amount - logistics_fee), 0) as total_profit
            ')
            ->groupBy(DB::raw('DATE(ae_created_at)'))
            ->orderBy('date', 'desc')
            ->get();
    }

    protected function getAggregateByShop($query)
    {
        return $query
            ->join('shops', 'orders.shop_id', '=', 'shops.id')
            ->selectRaw('
                shops.id as shop_id,
                shops.name as shop_name,
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_sales,
                COALESCE(SUM(purchase_amount), 0) as total_purchase_cost,
                COALESCE(SUM(logistics_fee), 0) as total_logistics_fee,
                COALESCE(SUM(total_amount - platform_fee - affiliate_fee - lianlian_fee - purchase_amount - logistics_fee), 0) as total_profit
            ')
            ->groupBy('shops.id', 'shops.name')
            ->orderBy('total_profit', 'desc')
            ->get();
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

        try {
            RunOrderSyncTask::dispatch($task->id);
        } catch (\Throwable $e) {
            $task->update([
                'status' => 'failed',
                'progress' => 100,
                'message' => '同步任务启动失败: ' . $e->getMessage(),
                'finished_at' => now(),
            ]);

            return $this->error('同步任务启动失败: ' . $e->getMessage());
        }

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
            'backend_status' => 'nullable|string|max:50',
            'purchase_image' => 'nullable|string|max:500',
            'shipping_image' => 'nullable|string|max:500',
            'purchase_date' => 'nullable|date',
            'shipping_date' => 'nullable|date',
            'lianlian_fee' => 'nullable|numeric|min:0',
            'purchase_amount' => 'nullable|numeric|min:0',
            'express_fee' => 'nullable|numeric|min:0',
            'logistics_fee' => 'nullable|numeric|min:0',
            'eub_amazon_ratio' => 'nullable|numeric|min:0|max:999.99',
            'eub_base_fee' => 'nullable|numeric|min:0',
            'calculated_logistics_fee' => 'nullable|numeric|min:0',
            'logistics_fee_override' => 'nullable|boolean',
            'apply_qianze_at' => 'nullable|date',
            'ship_qianze_at' => 'nullable|date',
        ]);

        $order = Order::with('currentLogistics')->findOrFail($request->id);

        $payload = [
            'seller_comment' => $request->input('seller_comment', $order->seller_comment),
            'admin_remark' => $request->input('admin_remark', $order->admin_remark),
            'backend_status' => $request->input('backend_status') ?: '',
            'purchase_image' => $request->input('purchase_image', $order->purchase_image),
            'shipping_image' => $request->input('shipping_image', $order->shipping_image),
            'purchase_date' => $request->input('purchase_date', $order->purchase_date),
            'shipping_date' => $request->input('shipping_date', $order->shipping_date),
            'lianlian_fee' => $request->input('lianlian_fee', $order->lianlian_fee),
            'purchase_amount' => $request->input('purchase_amount', $order->purchase_amount),
            'express_fee' => $request->input('express_fee', $order->express_fee),
            'logistics_fee' => $request->input('logistics_fee', $order->logistics_fee),
            'eub_amazon_ratio' => $request->input('eub_amazon_ratio', $order->eub_amazon_ratio ?? 0),
            'eub_base_fee' => $request->input('eub_base_fee', $order->eub_base_fee ?? 0),
            'calculated_logistics_fee' => $request->input('calculated_logistics_fee', $order->calculated_logistics_fee ?? 0),
            'logistics_fee_override' => (bool) $request->input('logistics_fee_override', $order->logistics_fee_override ?? false),
            'apply_qianze_at' => $request->input('apply_qianze_at', $order->apply_qianze_at),
            'ship_qianze_at' => $request->input('ship_qianze_at', $order->ship_qianze_at),
        ];

        $order->update($payload);

        $order->load('currentLogistics');

        return $this->success($order, '订单后台信息更新成功');
    }

    /**
     * 批量更新后台状态
     */
    public function batchUpdateBackendStatus(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:orders,id',
            'backend_status' => 'required|string|max:50',
        ]);

        $query = Order::query()->whereIn('id', $request->input('ids', []));
        $this->applyOrderPermissionScope($query, $request->user());

        $updated = $query->update([
            'backend_status' => $request->input('backend_status'),
        ]);

        return $this->success([
            'updated' => $updated,
        ], '批量更新后台状态成功');
    }

    /**
     * 导出订单（CSV）
     */
    public function export(Request $request)
    {
        $user = $request->user();
        $query = Order::with(['shop:id,name,email', 'currentLogistics']);

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
            $this->applyTrackingNumberFilter($query, $request->tracking_number);
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

    private function applyTrackingNumberFilter($query, string $trackingNumber): void
    {
        $keyword = '%' . $trackingNumber . '%';

        $query->where(function ($subQuery) use ($keyword) {
            $subQuery->where('tracking_number', 'like', $keyword)
                ->orWhereHas('currentLogistics', function ($logisticsQuery) use ($keyword) {
                    $logisticsQuery->where('tracking_number', 'like', $keyword);
                });
        });
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

}
