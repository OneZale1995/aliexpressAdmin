<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = User::with('roles');

        if ($request->filled('username')) {
            $query->where('username', 'like', '%' . $request->username . '%');
        }
        if ($request->filled('nickname')) {
            $query->where('nickname', 'like', '%' . $request->nickname . '%');
        }

        if ($request->input('all') == 1) {
            return $this->success($query->orderBy('id', 'desc')->get());
        }

        return $this->paginate($query, $request);
    }

    public function store(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:100|unique:admin_users',
            'nickname' => 'nullable|string|max:191',
            'password' => 'required|string|min:6',
            'status' => 'in:0,1',
            'role_ids' => 'array',
        ]);

        $user = User::create([
            'username' => $request->username,
            'nickname' => $request->nickname,
            'name' => $request->nickname ?: $request->username,
            'password' => Hash::make($request->password),
            'status' => $request->get('status', 1),
        ]);

        if ($request->has('role_ids')) {
            $user->roles()->sync($request->role_ids);
        }

        return $this->success($user->load('roles'));
    }

    public function show(Request $request)
    {
        $user = User::findOrFail($request->id);
        return $this->success($user->load('roles'));
    }

    public function update(Request $request)
    {
        $user = User::findOrFail($request->id);

        $request->validate([
            'username' => 'required|string|max:100|unique:admin_users,username,' . $user->id,
            'nickname' => 'nullable|string|max:191',
            'password' => 'nullable|string|min:6',
            'status' => 'in:0,1',
            'role_ids' => 'array',
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

        $user->update($data);

        if ($request->has('role_ids')) {
            $user->roles()->sync($request->role_ids);
        }

        return $this->success($user->load('roles'));
    }

    public function destroy(Request $request)
    {
        $user = User::findOrFail($request->id);
        $user->roles()->detach();
        $user->delete();

        return $this->success();
    }
}
