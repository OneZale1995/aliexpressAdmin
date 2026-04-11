<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DictType;
use App\Models\DictData;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class DictController extends Controller
{
    use ApiResponse;

    // ========== 字典类型 ==========

    public function typeIndex(Request $request)
    {
        $query = DictType::query();

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        if ($request->filled('code')) {
            $query->where('code', 'like', '%' . $request->code . '%');
        }

        return $this->paginate($query, $request);
    }

    public function typeStore(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'code' => 'required|string|unique:admin_dict_types,code',
        ]);

        $type = DictType::create($request->only(['name', 'code', 'status', 'description']));

        return $this->success($type, '创建成功');
    }

    public function typeUpdate(Request $request)
    {
        $dictType = DictType::findOrFail($request->id);

        $request->validate([
            'name' => 'required|string',
            'code' => 'required|string|unique:admin_dict_types,code,' . $dictType->id,
        ]);

        $dictType->update($request->only(['name', 'code', 'status', 'description']));

        return $this->success($dictType, '更新成功');
    }

    public function typeDestroy(Request $request)
    {
        $dictType = DictType::findOrFail($request->id);

        if ($dictType->items()->count() > 0) {
            return $this->error('该字典类型下存在数据，无法删除');
        }

        $dictType->delete();
        return $this->success(null, '删除成功');
    }

    // ========== 字典数据 ==========

    public function dataIndex(Request $request)
    {
        $query = DictData::query();

        if ($request->filled('dict_type_id')) {
            $query->where('dict_type_id', $request->dict_type_id);
        }

        $items = $query->orderBy('sort')->get();

        return $this->success($items);
    }

    public function dataStore(Request $request)
    {
        $request->validate([
            'dict_type_id' => 'required|exists:admin_dict_types,id',
            'label' => 'required|string',
            'value' => 'required|string',
        ]);

        $data = DictData::create($request->only([
            'dict_type_id', 'label', 'value', 'status', 'sort', 'description',
        ]));

        return $this->success($data, '创建成功');
    }

    public function dataUpdate(Request $request)
    {
        $dictData = DictData::findOrFail($request->id);
        $request->validate([
            'label' => 'required|string',
            'value' => 'required|string',
        ]);

        $dictData->update($request->only([
            'label', 'value', 'status', 'sort', 'description',
        ]));

        return $this->success($dictData, '更新成功');
    }

    public function dataDestroy(Request $request)
    {
        $dictData = DictData::findOrFail($request->id);
        $dictData->delete();
        return $this->success(null, '删除成功');
    }

    // 根据字典编码获取数据（前端下拉框使用）
    public function getByCode(Request $request)
    {
        $items = DictType::getItems($request->code);
        return $this->success($items);
    }
}
