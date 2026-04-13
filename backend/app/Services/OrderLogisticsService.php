<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderLogistics;

class OrderLogisticsService
{
    public function current(Order $order): ?OrderLogistics
    {
        if ($order->relationLoaded('currentLogistics')) {
            return $order->getRelation('currentLogistics');
        }

        $logistics = $order->currentLogistics()->first();
        $order->setRelation('currentLogistics', $logistics);

        return $logistics;
    }

    public function syncPrimary(Order $order, array $attributes = [], bool $syncLegacySnapshot = true): OrderLogistics
    {
        $logistics = $order->logistics()->firstOrNew([
            'is_primary' => true,
        ]);

        if (!$logistics->exists) {
            $logistics->order()->associate($order);
            $logistics->is_primary = true;
        }

        $payload = $this->normalizeAttributes($attributes, $logistics);
        $logistics->fill($payload);
        $logistics->save();

        $order->setRelation('currentLogistics', $logistics);

        if ($syncLegacySnapshot) {
            $this->syncLegacySnapshot($order, $logistics);
        }

        return $logistics;
    }

    public function syncTracking(Order $order, ?string $trackingNumber, array $attributes = []): OrderLogistics
    {
        return $this->syncPrimary($order, array_merge($attributes, [
            'tracking_number' => $trackingNumber,
        ]));
    }

    public function resolveProviderCodeByTemplate(?string $templateCode): ?string
    {
        return match ($templateCode) {
            'offline_epacket' => 'chinapost',
            'offline_leiyi' => 'sz56t',
            'online' => 'aliexpress',
            default => null,
        };
    }

    private function normalizeAttributes(array $attributes, OrderLogistics $existing): array
    {
        $payload = [];
        $fillable = [
            'is_primary',
            'logistics_mode',
            'provider_code',
            'provider_name',
            'template_code',
            'external_order_id',
            'platform_logistic_order_id',
            'handover_list_id',
            'handover_list_status',
            'tracking_number',
            'tracking_url',
            'logistic_status',
        ];

        foreach ($fillable as $field) {
            if (array_key_exists($field, $attributes)) {
                $payload[$field] = $attributes[$field];
            }
        }

        if (array_key_exists('payload', $attributes)) {
            $currentPayload = is_array($existing->payload) ? $existing->payload : [];
            $incomingPayload = is_array($attributes['payload']) ? $attributes['payload'] : [];
            $payload['payload'] = array_replace_recursive($currentPayload, $incomingPayload);
        }

        if (array_key_exists('template_code', $payload) && !array_key_exists('provider_code', $payload)) {
            $payload['provider_code'] = $this->resolveProviderCodeByTemplate($payload['template_code']) ?: $existing->provider_code;
        }

        return $payload;
    }

    private function syncLegacySnapshot(Order $order, OrderLogistics $logistics): void
    {
        $legacy = [
            'logistics_type' => $this->resolveLegacyLogisticsType($logistics),
            'logistics_template' => $this->resolveLegacyTemplateCode($logistics),
            'tracking_number' => $logistics->tracking_number ?? '',
            'logistic_order_id' => $logistics->platform_logistic_order_id,
            'handover_list_id' => $logistics->handover_list_id,
            'handover_list_status' => $logistics->handover_list_status,
            'sz56t_order_id' => $logistics->provider_code === 'sz56t' ? $logistics->external_order_id : null,
        ];

        $dirty = [];
        foreach ($legacy as $field => $value) {
            if ($order->getRawOriginal($field) !== $value) {
                $dirty[$field] = $value;
            }
        }

        if (empty($dirty)) {
            return;
        }

        $order->forceFill($dirty);
        $order->saveQuietly();
    }

    private function resolveLegacyLogisticsType(OrderLogistics $logistics): string
    {
        if (!empty($logistics->logistics_mode)) {
            return (string) $logistics->logistics_mode;
        }

        return match ($logistics->provider_code) {
            'aliexpress' => 'FBS',
            'chinapost', 'sz56t', 'manual' => 'DBS',
            default => '',
        };
    }

    private function resolveLegacyTemplateCode(OrderLogistics $logistics): string
    {
        if (!empty($logistics->template_code)) {
            return (string) $logistics->template_code;
        }

        return match ($logistics->provider_code) {
            'chinapost' => 'offline_epacket',
            'sz56t' => 'offline_leiyi',
            default => 'online',
        };
    }
}