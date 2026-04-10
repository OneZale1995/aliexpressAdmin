<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    use ApiResponse;

    public function update(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'nickname' => 'sometimes|string|max:255',
            'avatar' => 'sometimes|string',
        ]);

        $user->update($request->only(['nickname', 'avatar']));

        return $this->success($user, '更新成功');
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        if (!Hash::check($request->old_password, $user->password)) {
            return $this->error('原密码错误');
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        return $this->success(null, '密码修改成功');
    }

    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|max:2048',
        ]);

        $path = $request->file('avatar')->store('avatars/' . date('Ymd'), 'public');
        $url = asset('storage/' . $path);

        $user = $request->user();
        $user->update(['avatar' => $url]);

        return $this->success(['url' => $url], '头像上传成功');
    }
}
