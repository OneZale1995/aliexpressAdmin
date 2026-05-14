<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\Team;
use App\Services\ProductSyncStateService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Shop::with(['user:id,username,nickname', 'team:id,name', 'creator:id,username,nickname', 'updater:id,username,nickname']);

        // 超管看所有
        if ($user->hasRole('super-admin')) {
            // 不加限制
        } elseif ($this->isTeamAdmin($user)) {
            // 团队管理员看自己团队的所有店铺
            $teamIds = Team::where('admin_user_id', $user->id)->pluck('id');
            $query->whereIn('team_id', $teamIds);
        } else {
            // 采购只看自己的店铺
            $query->where('user_id', $user->id);
        }

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('team_id')) {
            $query->where('team_id', $request->team_id);
        }

        return $this->paginate($query, $request);
    }

    public function store(Request $request, ProductSyncStateService $productSyncStateService)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required|string|max:200',
            'email' => 'nullable|email|max:200',
            'status' => 'in:0,1',
            'access_token' => 'nullable|string',
            'default_shipping_fee' => 'nullable|numeric|min:0',
            'logistics_template_id' => 'nullable|string|max:100',
            'logistics_template_name' => 'nullable|string|max:200',
            'logistics_route' => 'nullable|string|max:200',
            'team_id' => 'nullable|exists:teams,id',
            'user_id' => 'nullable|exists:admin_users,id',
        ]);

        // 非超管自动分配团队
        $teamId = $user->hasRole('super-admin') ? $request->team_id : $this->resolveManagedTeamId($user);
        if (!$teamId) {
            if ($user->hasRole('super-admin')) {
                return $this->fail('请选择所属团队');
            }
            return $this->fail('请先加入团队后再添加店铺');
        }

        $assignedUserId = $user->id;
        if ($user->hasRole('super-admin') || $this->isTeamAdmin($user)) {
            if (!$request->filled('user_id')) {
                return $this->fail('请选择所属采购');
            }

            $assignedUserId = (int) $request->user_id;
            if (!$this->canAssignPurchaserToTeam((int) $teamId, $assignedUserId)) {
                return $this->fail('所选采购不属于当前团队');
            }
        }

        $shop = Shop::create([
            'name' => $request->name,
            'email' => $request->email,
            'status' => $request->get('status', 1),
            'access_token' => $request->access_token,
            'default_shipping_fee' => $request->get('default_shipping_fee', 0),
            'logistics_template_id' => $request->logistics_template_id,
            'logistics_template_name' => $request->logistics_template_name,
            'logistics_route' => $request->logistics_route,
            'team_id' => $teamId,
            'user_id' => $assignedUserId,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        // 如有 access_token，异步拉取该店铺商品列表
        if ($shop->access_token) {
            try {
                $productSyncStateService->dispatchShopSync($shop);
            } catch (\Throwable $e) {
                // 创建店铺不因自动同步启动失败而回滚
            }
        }

        return $this->success($shop->load(['user:id,username,nickname', 'team:id,name']));
    }

    public function show(Request $request)
    {
        $shop = Shop::with(['user:id,username,nickname', 'team:id,name'])->findOrFail($request->id);
        // 返回时包含access_token
        $shop->makeVisible('access_token');
        return $this->success($shop);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $shop = Shop::findOrFail($request->id);

        $request->validate([
            'name' => 'required|string|max:200',
            'email' => 'nullable|email|max:200',
            'status' => 'in:0,1',
            'access_token' => 'nullable|string',
            'default_shipping_fee' => 'nullable|numeric|min:0',
            'logistics_template_id' => 'nullable|string|max:100',
            'logistics_template_name' => 'nullable|string|max:200',
            'logistics_route' => 'nullable|string|max:200',
            'team_id' => 'required|exists:teams,id',
            'user_id' => 'required|exists:admin_users,id',
        ]);

        $teamId = $user->hasRole('super-admin') ? $request->team_id : $this->resolveManagedTeamId($user);
        if (!$teamId) {
            return $this->fail('请先加入团队后再编辑店铺');
        }

        $assignedUserId = $shop->user_id;
        if ($user->hasRole('super-admin') || $this->isTeamAdmin($user)) {
            if (!$request->filled('user_id')) {
                return $this->fail('请选择所属采购');
            }

            $assignedUserId = (int) $request->user_id;
            if (!$this->canAssignPurchaserToTeam((int) $teamId, $assignedUserId)) {
                return $this->fail('所选采购不属于当前团队');
            }
        } else {
            $assignedUserId = $user->id;
        }

        $tokenChanged = $request->access_token !== $shop->access_token;

        $shop->update([
            'name' => $request->name,
            'email' => $request->email,
            'status' => $request->input('status', $shop->status),
            'access_token' => $request->access_token,
            'default_shipping_fee' => $request->get('default_shipping_fee', $shop->default_shipping_fee),
            'logistics_template_id' => $request->logistics_template_id,
            'logistics_template_name' => $request->logistics_template_name,
            'logistics_route' => $request->logistics_route,
            'team_id' => $teamId,
            'user_id' => $assignedUserId,
            'updated_by' => $user->id,
        ]);

        if ($tokenChanged) {
            $shop->update(['token_invalid_at' => null]);
        }

        return $this->success($shop->load(['user:id,username,nickname', 'team:id,name', 'creator:id,username,nickname', 'updater:id,username,nickname']));
    }

    public function destroy(Request $request)
    {
        $shop = Shop::findOrFail($request->id);
        $shop->delete();
        return $this->success();
    }

    private function isTeamAdmin($user): bool
    {
        return Team::where('admin_user_id', $user->id)->exists();
    }

    private function resolveManagedTeamId($user): ?int
    {
        if ($this->isTeamAdmin($user)) {
            return Team::where('admin_user_id', $user->id)->value('id');
        }

        return $user->teams()->value('teams.id');
    }

    private function canAssignPurchaserToTeam(int $teamId, int $userId): bool
    {
        return Team::whereKey($teamId)
            ->whereHas('members', function ($query) use ($userId) {
                $query->where('admin_users.id', $userId)
                    ->whereHas('roles', function ($roleQuery) {
                        $roleQuery->where('name', 'purchaser');
                    });
            })
            ->exists();
    }
}
