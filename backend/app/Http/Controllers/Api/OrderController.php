<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RunOrderSyncTask;
use App\Models\DictType;
use App\Models\Order;
use App\Models\OrderSyncTask;
use App\Models\OrderBlacklist;
use App\Models\Shop;
use App\Models\SystemConfig;
use App\Models\Team;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    use ApiResponse;

    protected const ORDER_DISPLAY_STATUS_DICT_CODE = 'ae_order_display_status';

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
                $query->whereNotNull('purchase_image')->whereRaw('JSON_LENGTH(purchase_image) > 0');
            } else {
                $query->where(function ($q) {
                    $q->whereNull('purchase_image')->orWhereRaw('JSON_LENGTH(purchase_image) = 0');
                });
            }
        }
        if ($request->filled('has_shipping_image')) {
            if ((string) $request->has_shipping_image === '1') {
                $query->whereNotNull('shipping_image')->whereRaw('JSON_LENGTH(shipping_image) > 0');
            } else {
                $query->where(function ($q) {
                    $q->whereNull('shipping_image')->orWhereRaw('JSON_LENGTH(shipping_image) = 0');
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
        } else {
            $query->where(function ($q) {
                $q->where('backend_status', '!=', 'abandoned')
                  ->orWhereNull('backend_status')
                  ->orWhere('backend_status', '');
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
            $this->applyDisplayStatusFilter($query, $request->display_status);
        }

        $query->orderBy('ae_created_at', 'desc');

        $page = (int) $request->get('page', 1);
        $limit = (int) $request->get('limit', 20);

        $total = $query->count();
        $items = $query->offset(($page - 1) * $limit)->limit($limit)->get();

        $this->attachBlacklistMatches($items, $user);

        return $this->success([
            'total' => $total,
            'items' => $items,
        ]);
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
        } else {
            $baseQuery->where(function ($q) {
                $q->where('backend_status', '!=', 'abandoned')
                  ->orWhereNull('backend_status')
                  ->orWhere('backend_status', '');
            });
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
                'total_items' => (int) DB::table('order_items')
                    ->whereIn('order_id', (clone $orderCountQuery)->select('orders.id'))
                    ->sum('quantity'),
                'daily_stats' => $this->getCountByDate(clone $orderCountQuery, 'order_count'),
                'shop_stats' => $this->getCountByShop(clone $orderCountQuery, 'order_count'),
            ],
            'shipped_count' => [
                'start_date' => $shippedStartDate,
                'end_date' => $shippedEndDate,
                'total_shipped' => (clone $shippedQuery)->count(),
                'total_items' => (int) DB::table('order_items')
                    ->whereIn('order_id', (clone $shippedQuery)->select('orders.id'))
                    ->sum('quantity'),
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

        $total = (clone $baseQuery)->where(function ($q) {
            $q->where('backend_status', '!=', 'abandoned')
              ->orWhereNull('backend_status')
              ->orWhere('backend_status', '');
        })->count();
        $counts = ['all' => $total];

        foreach ($this->getBackendStatusKeys() as $status) {
            $counts[$status] = (clone $baseQuery)->where('backend_status', $status)->count();
        }

        return $this->success($counts);
    }

    protected function applyDisplayStatusFilter($query, string $status)
    {
        if (in_array($status, self::STATUS_COUNT_KEYS, true)) {
            if ($status === 'InIssue') {
                $query->where(function ($q) {
                    $q->where('order_display_status', 'InIssue')
                      ->orWhereHas('items', function ($sub) {
                          $sub->where('issue_status', 'InProcess');
                      });
                });
            } else {
                $query->where('order_display_status', $status);
            }
        }

        return $query;
    }

    protected function buildStatisticsBaseQuery(Request $request)
    {
        $query = Order::query();

        $this->applyOrderPermissionScope($query, $request->user());
        $this->applyShopFilter($query, $request);

        $this->applyNonAbandonedOrderFilter($query);

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
        $itemQuery = clone $query;

        $stats = $query->selectRaw('
            COUNT(*) as total_orders,
            COALESCE(SUM(total_amount), 0) as total_sales,
            COALESCE(SUM(purchase_amount), 0) as total_purchase_cost,
            COALESCE(SUM(logistics_fee), 0) as total_logistics_fee,
            COALESCE(SUM(lianlian_fee), 0) as total_lianlian_fee,
            COALESCE(SUM(platform_fee + affiliate_fee), 0) as total_platform_fee,
            COALESCE(SUM(total_amount - platform_fee - affiliate_fee - purchase_amount - logistics_fee), 0) as total_profit
        ')->first();

        $totalItems = (int) DB::table('order_items')
            ->whereIn('order_id', $itemQuery->select('orders.id'))
            ->sum('quantity');

        return [
            'total_orders' => (int) ($stats->total_orders ?? 0),
            'total_items' => $totalItems,
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
            ->leftJoin('order_items', 'orders.id', '=', 'order_items.order_id')
            ->selectRaw('DATE(orders.ae_created_at) as date, COUNT(DISTINCT orders.id) as ' . $alias . ', COALESCE(SUM(order_items.quantity), 0) as item_count')
            ->groupBy(DB::raw('DATE(orders.ae_created_at)'))
            ->orderBy('date', 'desc')
            ->get();
    }

    protected function getCountByShop($query, string $alias)
    {
        return $query
            ->join('shops', 'orders.shop_id', '=', 'shops.id')
            ->leftJoin('order_items', 'orders.id', '=', 'order_items.order_id')
            ->selectRaw('shops.id as shop_id, shops.name as shop_name, COUNT(DISTINCT orders.id) as ' . $alias . ', COALESCE(SUM(order_items.quantity), 0) as item_count')
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
                COALESCE(SUM(total_amount - platform_fee - affiliate_fee - purchase_amount - logistics_fee), 0) as total_profit
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
                COALESCE(SUM(total_amount - platform_fee - affiliate_fee - purchase_amount - logistics_fee), 0) as total_profit
            ')
            ->groupBy('shops.id', 'shops.name')
            ->orderBy('total_profit', 'desc')
            ->get();
    }

    protected function applyNonAbandonedOrderFilter($query): void
    {
        $query->where(function ($q) {
            $q->where('backend_status', '!=', 'abandoned')
              ->orWhereNull('backend_status')
              ->orWhere('backend_status', '');
        });
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
        $shopId = $request->input('shop_id');
        $isSingleShop = !empty($shopId);

        if (!$isSingleShop) {
            // 批量同步：检查是否有未完成的任务
            $existingTask = OrderSyncTask::query()
                ->where('operator_user_id', $user->id)
                ->whereIn('status', ['pending', 'running'])
                ->orderByDesc('id')
                ->first();

            if ($existingTask) {
                $isStaleRunning = $existingTask->status === 'running'
                    && $existingTask->updated_at->diffInHours(now()) >= 1;
                $isStalePending = $existingTask->status === 'pending'
                    && $existingTask->updated_at->diffInMinutes(now()) >= 10;

                if ($isStaleRunning || $isStalePending) {
                    $existingTask->update([
                        'status' => 'completed',
                        'progress' => $existingTask->progress,
                        'finished_at' => now(),
                        'message' => '同步完成（任务中断自动恢复）',
                    ]);
                } else {
                    return $this->success([
                        'task_id' => $existingTask->id,
                        'status' => $existingTask->status,
                        'total_shops' => $existingTask->total_shops,
                    ], '已有同步任务正在执行，已返回当前任务');
                }
            }
        }

        $shops = $this->getSyncableShops($user, $shopId);

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
            RunOrderSyncTask::dispatch($task->id)->onQueue('orders');
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
            'purchase_image' => 'nullable',
            'shipping_image' => 'nullable',
            'purchase_date' => 'nullable|date',
            'shipping_date' => 'nullable|date',
            'purchase_amount' => 'nullable|numeric|min:0',
            'logistics_fee' => 'nullable|numeric|min:0',
            'weight' => 'nullable|numeric|min:0',
            'apply_qianze_at' => 'nullable|date',
            'ship_qianze_at' => 'nullable|date',
        ]);

        $order = Order::with('currentLogistics')->findOrFail($request->id);

        $payload = [
            'seller_comment' => $request->input('seller_comment', $order->seller_comment),
            'admin_remark' => $request->input('admin_remark', $order->admin_remark),
            'backend_status' => $request->input('backend_status') ?: '',
            'purchase_image' => $this->normalizeImagePayload($request, 'purchase_image', $order),
            'shipping_image' => $this->normalizeImagePayload($request, 'shipping_image', $order),
            'purchase_date' => $request->input('purchase_date', $order->purchase_date),
            'shipping_date' => $request->input('shipping_date', $order->shipping_date),
            'purchase_amount' => $request->input('purchase_amount', $order->purchase_amount),
            'logistics_fee' => $request->input('logistics_fee', $order->logistics_fee),
            'weight' => $request->input('weight', $order->weight),
            'apply_qianze_at' => $request->input('apply_qianze_at', $order->apply_qianze_at),
            'ship_qianze_at' => $request->input('ship_qianze_at', $order->ship_qianze_at),
        ];

        $order->update($payload);

        $order->load('currentLogistics');

        return $this->success($order, '订单后台信息更新成功');
    }

    public function deleteImage(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:orders,id',
            'url' => 'required|string',
            'type' => 'required|in:purchase_image,shipping_image',
        ]);

        $order = Order::findOrFail($request->id);
        $field = $request->type;
        $existing = $order->$field;
        $images = is_array($existing) ? $existing : [];

        $urlToDelete = $request->url;
        $images = array_values(array_filter($images, fn($url) => $url !== $urlToDelete));

        $order->update([$field => empty($images) ? null : $images]);

        // 尝试删除服务器上的文件
        $path = parse_url($urlToDelete, PHP_URL_PATH);
        if ($path) {
            $storagePath = ltrim(preg_replace('#^/storage/#', '', $path), '/');
            if ($storagePath) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($storagePath);
            }
        }

        return $this->success(['images' => $images], '图片已删除');
    }

    private function normalizeImagePayload(Request $request, string $field, Order $order): mixed
    {
        if (!$request->has($field)) {
            return $order->$field;
        }

        $value = $request->input($field);
        if ($value === null || $value === '' || $value === '[]') {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (is_array($value)) {
            return array_values(array_filter($value));
        }

        return $order->$field;
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

    public function enrichSkuAttributes(Request $request)
    {
        $request->validate(['order_id' => 'required|integer|exists:orders,id']);

        $order = Order::with(['items', 'shop'])->findOrFail($request->order_id);
        if (!$order->shop) {
            return $this->error('订单无关联店铺');
        }

        $service = new \App\Services\AliExpressService();
        $enriched = $service->enrichOrderItemSkuAttributes($order->shop, $order);

        return $this->success(['enriched' => $enriched], "已补充 {$enriched} 个商品的SKU属性");
    }

    /**
     * 导出订单（CSV）
     */
    public function export(Request $request)
    {
        $user = $request->user();
        $query = Order::with(['items', 'shop:id,name,email', 'currentLogistics']);

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
        $orderDisplayStatusLabelMap = $this->getDictLabelMap(self::ORDER_DISPLAY_STATUS_DICT_CODE);
        $estimatedReceiptRate = $this->getEstimatedReceiptRate();

        $headers = [
            '店铺', '店主邮箱', '平台单号', '订单状态', 'SKU编码', 'SKU属性', '数量', '买家', '收件人', '国际单号',
            '物流模板', '销售额', '手续费', '回款', '预估回款', '采购', '物流费', '利润', '利润率', '下单时间', '后台备注',
        ];

        $csv = chr(0xEF) . chr(0xBB) . chr(0xBF);
        $csv .= implode(',', array_map(fn($h) => '"' . str_replace('"', '""', $h) . '"', $headers)) . "\n";

        foreach ($rows as $o) {
            [$skuCodes, $skuSpecs, $skuQuantities] = $this->buildOrderExportSkuColumns($o);
            $line = [
                $o->shop->name ?? '',
                $o->shop->email ?? '',
                $o->ae_order_id,
                $this->translateByDictLabel($o->order_display_status, $orderDisplayStatusLabelMap),
                $skuCodes,
                $skuSpecs,
                $skuQuantities,
                $o->buyer_name,
                $o->receiver_name,
                $o->tracking_number,
                $o->logistics_template,
                $this->formatMoney($o->total_amount),
                $this->calcOrderFee($o),
                $this->calcOrderReceipt($o),
                $this->calcOrderEstimatedReceipt($o, $estimatedReceiptRate),
                $this->formatMoney($o->purchase_amount),
                $this->formatMoney($o->logistics_fee),
                $this->calcOrderProfit($o),
                $this->calcOrderProfitRate($o),
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

    private function buildOrderExportSkuColumns(Order $order): array
    {
        $items = collect($order->items ?? []);

        $skuCodes = $items->map(function ($item) {
            return trim((string) ($item->sku_code ?: $item->ae_sku_id ?: ''));
        })->filter(fn($value) => $value !== '')->implode('； ');

        $skuSpecs = $items->map(function ($item) {
            return $this->buildOrderItemSpecText($item);
        })->filter(fn($value) => $value !== '')->implode('； ');

        $quantities = $items->map(function ($item) {
            return (string) (int) ($item->quantity ?: 1);
        })->implode('； ');

        return [$skuCodes, $skuSpecs, $quantities];
    }

    private function getDictLabelMap(string $code): array
    {
        return collect(DictType::getItems($code))
            ->mapWithKeys(function (array $item) {
                return [(string) ($item['value'] ?? '') => (string) ($item['label'] ?? '')];
            })
            ->filter(fn($label, $value) => $value !== '' && $label !== '')
            ->all();
    }

    private function translateByDictLabel($value, array $labelMap): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $stringValue = (string) $value;
        return $labelMap[$stringValue] ?? $stringValue;
    }

    private function buildOrderItemSpecText($item): string
    {
        $skuAttributes = is_array($item->sku_attributes) ? $item->sku_attributes : [];
        if (!empty($skuAttributes)) {
            return collect($skuAttributes)
                ->filter(fn($value) => $value !== null && $value !== '')
                ->map(fn($value, $key) => $key . ': ' . $value)
                ->implode(' | ');
        }

        return '';
    }

    private function calcOrderProfit(Order $order): string
    {
        $profit = (float) $order->total_amount
            - (float) $order->platform_fee
            - (float) $order->affiliate_fee
            - (float) $order->purchase_amount
            - (float) $order->logistics_fee;

        return number_format($profit, 2, '.', '');
    }

    private function calcOrderFee(Order $order): string
    {
        $fee = (float) $order->platform_fee + (float) $order->affiliate_fee;

        return number_format($fee, 2, '.', '');
    }

    private function calcOrderReceipt(Order $order): string
    {
        return $this->formatMoney($order->estimate_revenue);
    }

    private function calcOrderEstimatedReceipt(Order $order, float $estimatedReceiptRate): string
    {
        $amount = (float) $order->total_amount * $estimatedReceiptRate;

        return number_format($amount, 2, '.', '');
    }

    private function calcOrderProfitRate(Order $order): string
    {
        $base = (float) $order->total_amount;
        if ($base <= 0) {
            return '0.00%';
        }

        $profit = (float) $this->calcOrderProfit($order);

        return number_format(($profit / $base) * 100, 2, '.', '') . '%';
    }

    private function getEstimatedReceiptRate(): float
    {
        $configuredRate = (float) SystemConfig::getByKey('estimated_receipt_rate', 0.908);

        return $configuredRate > 0 ? $configuredRate : 0.908;
    }

    private function formatMoney($value): string
    {
        return number_format((float) $value, 2, '.', '');
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
        $query = Shop::query()
            ->whereNotNull('access_token')
            ->where('access_token', '!=', '');

        if (!$user->hasRole('super-admin')) {
            if ($this->isTeamAdmin($user)) {
                $teamIds = Team::where('admin_user_id', $user->id)->pluck('id');
                $query->whereIn('team_id', $teamIds);
            } else {
                $query->where('user_id', $user->id);
            }
        }

        if ($shopId) {
            $query->whereKey($shopId);
        }

        return $query->get();
    }

    private function attachBlacklistMatches($orders, $user): void
    {
        if ($orders->isEmpty()) {
            return;
        }

        $teamIds = [];
        if ($user->hasRole('super-admin')) {
            $teamIds = Team::pluck('id')->toArray();
        } elseif ($this->isTeamAdmin($user)) {
            $teamIds = Team::where('admin_user_id', $user->id)->pluck('id')->toArray();
        } else {
            $teamIds = $user->teams()->pluck('teams.id')->toArray();
        }

        if (empty($teamIds)) {
            return;
        }

        $blacklistEntries = OrderBlacklist::whereIn('team_id', $teamIds)->get();

        if ($blacklistEntries->isEmpty()) {
            return;
        }

        foreach ($orders as $order) {
            $matches = [];

            foreach ($blacklistEntries as $entry) {
                if (!empty($entry->name) && (
                    $this->matchName($entry->name, $order->buyer_name)
                    || $this->matchName($entry->name, $order->receiver_name)
                )) {
                    $matches[] = [
                        'entry_id' => $entry->id,
                        'field' => 'name',
                        'matched_value' => $entry->name,
                        'remark' => $entry->remark,
                    ];
                }

                if (!empty($entry->phone) && (
                    $this->matchPhone($entry->phone, $order->buyer_phone)
                    || $this->matchPhone($entry->phone, $order->receiver_phone)
                )) {
                    $matches[] = [
                        'entry_id' => $entry->id,
                        'field' => 'phone',
                        'matched_value' => $entry->phone,
                        'remark' => $entry->remark,
                    ];
                }
            }

            $order->blacklist_match = !empty($matches) ? [
                'matched' => true,
                'details' => $matches,
            ] : null;
        }
    }

    private function matchName(string $blacklistName, ?string $orderName): bool
    {
        if (empty($orderName)) {
            return false;
        }

        $blacklistName = mb_strtolower(trim($blacklistName));
        $orderName = mb_strtolower(trim($orderName));

        return $blacklistName === $orderName || str_contains($orderName, $blacklistName);
    }

    private function matchPhone(string $blacklistPhone, ?string $orderPhone): bool
    {
        if (empty($orderPhone)) {
            return false;
        }

        $blacklistPhone = preg_replace('/[^0-9]/', '', $blacklistPhone);
        $orderPhone = preg_replace('/[^0-9]/', '', $orderPhone);

        return $blacklistPhone === $orderPhone || str_contains($orderPhone, $blacklistPhone);
    }

}
