<?php

namespace App\Services;

use App\Models\AliCategoryProperty;
use App\Models\AliCategoryPropertyValue;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Support\Facades\Http;

class AliExpressProductCreateService
{
    private array $propertyNameCache = [];

    private array $propertyValueCache = [];

    public function buildPayload(Product $product, array $options = []): array
    {
        $images = $this->extractProductImages($product);
        $subjects = $this->buildSubjectList($product);
        $descriptions = $this->buildDescriptionList($product);
        $skus = $this->buildSkuList($product, (bool) ($options['unique_sku'] ?? true));

        $this->assertNotEmpty($product->category_id, '商品缺少 AliExpress 类目 ID');
        $this->assertNotEmpty($product->freight_template_id, '商品缺少运费模板 ID');
        $this->assertNotEmpty($images, '商品缺少主图');
        $this->assertNotEmpty($subjects, '商品缺少标题');
        $this->assertNotEmpty($descriptions, '商品缺少描述');
        $this->assertNotEmpty($skus, '商品缺少 SKU');

        $productPayload = [
            'aliexpress_category_id' => (int) $product->category_id,
            'external_category_id' => (string) $product->category_id,
            'external_id' => $this->resolveExternalId($product, $options),
            'attribute_list' => $this->buildAttributeList($product),
            'freight_template_id' => (int) ($options['freight_template_id'] ?? $product->freight_template_id),
            'language' => (string) ($options['language'] ?? 'ru'),
            'main_image_urls_list' => $images,
            'multi_language_description_list' => $descriptions,
            'multi_language_subject_list' => $subjects,
            'package_length' => max(1, (int) $product->package_length),
            'package_width' => max(1, (int) $product->package_width),
            'package_height' => max(1, (int) $product->package_height),
            'weight' => (string) max(0.01, (float) $product->gross_weight),
            'product_unit' => (int) ($options['product_unit'] ?? 100000015),
            'shipping_lead_time' => max(1, min(30, (int) ($options['shipping_lead_time'] ?? $product->delivery_time ?: 5))),
            'sku_info_list' => $skus,
        ];

        if ($product->package_type !== null) {
            $productPayload['package_type'] = (bool) $product->package_type;
        }

        if ((int) $product->bulk_discount >= 1 && (int) $product->bulk_order >= 2) {
            $productPayload['bulk_discount'] = (int) $product->bulk_discount;
            $productPayload['bulk_order'] = (int) $product->bulk_order;
        }

        $lotNum = (int) $product->lot_num;
        if (($productPayload['package_type'] ?? false) && $lotNum > 0) {
            $productPayload['lot_num'] = (string) $lotNum;
        }

        if ($product->sizechart_id !== '') {
            $productPayload['size_chart_id'] = (int) $product->sizechart_id;
        }

        return ['products' => [$productPayload]];
    }

    public function createFromProduct(Shop $shop, Product $product, array $options = []): array
    {
        if (!$shop->access_token) {
            throw new \RuntimeException('店铺未配置 access_token');
        }

        $payload = $this->buildPayload($product, $options);

        return $this->createPayload($shop, $payload, $options);
    }

    public function createPayload(Shop $shop, array $payload, array $options = []): array
    {
        if (!$shop->access_token) {
            throw new \RuntimeException('店铺未配置 access_token');
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-auth-token' => (string) $shop->access_token,
            'x-request-locale' => (string) ($options['request_locale'] ?? 'en_US'),
        ])
            ->withOptions(['verify' => (bool) config('services.aliexpress.verify_ssl', false)])
            ->timeout((int) ($options['timeout'] ?? 60))
            ->post(rtrim((string) config('services.aliexpress.base_url'), '/') . '/api/v1/product/create', $payload);

        return [
            'http_status' => $response->status(),
            'payload' => $payload,
            'response' => $response->json() ?? $response->body(),
        ];
    }

    public function getTaskStatus(Shop $shop, string $groupId): array
    {
        if (!$shop->access_token) {
            throw new \RuntimeException('店铺未配置 access_token');
        }

        $response = Http::withHeaders([
            'x-auth-token' => (string) $shop->access_token,
            'x-request-locale' => 'en_US',
        ])
            ->withOptions(['verify' => (bool) config('services.aliexpress.verify_ssl', false)])
            ->timeout(60)
            ->get(rtrim((string) config('services.aliexpress.base_url'), '/') . '/api/v1/tasks', [
                'group_id' => $groupId,
            ]);

        return [
            'http_status' => $response->status(),
            'response' => $response->json() ?? $response->body(),
        ];
    }

    private function resolveExternalId(Product $product, array $options): string
    {
        $externalId = trim((string) ($options['external_id'] ?? ''));
        if ($externalId !== '') {
            return $externalId;
        }

        return sprintf('copy-%s-%s', $product->ae_item_id, now()->format('YmdHis'));
    }

    private function buildSubjectList(Product $product): array
    {
        $items = [];
        foreach ((array) ($product->subjects ?? $product->raw_data['subject'] ?? []) as $subject) {
            if (!is_array($subject)) {
                continue;
            }

            $name = trim((string) ($subject['name'] ?? $subject['subject'] ?? ''));
            if ($name === '') {
                continue;
            }

            $items[] = [
                'language' => $this->normalizeLanguage((string) ($subject['locale'] ?? $subject['language'] ?? 'en')),
                'subject' => $name,
            ];
        }

        if (empty($items)) {
            $title = trim((string) ($product->title_en ?: $product->title_ru));
            if ($title !== '') {
                $items[] = ['language' => 'en', 'subject' => $title];
            }
        }

        return $this->uniqueByLanguage($items);
    }

    private function buildDescriptionList(Product $product): array
    {
        $items = [];
        foreach ((array) ($product->descriptions ?? $product->raw_data['descriptions'] ?? []) as $description) {
            if (!is_array($description)) {
                continue;
            }

            $web = trim((string) ($description['web'] ?? $description['web_detail'] ?? $description['detail'] ?? ''));
            $mobile = trim((string) ($description['mobile'] ?? $description['mobile_detail'] ?? ''));
            if ($web === '' && $mobile === '') {
                continue;
            }

            if ($web === '') {
                $web = $mobile;
            }
            if ($mobile === '') {
                $mobile = $web;
            }

            $items[] = [
                'language' => $this->normalizeLanguage((string) ($description['locale'] ?? $description['language'] ?? 'en')),
                'web' => $web,
                'mobile' => $mobile,
            ];
        }

        return $this->uniqueByLanguage($items);
    }

    private function buildAttributeList(Product $product): array
    {
        $items = [];
        foreach ((array) ($product->properties ?? $product->raw_data['property'] ?? []) as $property) {
            if (!is_array($property)) {
                continue;
            }

            $nameId = (string) ($property['name_id'] ?? $property['property_id'] ?? '');
            $valueId = (string) ($property['value_id'] ?? '');
            if ($nameId === '' || $valueId === '' || $valueId === '0') {
                continue;
            }

            $name = $this->resolvePropertyName($product, $nameId, false, (string) ($property['name'] ?? ''));
            $value = $this->resolvePropertyValue($product, $nameId, $valueId, false, (string) ($property['value'] ?? ''));
            if ($name === '' || $value === '') {
                continue;
            }

            $item = [
                'attribute_name' => $name,
                'attribute_name_id' => $nameId,
                'attribute_value' => $value,
                'attribute_value_id' => $valueId,
            ];

            $unitId = (int) ($property['unit_id'] ?? 0);
            if ($unitId > 0) {
                $item['attribute_unit_id'] = $unitId;
            }

            $unitName = trim((string) ($property['unit_name'] ?? ''));
            if ($unitName !== '') {
                $item['attribute_unit'] = $unitName;
            }

            $items[] = $item;
        }

        return $items;
    }

    private function buildSkuList(Product $product, bool $uniqueSku): array
    {
        $items = [];
        $index = 1;
        foreach ((array) ($product->skus ?? $product->raw_data['sku'] ?? []) as $sku) {
            if (!is_array($sku)) {
                continue;
            }

            $sourceSkuCode = trim((string) ($sku['code'] ?? $sku['barcode'] ?? $sku['sku_id'] ?? $sku['id'] ?? ''));
            $price = trim((string) ($sku['price'] ?? $product->price ?? ''));
            if ($sourceSkuCode === '' || $price === '') {
                continue;
            }

            $item = [
                'sku_code' => $uniqueSku ? $this->makeUniqueSkuCode($product, $index) : $sourceSkuCode,
                'price' => $price,
                'inventory' => max(0, (int) ($sku['ipm_sku_stock'] ?? $sku['inventory'] ?? $sku['quantity'] ?? 0)),
            ];

            $discountPrice = trim((string) ($sku['discount_price'] ?? ''));
            if ($discountPrice !== '') {
                $item['discount_price'] = $discountPrice;
            }

            $skuAttributes = $this->buildSkuAttributeList($product, (array) ($sku['property'] ?? []));
            if (!empty($skuAttributes)) {
                $item['sku_attributes_list'] = $skuAttributes;
            }

            $items[] = $item;
            $index++;
        }

        return $items;
    }

    private function buildSkuAttributeList(Product $product, array $properties): array
    {
        $items = [];
        foreach ($properties as $property) {
            if (!is_array($property)) {
                continue;
            }

            $nameId = (string) ($property['name_id'] ?? $property['property_id'] ?? '');
            $valueId = (string) ($property['value_id'] ?? '');
            if ($nameId === '' || $valueId === '' || $valueId === '0') {
                continue;
            }

            $item = [
                'sku_attribute_name_id' => $nameId,
                'sku_attribute_value_id' => $valueId,
            ];

            $definitionName = $this->resolvePropertyValue($product, $nameId, $valueId, true, (string) ($property['value'] ?? ''));
            if ($definitionName !== '') {
                $item['sku_attribute_value_definition_name'] = mb_substr($definitionName, 0, 40);
            }

            $imageUrl = trim((string) ($property['sku_image_url'] ?? $property['image_url'] ?? ''));
            if ($imageUrl !== '') {
                $item['sku_image_url'] = $imageUrl;
            }

            $items[] = $item;
        }

        return $items;
    }

    private function makeUniqueSkuCode(Product $product, int $index): string
    {
        return sprintf('copy-%s-%s-%d', $product->ae_item_id, now()->format('YmdHis'), $index);
    }

    private function resolvePropertyName(Product $product, string $propertyId, bool $isSkuProperty, string $fallback): string
    {
        $fallback = trim($fallback);
        if ($fallback !== '') {
            return $fallback;
        }

        $cacheKey = implode(':', [(string) $product->category_id, $propertyId, $isSkuProperty ? '1' : '0']);
        if (array_key_exists($cacheKey, $this->propertyNameCache)) {
            return $this->propertyNameCache[$cacheKey];
        }

        return $this->propertyNameCache[$cacheKey] = (string) AliCategoryProperty::query()
            ->where('category_id', (string) $product->category_id)
            ->where('property_id', $propertyId)
            ->where('is_sku_property', $isSkuProperty)
            ->value('name');
    }

    private function resolvePropertyValue(Product $product, string $propertyId, string $valueId, bool $isSkuProperty, string $fallback): string
    {
        $fallback = trim($fallback);
        if ($fallback !== '') {
            return $fallback;
        }

        $cacheKey = implode(':', [(string) $product->category_id, $propertyId, $valueId, $isSkuProperty ? '1' : '0']);
        if (array_key_exists($cacheKey, $this->propertyValueCache)) {
            return $this->propertyValueCache[$cacheKey];
        }

        return $this->propertyValueCache[$cacheKey] = (string) AliCategoryPropertyValue::query()
            ->where('category_id', (string) $product->category_id)
            ->where('property_id', $propertyId)
            ->where('value_id', $valueId)
            ->where('is_sku_property', $isSkuProperty)
            ->orderByRaw("CASE WHEN shipping_template_id = '' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->value('name');
    }

    private function extractProductImages(Product $product): array
    {
        $images = [];
        if ((string) $product->main_image_url !== '') {
            $images[] = (string) $product->main_image_url;
        }

        $this->appendImageUrls($images, $product->media);
        $this->appendImageUrls($images, $product->marketing_images);
        $this->appendImageUrls($images, $product->raw_data['media'] ?? null);
        $this->appendImageUrls($images, $product->raw_data['marketing_images'] ?? null);

        return array_values(array_slice(array_unique(array_filter($images)), 0, 6));
    }

    private function appendImageUrls(array &$images, mixed $source): void
    {
        if (is_string($source)) {
            $source = trim($source);
            if ($source !== '' && preg_match('#^https?://#i', $source)) {
                $images[] = $source;
            }

            return;
        }

        if (!is_array($source)) {
            return;
        }

        foreach ($source as $key => $value) {
            if (is_string($value) && preg_match('#^https?://#i', trim($value))) {
                $images[] = trim($value);
                continue;
            }

            if (is_array($value)) {
                $this->appendImageUrls($images, $value);
                continue;
            }

            if (is_string($key) && is_string($value) && in_array($key, ['url', 'src', 'image', 'image_url', 'imageUrl', 'main_image_url'], true)) {
                $images[] = trim($value);
            }
        }
    }

    private function normalizeLanguage(string $language): string
    {
        return match ($language) {
            'ru_RU' => 'ru',
            'en_US' => 'en',
            'tr_TR' => 'tr',
            default => in_array($language, ['ru', 'en', 'tr'], true) ? $language : 'en',
        };
    }

    private function uniqueByLanguage(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $language = (string) ($item['language'] ?? '');
            if ($language !== '' && !isset($result[$language])) {
                $result[$language] = $item;
            }
        }

        return array_values($result);
    }

    private function assertNotEmpty(mixed $value, string $message): void
    {
        if (empty($value)) {
            throw new \InvalidArgumentException($message);
        }
    }
}
