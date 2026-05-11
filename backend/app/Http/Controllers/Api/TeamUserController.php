<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TeamUserController extends Controller
{
    use ApiResponse;

    /**
     * 团队用户列表 - 团队管理员看自己团队成员，超管看全部
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('super-admin')) {
            $query = User::with('roles');
            if ($request->filled('username')) {
                $query->where('username', 'like', '%' . $request->username . '%');
            }
            if ($request->input('all') == 1) {
                return $this->success($query->orderBy('id', 'desc')->get());
            }
            return $this->paginate($query, $request);
        }

        // 团队管理员：只看自己团队的成员
        $teamIds = Team::where('admin_user_id', $user->id)->pluck('id');
        $memberIds = \DB::table('team_members')->whereIn('team_id', $teamIds)->pluck('user_id')->unique();

        $query = User::with('roles')->whereIn('id', $memberIds);
        if ($request->filled('username')) {
            $query->where('username', 'like', '%' . $request->username . '%');
        }

        if ($request->input('all') == 1) {
            return $this->success($query->orderBy('id', 'desc')->get());
        }

        return $this->paginate($query, $request);
    }

    /**
     * 添加采购用户 - 团队管理员只能创建采购角色的用户，自动归入自己的团队
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'username' => 'required|string|max:100|unique:admin_users',
            'nickname' => 'nullable|string|max:191',
            'password' => 'required|string|min:6',
            'team_id' => 'nullable|exists:teams,id',
        ]);

        // 确定团队：团队管理员自动用自己的团队，超管需要指定
        if ($user->hasRole('super-admin')) {
            if (!$request->filled('team_id')) {
                return $this->error('请选择所属团队');
            }
            $team = Team::findOrFail($request->team_id);
        } else {
            $team = Team::where('admin_user_id', $user->id)->first();
            if (!$team) {
                return $this->error('请先创建团队');
            }
        }

        // 创建用户
        $newUser = User::create([
            'username' => $request->username,
            'nickname' => $request->nickname,
            'name' => $request->nickname ?: $request->username,
            'password' => Hash::make($request->password),
            'status' => 1,
        ]);

        // 自动分配采购角色
        $purchaserRole = Role::where('name', 'purchaser')->first();
        if ($purchaserRole) {
            $newUser->roles()->sync([$purchaserRole->id]);
        }

        // 自动加入团队
        $team->members()->syncWithoutDetaching([$newUser->id]);

        return $this->success($newUser->load('roles'));
    }

    /**
     * 编辑采购用户
     */
    public function update(Request $request)
    {
        $user = $request->user();
        $targetUser = User::findOrFail($request->id);

        // 团队管理员只能编辑自己团队的成员
        if (!$user->hasRole('super-admin')) {
            $teamIds = Team::where('admin_user_id', $user->id)->pluck('id');
            $isMember = \DB::table('team_members')
                ->whereIn('team_id', $teamIds)
                ->where('user_id', $targetUser->id)
                ->exists();
            if (!$isMember) {
                return $this->error('无权编辑此用户');
            }
        }

        $request->validate([
            'username' => 'required|string|max:100|unique:admin_users,username,' . $targetUser->id,
            'nickname' => 'nullable|string|max:191',
            'status' => 'in:0,1',
        ]);

        $data = [
            'username' => $request->username,
            'nickname' => $request->nickname,
        ];
        if ($request->has('status')) {
            $data['status'] = $request->status;
        }
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $targetUser->update($data);

        return $this->success($targetUser->load('roles'));
    }

    /**
     * 删除采购用户
     */
    public function destroy(Request $request)
    {
        $user = $request->user();
        $targetUser = User::findOrFail($request->id);

        if (!$user->hasRole('super-admin')) {
            $teamIds = Team::where('admin_user_id', $user->id)->pluck('id');
            $isMember = \DB::table('team_members')
                ->whereIn('team_id', $teamIds)
                ->where('user_id', $targetUser->id)
                ->exists();
            if (!$isMember) {
                return $this->error('无权删除此用户');
            }
        }

        $targetUser->roles()->detach();
        \DB::table('team_members')->where('user_id', $targetUser->id)->delete();
        $targetUser->delete();

        return $this->success();
    }
}
