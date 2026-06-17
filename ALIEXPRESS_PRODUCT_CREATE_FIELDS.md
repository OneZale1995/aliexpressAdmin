# AliExpress 新增产品字段清单

接口：`POST /api/v1/product/create`

生产环境：`https://openapi.aliexpress.ru`

说明：

- 单次请求最多创建 `1000` 个产品。
- 请求返回 `200 OK` 不代表产品已创建完成，需要使用返回的 `group_id` 调用任务查询接口确认状态。
- 4 小时内创建或更新产品总量最多 `100000` 个。

## 请求头

| 字段 | 是否必填 | 类型 | 说明 |
|---|---:|---|---|
| `Content-Type` | 是 | string | 固定为 `application/json` |
| `x-auth-token` | 是 | string | JWT 授权 Token |
| `x-request-locale` | 否 | string | API 响应语言，例如 `en_US` |

## 顶层字段

| 字段 | 是否必填 | 类型 | 说明 |
|---|---:|---|---|
| `products` | 是 | array | 产品对象数组，单次最多 `1000` 个产品 |

## `products[]` 产品字段

| 字段 | 是否必填 | 类型 | 说明 |
|---|---:|---|---|
| `aliexpress_category_id` | 是 | int | AliExpress 最末级类目 ID，必须是没有子类目的类目 |
| `external_category_id` | 否 | string | 外部系统类目 ID |
| `external_id` | 否 | string | 外部系统产品 ID，用于匹配任务和产品 |
| `attribute_list` | 条件必填 | array<object> | 产品属性。若类目存在必填属性，则必须传 |
| `freight_template_id` | 是 | int | 运费模板 ID |
| `language` | 是 | string | 产品默认语言，可选值：`ru`、`en`、`tr` |
| `main_image_urls_list` | 是 | array<string> | 主图链接，`1-6` 张，必须是图片直链 |
| `multi_language_description_list` | 是 | array<object> | 多语言商品描述 |
| `multi_language_subject_list` | 是 | array<object> | 多语言商品标题 |
| `package_length` | 是 | int | 包裹长度，单位 cm，范围 `1-700` |
| `package_width` | 是 | int | 包裹宽度，单位 cm，范围 `1-700` |
| `package_height` | 是 | int | 包裹高度，单位 cm，范围 `1-700` |
| `weight` | 是 | string | 含包装重量，单位 kg，范围 `0.01-700` |
| `product_unit` | 是 | int | 商品单位 |
| `shipping_lead_time` | 是 | int | 发货时间，单位天，范围 `1-30`，建议不超过 `5` |
| `package_type` | 否 | boolean | 销售方式：`true` 按批，`false` 按件 |
| `lot_num` | 条件必填 | string | 当 `package_type=true` 时必填，表示每批数量 |
| `size_chart_id` | 条件必填 | int | 服装、鞋、配饰、内衣等类目需要 |
| `bulk_discount` | 条件必填 | int | 批发折扣百分比。传了 `bulk_order` 时必填，范围 `1-99` |
| `bulk_order` | 条件必填 | int | 批发起订量。传了 `bulk_discount` 时必填，范围 `2-100000` |
| `sku_info_list` | 是 | array<object> | SKU / 商品规格列表 |
| `video` | 否 | object | 商品视频 |
| `hs_codes` | 条件必填 | array<object> | HS code。土耳其卖家需要传 |

## `attribute_list[]` 产品属性字段

| 字段 | 是否必填 | 类型 | 说明 |
|---|---:|---|---|
| `attribute_name` | 通常必填 | string | 属性名称，取自类目属性 `properties.name`；自定义属性时传自定义名称 |
| `attribute_name_id` | 通常必填 | string | 属性 ID，取自类目属性 `properties.id`；自定义属性传 `-1` |
| `attribute_value` | 通常必填 | string | 属性值，取自属性值字典 `values.name`；品牌属性不要传 |
| `attribute_value_id` | 通常必填 | string | 属性值 ID，取自属性值字典 `values.id`；自定义属性传 `-1` |
| `attribute_unit` | 否 | string | 属性单位名称 |
| `attribute_unit_id` | 条件必填 | int | 数值型属性需要传单位 ID |

## 品牌属性特殊规则

| 字段 | 值 | 说明 |
|---|---|---|
| `attribute_name` | 品牌名称 | 传品牌名称 |
| `attribute_name_id` | `2` | Brand 属性固定传 `2` |
| `attribute_value_id` | 品牌 ID | 来自品牌列表或类目属性值字典 |
| `attribute_value` | 不传 | 不要传该字段，避免报错，系统会自动补充 |

## `multi_language_description_list[]` 描述字段

| 字段 | 是否必填 | 类型 | 说明 |
|---|---:|---|---|
| `language` | 是 | string | 可选值：`tr`、`ru`、`en`、`tr_TR`、`ru_RU`、`en_US` |
| `web` | 是 | string | PC 端描述，HTML 或纯文本 |
| `mobile` | 是 | string | 移动端描述，HTML 或纯文本 |

## `multi_language_subject_list[]` 标题字段

| 字段 | 是否必填 | 类型 | 说明 |
|---|---:|---|---|
| `language` | 是 | string | 可选值：`tr`、`ru`、`en`、`tr_TR`、`ru_RU`、`en_US` |
| `subject` | 是 | string | 商品标题 |

## `sku_info_list[]` SKU 字段

| 字段 | 是否必填 | 类型 | 说明 |
|---|---:|---|---|
| `sku_code` | 是 | string | 商家系统中的 SKU 编码或条码 |
| `price` | 是 | string | 售价，支持小数 |
| `discount_price` | 否 | string | 折扣价，支持小数 |
| `inventory` | 是 | int | 库存，范围 `0-999999` |
| `sku_attributes_list` | 否/通常需要 | array<object> | SKU 规格属性，例如颜色、尺码 |
| `tnved_codes` | 条件必填 | array<string> | 部分类目需要；如不知道可传 `gtin` 让系统推导 |
| `gtin` | 否 | string | GTIN / GS1，可用于自动推导 `tnved_codes` 和 `okpd2_codes` |
| `okpd2_codes` | 条件必填 | array<string> | 部分类目需要；如不知道可传 `gtin` 让系统推导 |

## `sku_attributes_list[]` SKU 属性字段

| 字段 | 是否必填 | 类型 | 说明 |
|---|---:|---|---|
| `sku_attribute_name_id` | 是 | string | SKU 属性 ID，取自类目 `sku_properties.id` |
| `sku_attribute_value_id` | 是 | string | SKU 属性值 ID，取自属性值字典 `values.id`，查询时需传 `is_sku_property=true` |
| `sku_attribute_value_definition_name` | 否 | string | 商家自定义规格值名称，最多 `40` 字符 |
| `sku_image_url` | 否 | string | SKU 图片链接，仅支持 `has_customized_pic=true` 的属性 |

## `video` 视频字段

| 字段 | 是否必填 | 类型 | 说明 |
|---|---:|---|---|
| `video_url` | 否 | string | 视频链接，支持 `AVI`、`MOV`、`3GP`，最长 `31` 秒，最大 `2GB` |
| `preview_url` | 否 | string | 视频封面图链接，支持 `JPEG` 或 `PNG` |

## `hs_codes[]` 字段

| 字段 | 是否必填 | 类型 | 说明 |
|---|---:|---|---|
| `code` | 条件必填 | string | HS code，土耳其卖家需要 |

## `product_unit` 可选值

| 值 | 含义 |
|---:|---|
| `100000013` | pair / 双 |
| `100000014` | package / 包 |
| `100000015` | item / 件 |
| `100000017` | set / 套 |
| `100000019` | square meter / 平方米 |

## 最小必填结构参考

```json
{
  "products": [
    {
      "aliexpress_category_id": 200000361,
      "attribute_list": [],
      "freight_template_id": 24117182098,
      "language": "en",
      "main_image_urls_list": [
        "https://example.com/image-1.jpg"
      ],
      "multi_language_description_list": [
        {
          "language": "en",
          "web": "Product description",
          "mobile": "Product description"
        }
      ],
      "multi_language_subject_list": [
        {
          "language": "en",
          "subject": "Product title"
        }
      ],
      "package_length": 30,
      "package_width": 30,
      "package_height": 5,
      "weight": "2",
      "product_unit": 100000015,
      "shipping_lead_time": 5,
      "sku_info_list": [
        {
          "sku_code": "SKU001",
          "price": "10.99",
          "inventory": 100
        }
      ]
    }
  ]
}
```

注意：实际请求中，`attribute_list` 是否可以为空取决于具体类目是否有必填属性；SKU 是否需要 `sku_attributes_list` 也取决于类目是否有必填 SKU 属性。
