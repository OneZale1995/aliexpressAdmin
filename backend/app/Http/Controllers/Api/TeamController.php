<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Team::with(['admin:id,username,nickname', 'members:id,username,nickname'])->withCount('shops');

        // 超管看所有，团队管理员只看自己的团队
        if (!$user->hasRole('super-admin')) {
            $query->where(function ($q) use ($user) {
                $q->where('admin_user_id', $user->id)
                  ->orWhereHas('members', function ($mq) use ($user) {
                      $mq->where('admin_users.id', $user->id);
                  });
            });
        }

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->input('all') == 1) {
            return $this->success($query->orderBy('id', 'desc')->get());
        }

        return $this->paginate($query, $request);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);

        if ($user->hasRole('super-admin')) {
            // 超管创建团队需要指定管理员
            $request->validate([
                'admin_user_id' => 'required|exists:admin_users,id',
                'status' => 'in:0,1',
                'member_ids' => 'array',
                'member_ids.*' => 'exists:admin_users,id',
            ]);
            $adminUserId = $request->admin_user_id;
        } else {
            // 团队管理员自己创建，只允许1个团队
            $exists = Team::where('admin_user_id', $user->id)->exists();
            if ($exists) {
                return $this->error('每个团队管理员只能创建1个团队');
            }
            $adminUserId = $user->id;
        }

        $team = Team::create([
            'name' => $request->name,
            'admin_user_id' => $adminUserId,
            'status' => $request->get('status', 1),
            'description' => $request->description,
        ]);

        if ($request->has('member_ids')) {
            $team->members()->sync($request->member_ids);
        }

        return $this->success($team->load(['admin:id,username,nickname', 'members:id,username,nickname']));
    }

    public function show(Request $request)
    {
        $team = Team::with(['admin:id,username,nickname', 'members:id,username,nickname'])->findOrFail($request->id);
        return $this->success($team);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $team = Team::findOrFail($request->id);

        // 团队管理员只能修改自己团队的名称
        if (!$user->hasRole('super-admin')) {
            if ($team->admin_user_id !== $user->id) {
                return $this->error('无权修改此团队');
            }

            $request->validate([
                'name' => 'required|string|max:100',
            ]);

            $team->update(['name' => $request->name]);

            return $this->success($team->load(['admin:id,username,nickname', 'members:id,username,nickname']));
        }

        // 超管可以修改所有字段
        $request->validate([
            'name' => 'required|string|max:100',
            'admin_user_id' => 'required|exists:admin_users,id',
            'status' => 'in:0,1',
            'description' => 'nullable|string',
            'member_ids' => 'array',
            'member_ids.*' => 'exists:admin_users,id',
        ]);

        $team->update([
            'name' => $request->name,
            'admin_user_id' => $request->admin_user_id,
            'status' => $request->input('status', $team->status),
            'description' => $request->description,
        ]);

        if ($request->has('member_ids')) {
            $team->members()->sync($request->member_ids);
        }

        return $this->success($team->load(['admin:id,username,nickname', 'members:id,username,nickname']));
    }

    public function destroy(Request $request)
    {
        $team = Team::findOrFail($request->id);

        if ($team->shops()->count() > 0) {
            return $this->error('该团队下还有店铺，无法删除');
        }

        $team->members()->detach();
        $team->delete();

        return $this->success();
    }
}
