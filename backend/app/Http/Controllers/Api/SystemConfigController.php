<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemConfig;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class SystemConfigController extends Controller
{
    use ApiResponse;

    private const PRESET_CONFIGS = [
        [
            'group' => 'finance',
            'key' => 'cny_exchange_rate',
            'name' => '人民币汇率',
            'value' => '7.2000',
            'type' => 'number',
            'description' => '用于将订单利润折算为人民币，示例：7.2',
            'sort' => 1,
        ],
        [
            'group' => 'finance',
            'key' => 'estimated_receipt_rate',
            'name' => '预估回款比例',
            'value' => '0.908',
            'type' => 'number',
            'description' => '用于计算预估回款，公式：销售额 × 比例',
            'sort' => 2,
        ],
        [
            'group' => 'logistics_plugin',
            'key' => 'enable_team_logistics_config',
            'name' => '启用团队物流配置',
            'value' => '0',
            'type' => 'switch',
            'options' => '{"activeValue":"1","inactiveValue":"0"}',
            'description' => '开启后允许团队配置中国邮政/雷翼账号参数',
            'sort' => 1,
        ],
        [
            'group' => 'logistics_plugin',
            'key' => 'enable_user_logistics_config',
            'name' => '启用用户物流配置',
            'value' => '0',
            'type' => 'switch',
            'options' => '{"activeValue":"1","inactiveValue":"0"}',
            'description' => '开启后允许采购用户配置个人物流参数，优先级高于团队',
            'sort' => 2,
        ],
    ];

    public function index(Request $request)
    {
        $this->ensurePresetConfigs();

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

    private function ensurePresetConfigs(): void
    {
        foreach (self::PRESET_CONFIGS as $config) {
            SystemConfig::firstOrCreate(['key' => $config['key']], $config);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'key' => 'required|string|unique:admin_system_configs,key',
            'name' => 'required|string',
        ]);

        $config = SystemConfig::create($request->only([
            'group', 'key', 'value', 'name', 'type', 'options', 'description', 'sort',
        ]));

        SystemConfig::clearCache($config->group, $config->key);

        return $this->success($config, '创建成功');
    }

    public function update(Request $request)
    {
        $systemConfig = SystemConfig::findOrFail($request->id);
        $oldGroup = $systemConfig->group;
        $oldKey = $systemConfig->key;

        $request->validate([
            'key' => 'required|string|unique:admin_system_configs,key,' . $systemConfig->id,
            'name' => 'required|string',
        ]);

        $systemConfig->update($request->only([
            'group', 'key', 'value', 'name', 'type', 'options', 'description', 'sort',
        ]));

        SystemConfig::clearCache($oldGroup, $oldKey);
        SystemConfig::clearCache($systemConfig->group, $systemConfig->key);

        return $this->success($systemConfig, '更新成功');
    }

    public function destroy(Request $request)
    {
        $systemConfig = SystemConfig::findOrFail($request->id);
        SystemConfig::clearCache($systemConfig->group, $systemConfig->key);
        $systemConfig->delete();
        return $this->success(null, '删除成功');
    }

    // 批量保存配置值（前端配置页面使用）
    public function batchSave(Request $request)
    {
        $configs = $request->input('configs', []);
        $affectedGroups = [];

        foreach ($configs as $item) {
            if (isset($item['key'])) {
                SystemConfig::where('key', $item['key'])->update(['value' => $item['value'] ?? '']);
                $record = SystemConfig::where('key', $item['key'])->first();
                if ($record) {
                    SystemConfig::clearCache(null, $item['key']);
                    $affectedGroups[$record->group] = true;
                }
            }
        }

        foreach (array_keys($affectedGroups) as $group) {
            SystemConfig::clearCache($group);
        }

        return $this->success(null, '保存成功');
    }
}
