<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            // 记录失败日志
            LoginLog::create([
                'user_id' => $user->id ?? 0,
                'user_name' => $request->username,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 0,
                'message' => '用户名或密码错误',
                'created_at' => now(),
            ]);
            return $this->error('用户名或密码错误');
        }

        if ($user->status !== 1) {
            LoginLog::create([
                'user_id' => $user->id,
                'user_name' => $user->username,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 0,
                'message' => '账号已被禁用',
                'created_at' => now(),
            ]);
            return $this->error('账号已被禁用');
        }

        $token = $user->createToken('admin-token')->plainTextToken;

        // 记录成功日志
        LoginLog::create([
            'user_id' => $user->id,
            'user_name' => $user->username,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status' => 1,
            'message' => '登录成功',
            'created_at' => now(),
        ]);

        return $this->success(['token' => $token]);
    }

    public function info(Request $request)
    {
        $user = $request->user();
        $user->load('roles');

        return $this->success([
            'roles' => $user->getRoleNames() ?: ['default'],
            'permissions' => $user->getAllPermissions(),
            'introduction' => $user->nickname ?: $user->username,
            'avatar' => $user->avatar ?: 'https://wpimg.wallstcn.com/f778738c-e4f8-4870-b634-56703b4acafe.gif',
            'name' => $user->nickname ?: $user->username,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success('success');
    }
}
