<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChinaPostService
{
    protected string $baseUrl;
    protected string $ecCompanyId;
    protected string $digestKey;
    protected string $mailType;
    protected string $whCode;
    protected bool $verifySsl;

    // 默认寄件人信息
    protected array $defaultSender;

    public function __construct()
    {
        $config = config('services.chinapost', []);
        $this->baseUrl = $config['base_url'] ?? 'https://211.156.197.248:443';
        $this->ecCompanyId = $config['ec_company_id'] ?? '';
        $this->digestKey = $config['digest_key'] ?? '';
        $this->mailType = $config['mail_type'] ?? '';
        $this->whCode = $config['wh_code'] ?? '';
        $this->verifySsl = $config['verify_ssl'] ?? false;

        $this->defaultSender = $config['sender'] ?? [
            'name' => '',
            'company' => '',
            'post_code' => '',
            'phone' => '',
            'mobile' => '',
            'email' => '',
            'nation' => 'CN',
            'province' => '',
            'city' => '',
            'county' => '',
            'address' => '',
            'linker' => '',
        ];
    }

    /**
     * 生成签名: Base64(MD5(content + key))
     */
    protected function sign(string $content): string
    {
        $md5 = md5($content . $this->digestKey, true); // raw binary
        return base64_encode($md5);
    }

    /**
     * 订单交运并返回运单号 (模式二 - 接口 2.3)
     * 适用于国际邮件(E邮宝等)
     */
    public function createOrder(Order $order, array $options = []): array
    {
        $bizProductNo = $options['biz_product_no'] ?? '001'; // 001=E邮宝, 002=挂号小包

        $logisticsInterface = $this->buildOrderPayload($order, $bizProductNo, $options);
        $jsonContent = json_encode($logisticsInterface, JSON_UNESCAPED_UNICODE);

        $url = $this->baseUrl . '/pcpErp-web/a/pcp/orderService/OrderReceiveBack';

        $params = [
            'logistics_interface' => $jsonContent,
            'data_digest' => $this->sign($jsonContent),
            'msg_type' => 'B2C_TRADE',
            'ecCompanyId' => $this->ecCompanyId,
            'data_type' => 'JSON',
            'biz_product_no' => $bizProductNo,
        ];

        Log::info('ChinaPost createOrder request', [
            'order_id' => $order->ae_order_id,
            'url' => $url,
            'biz_product_no' => $bizProductNo,
        ]);

        try {
            $response = Http::asForm()
                ->withOptions(['verify' => $this->verifySsl])
                ->timeout(30)
                ->post($url, $params);

            $data = $response->json();

            Log::info('ChinaPost createOrder response', [
                'order_id' => $order->ae_order_id,
                'response' => $data,
            ]);

            if (isset($data['success']) && filter_var($data['success'], FILTER_VALIDATE_BOOLEAN)) {
                $waybillNo = $data['waybillNo'] ?? '';
                return [
                    'success' => true,
                    'message' => '邮政下单成功',
                    'waybill_no' => $waybillNo,
                    'raw' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => '邮政下单失败: ' . ($data['msg'] ?? $data['reason'] ?? json_encode($data)),
                'error_code' => $data['reason'] ?? null,
                'raw' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('ChinaPost createOrder exception', [
                'order_id' => $order->ae_order_id,
                'message' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 获取面单 PDF (接口 2.4.1)
     */
    public function getLabel(string $waybillNo, string $pageType = 'RM'): array
    {
        $url = $this->baseUrl . '/pcpErp-web/a/pcp/surface/download';

        $params = [
            'barCode' => $waybillNo,
            'ecCompanyId' => $this->ecCompanyId,
            'dataDigest' => $this->sign($waybillNo),
            'version' => '2.0',
            'pageType' => $pageType,
        ];

        Log::info('ChinaPost getLabel request', [
            'waybill_no' => $waybillNo,
        ]);

        try {
            $response = Http::asForm()
                ->withOptions(['verify' => $this->verifySsl])
                ->timeout(30)
                ->post($url, $params);

            $data = $response->json();

            if (isset($data['success']) && $data['success'] === true) {
                $hexData = $data['data'] ?? '';
                // 验证返回签名
                $returnDigest = trim($data['digest'] ?? '');
                $localDigest = trim($this->sign($hexData));

                if ($returnDigest && $returnDigest !== $localDigest) {
                    Log::warning('ChinaPost getLabel digest mismatch', [
                        'waybill_no' => $waybillNo,
                    ]);
                }

                // 将十六进制数据转为二进制 PDF
                $pdfContent = hex2bin($hexData);

                return [
                    'success' => true,
                    'message' => '面单获取成功',
                    'pdf_content' => $pdfContent,
                    'waybill_no' => $waybillNo,
                ];
            }

            return [
                'success' => false,
                'message' => '面单获取失败: ' . ($data['msg'] ?? ''),
                'error_code' => $data['err_code'] ?? null,
                'raw' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('ChinaPost getLabel exception', [
                'waybill_no' => $waybillNo,
                'message' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 邮件撤单 (接口 2.7)
     */
    public function cancelOrder(string $waybillNo): array
    {
        $url = $this->baseUrl . '/pcpErp-web/a/pcp/orderService/cancelOrder';

        $params = [
            'waybillNo' => $waybillNo,
            'senderNo' => $this->ecCompanyId,
            'digest' => $this->sign($waybillNo),
        ];

        try {
            $response = Http::asForm()
                ->withOptions(['verify' => $this->verifySsl])
                ->timeout(30)
                ->post($url, $params);

            $data = $response->json();

            if (isset($data['flag']) && filter_var($data['flag'], FILTER_VALIDATE_BOOLEAN)) {
                return [
                    'success' => true,
                    'message' => $data['message'] ?? '撤单成功',
                ];
            }

            return [
                'success' => false,
                'message' => $data['message'] ?? '撤单失败',
            ];
        } catch (\Exception $e) {
            Log::error('ChinaPost cancelOrder exception', [
                'waybill_no' => $waybillNo,
                'message' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 获取收寄信息(资费、重量) (接口 2.5)
     */
    public function getOrderFee(string $waybillNos): array
    {
        $url = $this->baseUrl . '/pcpErp-web/a/pcp/orderFeeService/getOrderFee';

        $params = [
            'waybillNos' => $waybillNos,
            'dataDigest' => $this->sign($waybillNos),
            'ecCompanyId' => $this->ecCompanyId,
        ];

        try {
            $response = Http::asForm()
                ->withOptions(['verify' => $this->verifySsl])
                ->timeout(30)
                ->post($url, $params);

            $data = $response->json();

            if (isset($data['return_success']) && $data['return_success'] === 'true') {
                return [
                    'success' => true,
                    'data' => $data['data'] ?? [],
                ];
            }

            return [
                'success' => false,
                'message' => $data['return_msg'] ?? '查询失败',
                'error_code' => $data['return_reason'] ?? null,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 构建交运报文
     */
    protected function buildOrderPayload(Order $order, string $bizProductNo, array $options = []): array
    {
        $items = $order->items ?? collect();
        $totalWeight = 0;
        $totalValue = 0;
        $cargoItems = [];

        foreach ($items as $index => $item) {
            $qty = max(1, (int) ($item->quantity ?? 1));
            $price = (float) ($item->item_price ?? 0);
            $weight = (int) ($options['item_weight'] ?? 100); // 默认100克/件

            $totalWeight += $weight * $qty;
            $totalValue += $price * $qty;

            $cargoItems[] = [
                'cargo_no' => (string) ($item->sku_id ?: ($index + 1)),
                'cargo_name' => mb_substr($item->item_title ?: '商品', 0, 50),
                'cargo_name_en' => mb_substr($item->item_title ?: 'Product', 0, 50),
                'cargo_type_name' => mb_substr($item->item_title ?: '商品', 0, 50),
                'cargo_type_name_en' => mb_substr($item->item_title ?: 'Product', 0, 50),
                'cargo_origin_name' => 'CN',
                'cargo_link' => '',
                'cargo_quantity' => $qty,
                'cargo_value' => round($price, 2),
                'cost' => round($price, 2),
                'cargo_currency' => 'USD',
                'cargo_weight' => $weight,
                'cargo_description' => mb_substr($item->item_title ?: 'Product', 0, 200),
                'cargo_serial' => '',
                'unit' => '个',
                'intemsize' => '',
            ];
        }

        // 如果没有订单项，创建一个占位项
        if (empty($cargoItems)) {
            $totalWeight = (int) ($options['weight'] ?? 100);
            $totalValue = (float) ($order->total_amount ?? 1);
            $cargoItems[] = [
                'cargo_no' => '1',
                'cargo_name' => '商品',
                'cargo_name_en' => 'Product',
                'cargo_type_name' => '商品',
                'cargo_type_name_en' => 'Product',
                'cargo_origin_name' => 'CN',
                'cargo_link' => '',
                'cargo_quantity' => 1,
                'cargo_value' => round($totalValue, 2),
                'cost' => round($totalValue, 2),
                'cargo_currency' => 'USD',
                'cargo_weight' => $totalWeight,
                'cargo_description' => 'Product',
                'cargo_serial' => '',
                'unit' => '个',
                'intemsize' => '',
            ];
        }

        $weight = (int) ($options['weight'] ?? $totalWeight);

        // 收件人信息 - 从订单中提取
        $receiver = [
            'name' => $order->receiver_name ?: ($order->buyer_name ?: 'Customer'),
            'company' => '',
            'post_code' => $order->receiver_zip_code ?: '',
            'phone' => $order->receiver_phone ?: '',
            'mobile' => $order->receiver_mobile ?: ($order->receiver_phone ?: ''),
            'email' => '',
            'nation' => $order->receiver_country_code ?: 'RU',
            'province' => $order->receiver_region ?: '',
            'city' => $order->receiver_city ?: '',
            'county' => $order->receiver_district ?: '',
            'address' => $order->receiver_street ?: ($order->delivery_address ?: ''),
            'linker' => $order->receiver_name ?: ($order->buyer_name ?: 'Customer'),
        ];

        return [
            'created_time' => now()->format('Y-m-d H:i:s'),
            'sender_no' => $this->ecCompanyId,
            'mailType' => $this->mailType,
            'wh_code' => $options['wh_code'] ?? $this->whCode,
            'logistics_order_no' => (string) $order->ae_order_id,
            'batch_no' => '',
            'biz_product_no' => $bizProductNo,
            'weight' => $weight,
            'volume' => null,
            'length' => $options['length'] ?? null,
            'width' => $options['width'] ?? null,
            'height' => $options['height'] ?? null,
            'postage_total' => null,
            'postage_currency' => 'USD',
            'contents_total_weight' => $totalWeight,
            'contents_total_value' => round($totalValue, 2),
            'transfer_type' => 'HK',
            'battery_flag' => $options['battery_flag'] ?? '0',
            'pickup_notes' => '',
            'insurance_flag' => '',
            'insurance_amount' => null,
            'undelivery_option' => 2,
            'valuable_flag' => '0',
            'declare_source' => '2',
            'declare_type' => '1',
            'declare_curr_code' => 'USD',
            'printcode' => '',
            'barcode' => (string) $order->ae_order_id,
            'forecastshut' => '0',
            'mail_sign' => '2',
            'mail_flag' => '1', // saleofgood
            'tax_id' => '',
            's_tax_id' => '',
            'prepayment_of_vat' => '',
            'pickup_flag' => 0,
            'sender' => $this->defaultSender,
            'receiver' => $receiver,
            'items' => $cargoItems,
        ];
    }
}
