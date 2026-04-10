<?php

namespace App\Traits;

trait ApiResponse
{
    protected function success($data = null, string $message = 'ok')
    {
        return response()->json([
            'code' => 20000,
            'message' => $message,
            'data' => $data,
        ], 200);
    }

    protected function error(string $message = 'error', int $code = 40000, $data = null)
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], 200);
    }

    protected function paginate($query, $request)
    {
        $page = (int) $request->get('page', 1);
        $limit = (int) $request->get('limit', 20);

        $total = $query->count();
        $items = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return $this->success([
            'total' => $total,
            'items' => $items,
        ]);
    }
}
