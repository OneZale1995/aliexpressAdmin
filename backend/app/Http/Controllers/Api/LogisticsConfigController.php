<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LogisticsConfig;
use App\Models\SystemConfig;
use App\Models\Team;
use App\Models\User;
use App\Services\LogisticsConfigResolver;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class LogisticsConfigController extends Controller
{
    use ApiResponse;

    public function current(Request $request, LogisticsConfigResolver $resolver)
    {
        $user = $request->user();

        $teamSwitch = $resolver->isSwitchOn(SystemConfig::getByKey(LogisticsConfigResolver::KEY_ENABLE_TEAM, '0'));
        $userSwitch = $resolver->isSwitchOn(SystemConfig::getByKey(LogisticsConfigResolver::KEY_ENABLE_USER, '0'));

        $teamId = $this->resolveTeamScopeId($request, $user);
        $userId = $this->resolveUserScopeId($request, $user);

        $payload = [
            'switches' => [
                LogisticsConfigResolver::KEY_ENABLE_TEAM => $teamSwitch,
                LogisticsConfigResolver::KEY_ENABLE_USER => $userSwitch,
            ],
            'scopes' => [
                'team' => [
                    'available' => (bool) $teamId,
                    'scope_id' => $teamId,
                    'editable' => $this->canEditTeamScope($user),
                    'providers' => [],
                ],
                'user' => [
                    'available' => (bool) $userId,
                    'scope_id' => $userId,
                    'editable' => true,
                    'providers' => [],
                ],
            ],
        ];

        foreach ([LogisticsConfigResolver::PROVIDER_CHINAPOST, LogisticsConfigResolver::PROVIDER_SZ56T] as $provider) {
            $payload['scopes']['team']['providers'][$provider] = $this->buildScopeProviderData(
                'team',
                $teamId,
                $provider,
                $resolver
            );

            $payload['scopes']['user']['providers'][$provider] = $this->buildScopeProviderData(
                'user',
                $userId,
                $provider,
                $resolver
            );
        }

        return $this->success($payload);
    }

    public function save(Request $request, LogisticsConfigResolver $resolver)
    {
        $user = $request->user();

        $request->validate([
            'scope_type' => 'required|in:team,user',
            'provider' => 'required|in:chinapost,sz56t',
            'enabled' => 'required|boolean',
            'config' => 'nullable|array',
            'scope_id' => 'nullable|integer',
        ]);

        $scopeType = $request->input('scope_type');
        $provider = $request->input('provider');
        $enabled = (bool) $request->boolean('enabled');

        $switchKey = $scopeType === 'team'
            ? LogisticsConfigResolver::KEY_ENABLE_TEAM
            : LogisticsConfigResolver::KEY_ENABLE_USER;

        if (!$resolver->isSwitchOn(SystemConfig::getByKey($switchKey, '0'))) {
            return $this->error(($scopeType === 'team' ? '团队' : '用户') . '物流配置功能未开启');
        }

        $scopeId = $scopeType === 'team'
            ? $this->resolveTeamScopeId($request, $user)
            : $this->resolveUserScopeId($request, $user);

        if (!$scopeId) {
            return $this->error('未找到可配置的作用域');
        }

        if ($scopeType === 'team' && !$this->canEditTeamScope($user)) {
            return $this->error('无权编辑团队物流配置');
        }

        $configInput = is_array($request->input('config')) ? $request->input('config') : [];
        $sanitized = $resolver->sanitizeProviderConfig($provider, $configInput);

        $record = LogisticsConfig::query()->updateOrCreate(
            [
                'scope_type' => $scopeType,
                'scope_id' => (int) $scopeId,
                'provider' => $provider,
            ],
            [
                'enabled' => $enabled,
                'config' => $sanitized,
            ]
        );

        return $this->success([
            'scope_type' => $record->scope_type,
            'scope_id' => $record->scope_id,
            'provider' => $record->provider,
            'enabled' => (bool) $record->enabled,
            'config' => array_merge($resolver->providerDefaultConfig($provider), is_array($record->config) ? $record->config : []),
        ], '保存成功');
    }

    private function buildScopeProviderData(string $scopeType, ?int $scopeId, string $provider, LogisticsConfigResolver $resolver): array
    {
        $defaults = $resolver->providerDefaultConfig($provider);

        if (!$scopeId) {
            return [
                'enabled' => false,
                'config' => $defaults,
            ];
        }

        $record = LogisticsConfig::query()
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->where('provider', $provider)
            ->first();

        return [
            'enabled' => (bool) ($record->enabled ?? false),
            'config' => array_merge($defaults, is_array($record->config ?? null) ? $record->config : []),
        ];
    }

    private function resolveTeamScopeId(Request $request, User $user): ?int
    {
        if ($user->hasRole('super-admin')) {
            if ($request->filled('team_id')) {
                $teamId = (int) $request->input('team_id');
                return Team::query()->whereKey($teamId)->exists() ? $teamId : null;
            }

            return null;
        }

        if ($this->isTeamAdmin($user)) {
            return (int) Team::query()->where('admin_user_id', $user->id)->value('id');
        }

        return $user->teams()->value('teams.id');
    }

    private function resolveUserScopeId(Request $request, User $user): ?int
    {
        if ($user->hasRole('super-admin') && $request->filled('user_id')) {
            $userId = (int) $request->input('user_id');
            return User::query()->whereKey($userId)->exists() ? $userId : null;
        }

        return (int) $user->id;
    }

    private function canEditTeamScope(User $user): bool
    {
        return $user->hasRole('super-admin') || $this->isTeamAdmin($user);
    }

    private function isTeamAdmin(User $user): bool
    {
        return Team::query()->where('admin_user_id', $user->id)->exists();
    }
}
