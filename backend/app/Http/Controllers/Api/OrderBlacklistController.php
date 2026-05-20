<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderBlacklist;
use App\Models\Team;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class OrderBlacklistController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $user = $request->user();
        $query = OrderBlacklist::with(['team:id,name', 'creator:id,username,nickname', 'updater:id,username,nickname']);

        if (!$user->hasRole('super-admin')) {
            $teamIds = $this->getTeamIdsForUser($user);
            $query->whereIn('team_id', $teamIds);
        } elseif ($request->filled('team_id')) {
            $query->where('team_id', $request->team_id);
        }

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        if ($request->filled('phone')) {
            $query->where('phone', 'like', '%' . $request->phone . '%');
        }

        $query->orderBy('id', 'desc');

        return $this->paginate($query, $request);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'nullable|string|max:200',
            'phone' => 'nullable|string|max:50',
            'remark' => 'nullable|string',
            'team_id' => 'nullable|exists:teams,id',
        ]);

        if (!$request->filled('name') && !$request->filled('phone')) {
            return $this->error('姓名和电话至少填写一项');
        }

        $teamId = $this->resolveTeamId($user, $request);
        if (!$teamId) {
            $teamIds = $this->getTeamIdsForUser($user);
            if (count($teamIds) > 1) {
                return $this->error('请选择所属团队');
            }
            return $this->error('无法确定所属团队');
        }

        $entry = OrderBlacklist::create([
            'team_id' => $teamId,
            'name' => $request->name,
            'phone' => $request->phone,
            'remark' => $request->remark,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return $this->success($entry->load(['team:id,name', 'creator:id,username,nickname', 'updater:id,username,nickname']), '创建成功');
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $entry = OrderBlacklist::findOrFail($request->id);

        if (!$this->canManageEntry($user, $entry)) {
            return $this->error('无权操作该黑名单条目');
        }

        $request->validate([
            'name' => 'nullable|string|max:200',
            'phone' => 'nullable|string|max:50',
            'remark' => 'nullable|string',
        ]);

        if (!$request->filled('name') && !$request->filled('phone')) {
            return $this->error('姓名和电话至少填写一项');
        }

        $entry->update([
            'name' => $request->name,
            'phone' => $request->phone,
            'remark' => $request->remark,
            'updated_by' => $user->id,
        ]);

        return $this->success($entry->load(['team:id,name', 'creator:id,username,nickname', 'updater:id,username,nickname']), '更新成功');
    }

    public function destroy(Request $request)
    {
        $user = $request->user();
        $entry = OrderBlacklist::findOrFail($request->id);

        if (!$this->canManageEntry($user, $entry)) {
            return $this->error('无权操作该黑名单条目');
        }

        $entry->delete();

        return $this->success(null, '删除成功');
    }

    private function getTeamIdsForUser($user): array
    {
        if ($this->isTeamAdmin($user)) {
            return Team::where('admin_user_id', $user->id)->pluck('id')->toArray();
        }

        return $user->teams()->pluck('teams.id')->toArray();
    }

    private function resolveTeamId($user, Request $request): ?int
    {
        // 如果请求中提供了 team_id，校验用户是否有权限操作该团队
        if ($request->filled('team_id')) {
            $teamId = (int) $request->team_id;
            if ($user->hasRole('super-admin')) {
                return $teamId;
            }
            if (in_array($teamId, $this->getTeamIdsForUser($user))) {
                return $teamId;
            }
            return null;
        }

        // 未提供 team_id 时，仅当用户恰好属于1个团队时才自动确定
        $teamIds = $this->getTeamIdsForUser($user);
        return count($teamIds) === 1 ? $teamIds[0] : null;
    }

    private function isTeamAdmin($user): bool
    {
        return Team::where('admin_user_id', $user->id)->exists();
    }

    private function canManageEntry($user, $entry): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return in_array($entry->team_id, $this->getTeamIdsForUser($user));
    }
}
