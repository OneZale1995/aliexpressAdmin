<?php

namespace App\Http\Middleware;

use App\Models\OperationLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogOperation
{
    // 不记录的路由
    protected array $except = [
        'api/user/info',
        'api/user/login',
        'api/operation-logs/*',
        'api/login-logs/*',
        'api/export',
        'api/dict/get',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        // 检查排除路由
        foreach ($this->except as $pattern) {
            if ($request->is($pattern)) {
                return $response;
            }
        }

        $duration = (int) ((microtime(true) - $startTime) * 1000);

        $user = $request->user();

        $input = $request->except(['password', 'password_confirmation']);
        $responseContent = $response->getContent();

        // 截断过长的响应内容
        if (strlen($responseContent) > 2000) {
            $responseContent = mb_substr($responseContent, 0, 2000) . '...';
        }

        try {
            OperationLog::create([
                'user_id' => $user?->id ?? 0,
                'user_name' => $user?->nickname ?? $user?->username ?? '',
                'method' => $request->method(),
                'path' => $request->path(),
                'ip' => $request->ip(),
                'input' => json_encode($input, JSON_UNESCAPED_UNICODE),
                'status_code' => $response->getStatusCode(),
                'response' => $responseContent,
                'duration' => $duration,
            ]);
        } catch (\Throwable $e) {
            // 日志记录失败不影响正常流程
            logger()->error('Operation log failed: ' . $e->getMessage());
        }

        return $response;
    }
}
