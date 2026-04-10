<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class LoginLogController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = LoginLog::query();

        if ($request->filled('user_name')) {
            $query->where('user_name', 'like', '%' . $request->user_name . '%');
        }
        if ($request->filled('ip')) {
            $query->where('ip', $request->ip);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date . ' 23:59:59']);
        }

        return $this->paginate($query, $request);
    }

    public function destroy(Request $request)
    {
        $loginLog = LoginLog::findOrFail($request->id);
        $loginLog->delete();
        return $this->success(null, '删除成功');
    }

    public function clear(Request $request)
    {
        $days = (int) $request->input('days', 90);
        LoginLog::where('created_at', '<', now()->subDays($days))->delete();

        return $this->success(null, "已清理 {$days} 天前的登录日志");
    }
}
