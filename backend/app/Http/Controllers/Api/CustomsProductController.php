<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomsProduct;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class CustomsProductController extends Controller
{
    use ApiResponse;

    public function list(Request $request)
    {
        $items = CustomsProduct::orderBy('sort')->orderBy('id')->get();
        return $this->success(['items' => $items]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'name_cn' => 'required|string|max:100',
            'name_en' => 'required|string|max:200',
            'sort' => 'nullable|integer',
        ]);

        $item = CustomsProduct::create([
            'name_cn' => $request->name_cn,
            'name_en' => $request->name_en,
            'sort' => $request->input('sort', 0),
        ]);

        return $this->success($item, '创建成功');
    }

    public function update(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:customs_products,id',
            'name_cn' => 'required|string|max:100',
            'name_en' => 'required|string|max:200',
            'sort' => 'nullable|integer',
        ]);

        $item = CustomsProduct::findOrFail($request->id);
        $item->update([
            'name_cn' => $request->name_cn,
            'name_en' => $request->name_en,
            'sort' => $request->input('sort', $item->sort),
        ]);

        return $this->success($item, '更新成功');
    }

    public function delete(Request $request)
    {
        $request->validate(['id' => 'required|exists:customs_products,id']);
        CustomsProduct::findOrFail($request->id)->delete();
        return $this->success(null, '删除成功');
    }
}
