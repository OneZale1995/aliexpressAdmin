<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemConfig;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class SystemConfigController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = SystemConfig::query();

        if ($request->filled('group')) {
            $query->where('group', $request->group);
        }
        if ($request->filled('key')) {
            $query->where('key', 'like', '%' . $request->key . '%');
        }

        $items = $query->orderBy('group')->orderBy('sort')->get();

        return $this->success($items);
    }

    public function store(Request $request)
    {
        $request->validate([
            'key' => 'required|string|unique:system_configs,key',
            'name' => 'required|string',
        ]);

        $config = SystemConfig::create($request->only([
            'group', 'key', 'value', 'name', 'type', 'options', 'description', 'sort',
        ]));

        return $this->success($config, '创建成功');
    }

    public function update(Request $request)
    {
        $systemConfig = SystemConfig::findOrFail($request->id);

        $request->validate([
            'key' => 'required|string|unique:system_configs,key,' . $systemConfig->id,
            'name' => 'required|string',
        ]);

        $systemConfig->update($request->only([
            'group', 'key', 'value', 'name', 'type', 'options', 'description', 'sort',
        ]));

        return $this->success($systemConfig, '更新成功');
    }

    public function destroy(Request $request)
    {
        $systemConfig = SystemConfig::findOrFail($request->id);
        $systemConfig->delete();
        return $this->success(null, '删除成功');
    }

    // 批量保存配置值（前端配置页面使用）
    public function batchSave(Request $request)
    {
        $configs = $request->input('configs', []);

        foreach ($configs as $item) {
            if (isset($item['key'])) {
                SystemConfig::where('key', $item['key'])->update(['value' => $item['value'] ?? '']);
            }
        }

        return $this->success(null, '保存成功');
    }
}
