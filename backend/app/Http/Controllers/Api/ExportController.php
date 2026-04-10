<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExportController extends Controller
{
    use ApiResponse;

    /**
     * 通用导出接口
     * POST /api/export
     * body: { table, columns, filters?, filename? }
     */
    public function export(Request $request)
    {
        $request->validate([
            'table' => 'required|string',
            'columns' => 'required|array',
            'columns.*.field' => 'required|string',
            'columns.*.title' => 'required|string',
        ]);

        $table = $request->input('table');
        $columns = $request->input('columns');
        $filters = $request->input('filters', []);
        $filename = $request->input('filename', $table . '_' . date('YmdHis'));

        // 白名单校验表名，防止SQL注入
        $allowedTables = [
            'admin_users', 'admin_roles', 'admin_permissions', 'admin_menus',
            'admin_operation_logs', 'admin_login_logs', 'admin_files',
            'admin_system_configs', 'admin_dict_types', 'admin_dict_data',
        ];

        if (!in_array($table, $allowedTables, true)) {
            return $this->error('不允许导出该数据表');
        }

        $query = DB::table($table);

        // 应用筛选条件
        foreach ($filters as $filter) {
            if (isset($filter['field'], $filter['value']) && $filter['value'] !== '') {
                $op = $filter['op'] ?? '=';
                if ($op === 'like') {
                    $query->where($filter['field'], 'like', '%' . $filter['value'] . '%');
                } else {
                    $query->where($filter['field'], $op, $filter['value']);
                }
            }
        }

        $rows = $query->orderBy('id', 'desc')->limit(10000)->get();

        // 构建CSV
        $fields = array_column($columns, 'field');
        $headers = array_column($columns, 'title');

        $csvContent = chr(0xEF) . chr(0xBB) . chr(0xBF); // UTF-8 BOM
        $csvContent .= implode(',', array_map(fn($h) => '"' . str_replace('"', '""', $h) . '"', $headers)) . "\n";

        foreach ($rows as $row) {
            $values = [];
            foreach ($fields as $field) {
                $val = $row->$field ?? '';
                $values[] = '"' . str_replace('"', '""', (string)$val) . '"';
            }
            $csvContent .= implode(',', $values) . "\n";
        }

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.csv"',
        ]);
    }
}
