<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OperationLog;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class OperationLogController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = OperationLog::query();

        if ($request->filled('user_name')) {
            $query->where('user_name', 'like', '%' . $request->user_name . '%');
        }

        if ($request->filled('path')) {
            $query->where('path', 'like', '%' . $request->path . '%');
        }

        if ($request->filled('method')) {
            $query->where('method', $request->method);
        }

        if ($request->has('is_success') && $request->is_success !== '' && $request->is_success !== null) {
            $query->where('is_success', (bool) $request->is_success);
        }

        if ($request->filled('min_duration')) {
            $query->where('duration', '>=', (int) $request->min_duration);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date . ' 23:59:59']);
        }

        return $this->paginate($query, $request);
    }

    public function destroy(Request $request)
    {
        $operationLog = OperationLog::findOrFail($request->id);
        $operationLog->delete();

        return $this->success();
    }

    public function clear(Request $request)
    {
        $days = (int) $request->get('days', 30);
        OperationLog::where('created_at', '<', now()->subDays($days))->delete();

        return $this->success(null, "已清除{$days}天前的日志");
    }
}
