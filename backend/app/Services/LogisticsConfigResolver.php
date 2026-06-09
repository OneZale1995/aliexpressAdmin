<?php

namespace App\Services;

use App\Models\LogisticsConfig;
use App\Models\Order;
use App\Models\SystemConfig;

class LogisticsConfigResolver
{
    public const PROVIDER_CHINAPOST = 'chinapost';
    public const PROVIDER_SZ56T = 'sz56t';

    public const KEY_ENABLE_TEAM = 'enable_team_logistics_config';
    public const KEY_ENABLE_USER = 'enable_user_logistics_config';

    public function resolveForOrder(?Order $order, string $provider): array
    {
        $provider = strtolower(trim($provider));
        if (!$order || !$order->shop || !in_array($provider, [self::PROVIDER_CHINAPOST, self::PROVIDER_SZ56T], true)) {
            return [
                'source' => 'system',
                'config' => [],
                'team_enabled' => false,
                'user_enabled' => false,
            ];
        }

        $shop = $order->shop;
        $teamScopeEnabled = $this->isSwitchOn(SystemConfig::getByKey(self::KEY_ENABLE_TEAM, '0'));
        $userScopeEnabled = $this->isSwitchOn(SystemConfig::getByKey(self::KEY_ENABLE_USER, '0'));

        $teamConfig = null;
        if ($teamScopeEnabled && !empty($shop->team_id)) {
            $teamConfig = LogisticsConfig::query()
                ->where('scope_type', 'team')
                ->where('scope_id', (int) $shop->team_id)
                ->where('provider', $provider)
                ->first();
        }

        $userConfig = null;
        if ($userScopeEnabled && !empty($shop->user_id)) {
            $userConfig = LogisticsConfig::query()
                ->where('scope_type', 'user')
                ->where('scope_id', (int) $shop->user_id)
                ->where('provider', $provider)
                ->first();
        }

        $resolved = [];
        $source = 'system';

        if ($teamConfig && $teamConfig->enabled) {
            $resolved = $this->mergeNonEmpty($resolved, is_array($teamConfig->config) ? $teamConfig->config : []);
            $source = 'team';
        }

        if ($userConfig && $userConfig->enabled) {
            $resolved = $this->mergeNonEmpty($resolved, is_array($userConfig->config) ? $userConfig->config : []);
            $source = 'user';
        }

        return [
            'source' => $source,
            'config' => $resolved,
            'team_enabled' => $teamScopeEnabled,
            'user_enabled' => $userScopeEnabled,
            'team_config_enabled' => (bool) ($teamConfig->enabled ?? false),
            'user_config_enabled' => (bool) ($userConfig->enabled ?? false),
        ];
    }

    /**
     * 按指定的作用域解析配置（不依赖 Order，用于测试连接）
     */
    public function resolveForScope(string $scopeType, int $scopeId, string $provider): array
    {
        $provider = strtolower(trim($provider));
        if (!in_array($scopeType, ['team', 'user'], true) || !in_array($provider, [self::PROVIDER_CHINAPOST, self::PROVIDER_SZ56T], true)) {
            return ['source' => 'system', 'config' => []];
        }

        $switchKey = $scopeType === 'team' ? self::KEY_ENABLE_TEAM : self::KEY_ENABLE_USER;
        if (!$this->isSwitchOn(SystemConfig::getByKey($switchKey, '0'))) {
            return ['source' => 'system', 'config' => []];
        }

        $record = LogisticsConfig::query()
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->where('provider', $provider)
            ->first();

        if (!$record || !$record->enabled) {
            return ['source' => 'system', 'config' => []];
        }

        return [
            'source' => $scopeType,
            'config' => is_array($record->config) ? $record->config : [],
        ];
    }

    public function isSwitchOn($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    public function allowedProviderKeys(string $provider): array
    {
        $provider = strtolower(trim($provider));

        if ($provider === self::PROVIDER_CHINAPOST) {
            return [
                'test_authorization',
                'test_digest_key',
                'prod_authorization',
                'prod_digest_key',
                'agreement_code',
                'pickup_org_code',
            ];
        }

        if ($provider === self::PROVIDER_SZ56T) {
            return [
                'username',
                'password',
            ];
        }

        return [];
    }

    public function providerDefaultConfig(string $provider): array
    {
        $defaults = [];
        foreach ($this->allowedProviderKeys($provider) as $key) {
            $defaults[$key] = '';
        }

        return $defaults;
    }

    public function sanitizeProviderConfig(string $provider, array $input): array
    {
        $allowed = array_flip($this->allowedProviderKeys($provider));
        $sanitized = [];

        foreach ($input as $key => $value) {
            if (!isset($allowed[$key])) {
                continue;
            }
            $sanitized[$key] = is_scalar($value) || $value === null ? trim((string) ($value ?? '')) : '';
        }

        return $sanitized;
    }

    public function buildConfigRef(Order $order, string $provider): array
    {
        $resolved = $this->resolveForOrder($order, $provider);
        $config = $resolved['config'] ?? [];

        return [
            'source' => $resolved['source'] ?? 'system',
            'config' => $this->sanitizeProviderConfig($provider, $config),
            'frozen_at' => now()->toDateTimeString(),
        ];
    }

    public function resolveFromConfigRef(?array $configRef): ?array
    {
        if (!is_array($configRef)) {
            return null;
        }

        $config = $configRef['config'] ?? null;
        if (!is_array($config) || empty($config)) {
            return null;
        }

        return $config;
    }

    private function mergeNonEmpty(array $base, array $overlay): array
    {
        foreach ($overlay as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === '') {
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }
}
