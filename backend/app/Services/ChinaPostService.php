<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChinaPostService
{
    protected string $baseUrl;
    protected string $ecCompanyId;
    protected string $authorization;
    protected string $digestKey;
    protected string $mailType;
    protected string $whCode;
    protected bool $verifySsl;
    protected array $apiCodes;
    protected array $paths;
    protected array $publicConfig;

    // 默认寄件人信息
    protected array $defaultSender;

    public function __construct()
    {
        $config = config('services.chinapost', []);
        $this->baseUrl = $config['base_url'] ?? 'https://211.156.197.248:443';
        $this->ecCompanyId = $config['ec_company_id'] ?? '';
        $this->authorization = $config['authorization'] ?? '';
        $this->digestKey = $config['digest_key'] ?? '';
        $this->mailType = $config['mail_type'] ?? '';
        $this->whCode = $config['wh_code'] ?? '';
        $this->verifySsl = $config['verify_ssl'] ?? false;
        $this->apiCodes = $config['api_codes'] ?? [];
        $this->paths = $config['paths'] ?? [];
        $this->publicConfig = $config['public'] ?? [];

        $this->defaultSender = $config['sender'] ?? [
            'name' => '',
            'company' => '',
            'post_code' => '',
            'phone' => '',
            'mobile' => '',
            'email' => '',
            'id_type' => '',
            'id_no' => '',
            'nation' => 'CN',
            'province' => '',
            'city' => '',
            'county' => '',
            'address' => '',
            'gis' => '',
            'linker' => '',
        ];
    }

    protected function thirdPartyLog()
    {
        return Log::channel('third_party');
    }

    /**
     * 生成签名: Base64(MD5(content + key))
     */
    protected function sign(string $content): string
    {
        $md5 = md5($content . $this->digestKey, true); // raw binary
        return base64_encode($md5);
    }

    protected function path(string $key, string $default): string
    {
        return $this->baseUrl . ($this->paths[$key] ?? $default);
    }

    protected function public(string $key, $default = null)
    {
        return $this->publicConfig[$key] ?? $default;
    }

    protected function mergeSavedCreateOrderOptions(Order $order, array $options = []): array
    {
        $savedRequest = data_get($order, 'currentLogistics.payload.chinapost.request');
        if (!is_array($savedRequest)) {
            return $options;
        }

        $fieldMap = [
            'apiCode' => 'api_code',
            'senderNo' => 'sender_no',
            'msgType' => 'msg_type',
            'version' => 'version',
            'productType' => 'product_type',
            'userCode' => 'user_code',
            'biz_product_no' => 'biz_product_no',
        ];

        foreach ($fieldMap as $requestKey => $optionKey) {
            if (array_key_exists($optionKey, $options) && $options[$optionKey] !== null && $options[$optionKey] !== '') {
                continue;
            }

            $value = $savedRequest[$requestKey] ?? null;
            if ($value !== null && $value !== '') {
                $options[$optionKey] = $value;
            }
        }

        if ((!array_key_exists('logistics_interface', $options) || !is_array($options['logistics_interface']))
            && is_array($savedRequest['logistics_interface'] ?? null)) {
            $options['logistics_interface'] = $savedRequest['logistics_interface'];
        }

        return $options;
    }

    public function previewCreateOrderRequest(Order $order, array $options = []): array
    {
        $options = $this->mergeSavedCreateOrderOptions($order, $options);

        $bizProductNo = '019';
        $productType = (string) ($options['product_type'] ?? $this->public('product_type', 'E邮宝'));
        $msgType = (string) ($options['msg_type'] ?? $this->public('open_msg_type', '0'));
        $openVersion = (string) ($options['version'] ?? $this->public('open_version', 'V1.0.0'));
        $userCode = (string) ($options['user_code'] ?? $this->public('user_code', ''));
        $apiCode = (string) ($options['api_code'] ?? ($this->apiCodes['create_order'] ?? '110001'));
        $logisticsInterface = is_array($options['logistics_interface'] ?? null)
            ? $this->buildPayloadFromGivenLogisticsInterface($order, $bizProductNo, $options)
            : $this->buildOrderPayload($order, $bizProductNo, $options);

        $logisticsInterface['biz_product_no'] = $bizProductNo;

        $senderNo = (string) (
            $options['sender_no']
            ?? data_get($options, 'logistics_interface.sender_no')
            ?? ($logisticsInterface['sender_no'] ?? '')
            ?? $this->ecCompanyId
        );

        if ($senderNo === '') {
            return ['success' => false, 'message' => '缺少 sender_no（可在请求报文或 CHINAPOST_EC_COMPANY_ID 中提供）'];
        }

        $request = [
            'apiCode' => $apiCode,
            'senderNo' => $senderNo,
            'authorization' => $this->authorization !== '' ? '[hidden]' : '',
            'msgType' => $msgType,
            'timeStamp' => now()->format('Y-m-d H:i:s'),
            'version' => $openVersion,
            'productType' => $productType,
            'biz_product_no' => $bizProductNo,
            'logistics_interface' => $logisticsInterface,
        ];

        if ($userCode !== '') {
            $request['userCode'] = $userCode;
        }

        return [
            'success' => true,
            'request' => $request,
        ];
    }

    /**
     * 订单交运并返回运单号 (模式二 - 接口 2.3)
     * 适用于国际邮件(E邮宝等)
     */
    public function createOrder(Order $order, array $options = []): array
    {
        if ($this->authorization === '') {
            return ['success' => false, 'message' => '未配置 CHINAPOST_AUTHORIZATION'];
        }

        if ($this->digestKey === '') {
            return ['success' => false, 'message' => '未配置 CHINAPOST_DIGEST_KEY'];
        }

        $preview = $this->previewCreateOrderRequest($order, $options);
        if (!$preview['success']) {
            return $preview;
        }

        $request = $preview['request'];
        $bizProductNo = (string) ($request['biz_product_no'] ?? '019');
        $productType = (string) ($request['productType'] ?? 'E邮宝');
        $msgType = (string) ($request['msgType'] ?? '0');
        $openVersion = (string) ($request['version'] ?? 'V1.0.0');
        $apiCode = (string) ($request['apiCode'] ?? ($this->apiCodes['create_order'] ?? '110001'));
        $senderNo = (string) ($request['senderNo'] ?? '');
        $logisticsInterface = $request['logistics_interface'] ?? [];

        $jsonContent = json_encode($logisticsInterface, JSON_UNESCAPED_UNICODE);
        $encryptedLogitcsInterface = $this->encryptLogitcsInterface($jsonContent, $this->digestKey);
        if ($encryptedLogitcsInterface === null) {
            return ['success' => false, 'message' => 'logitcsInterface 加密失败，请检查 CHINAPOST_DIGEST_KEY 是否正确'];
        }

        $url = $this->path('open_api', '/amp-prod-api/f/amp/api/open');
        $params = [
            'apiCode' => (string) $apiCode,
            'senderNo' => $senderNo,
            'authorization' => $this->authorization,
            'msgType' => $msgType,
            'timeStamp' => now()->format('Y-m-d H:i:s'),
            'version' => $openVersion,
            'logitcsInterface' => $encryptedLogitcsInterface,
            'productType' => $productType,
            'biz_product_no' => $bizProductNo,
        ];

        if (!empty($request['userCode'])) {
            $params['userCode'] = (string) $request['userCode'];
        }

        $this->thirdPartyLog()->info('ChinaPost openApi createOrder request', [
            'order_id' => $order->ae_order_id,
            'url' => $url,
            'api_code' => $apiCode,
            'sender_no' => $senderNo,
            'msg_type' => $msgType,
            'version' => $openVersion,
            'product_type' => $productType,
            'biz_product_no' => $bizProductNo,
        ]);

        try {
            $response = Http::asForm()
                ->withOptions(['verify' => $this->verifySsl])
                ->timeout(30)
                ->post($url, $params);

            $data = $response->json();
            $retBody = $this->decodeRetBody($data['retBody'] ?? null);

            $this->thirdPartyLog()->info('ChinaPost openApi createOrder response', [
                'order_id' => $order->ae_order_id,
                'status' => $response->status(),
                'response' => $data,
            ]);

            if (!empty($data['success']) && (($data['retCode'] ?? '') === '00000' || ($data['retCode'] ?? '') === '')) {
                $waybillNo = (string) (
                    data_get($retBody, 'waybill_no')
                    ?? data_get($retBody, 'waybillNo')
                    ?? data_get($retBody, 'cbWaybillNo')
                    ?? ($data['waybillNo'] ?? '')
                );

                return [
                    'success' => true,
                    'message' => '邮政下单成功',
                    'waybill_no' => $waybillNo,
                    'cb_waybill_no' => (string) (data_get($retBody, 'cbWaybillNo') ?? ''),
                    'ret_code' => $data['retCode'] ?? null,
                    'ret_msg' => $data['retMsg'] ?? null,
                    'ret_body' => $retBody,
                    'raw' => $data,
                    'request' => $request,
                ];
            }

            return [
                'success' => false,
                'message' => '邮政下单失败: ' . ($data['retMsg'] ?? '未知错误'),
                'error_code' => $data['retCode'] ?? null,
                'ret_body' => $retBody,
                'raw' => $data,
                'request' => $request,
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            $this->thirdPartyLog()->error('ChinaPost openApi createOrder exception', [
                'order_id' => $order->ae_order_id,
                'message' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage(), 'request' => $request];
        }
    }

    /**
     * 条码分配（110002）
     */
    public function allocateBarcode(Order $order, array $options = []): array
    {
        if ($this->authorization === '') {
            return ['success' => false, 'message' => '未配置 CHINAPOST_AUTHORIZATION'];
        }

        if ($this->digestKey === '') {
            return ['success' => false, 'message' => '未配置 CHINAPOST_DIGEST_KEY'];
        }

        $apiCode = $options['api_code'] ?? ($this->apiCodes['allocate_barcode'] ?? '110002');
        $msgType = (string) ($options['msg_type'] ?? $this->public('open_msg_type', '0'));
        $openVersion = (string) ($options['version'] ?? $this->public('open_version', 'V1.0.0'));
        $userCode = (string) ($options['user_code'] ?? $this->public('user_code', ''));

        $logisticsInterface = is_array($options['logistics_interface'] ?? null)
            ? $this->buildBarcodePayloadFromGivenInterface($order, $options)
            : $this->buildBarcodeAllocationPayload($order, $options);

        $senderNo = (string) (
            $options['sender_no']
            ?? data_get($options, 'logistics_interface.order.0.ecCompanyId')
            ?? data_get($logisticsInterface, 'order.0.ecCompanyId')
            ?? $this->ecCompanyId
        );

        if ($senderNo === '') {
            return ['success' => false, 'message' => '缺少 senderNo/ecCompanyId'];
        }

        $jsonContent = json_encode($logisticsInterface, JSON_UNESCAPED_UNICODE);
        $encryptedLogitcsInterface = $this->encryptLogitcsInterface($jsonContent, $this->digestKey);
        if ($encryptedLogitcsInterface === null) {
            return ['success' => false, 'message' => 'logitcsInterface 加密失败，请检查 CHINAPOST_DIGEST_KEY 是否正确'];
        }

        $url = $this->path('open_api', '/amp-prod-api/f/amp/api/open');
        $params = [
            'apiCode' => (string) $apiCode,
            'senderNo' => $senderNo,
            'authorization' => $this->authorization,
            'msgType' => $msgType,
            'timeStamp' => now()->format('Y-m-d H:i:s'),
            'version' => $openVersion,
            'logitcsInterface' => $encryptedLogitcsInterface,
        ];

        if ($userCode !== '') {
            $params['userCode'] = $userCode;
        }

        $this->thirdPartyLog()->info('ChinaPost allocateBarcode request', [
            'order_id' => $order->ae_order_id,
            'url' => $url,
            'api_code' => $apiCode,
            'sender_no' => $senderNo,
        ]);

        try {
            $response = Http::asForm()
                ->withOptions(['verify' => $this->verifySsl])
                ->timeout(30)
                ->post($url, $params);

            $data = $response->json();
            $retBody = $this->decodeRetBody($data['retBody'] ?? null);

            $this->thirdPartyLog()->info('ChinaPost allocateBarcode response', [
                'order_id' => $order->ae_order_id,
                'status' => $response->status(),
                'response' => $data,
            ]);

            if (!empty($data['success']) && (($data['retCode'] ?? '') === '00000' || ($data['retCode'] ?? '') === '')) {
                [$trackingNumber, $cbWaybillNo] = $this->extractWaybillNumbers($retBody);

                return [
                    'success' => true,
                    'message' => '邮政条码分配成功',
                    'tracking_number' => $trackingNumber,
                    'waybill_no' => $trackingNumber,
                    'cb_waybill_no' => $cbWaybillNo,
                    'ret_code' => $data['retCode'] ?? null,
                    'ret_msg' => $data['retMsg'] ?? null,
                    'ret_body' => $retBody,
                    'raw' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => '邮政条码分配失败: ' . ($data['retMsg'] ?? '未知错误'),
                'error_code' => $data['retCode'] ?? null,
                'ret_body' => $retBody,
                'raw' => $data,
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            $this->thirdPartyLog()->error('ChinaPost allocateBarcode exception', [
                'order_id' => $order->ae_order_id,
                'message' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 获取面单（120001，邮件产品）
     */
    public function getLabel(string $waybillNo, string $pageType = 'RM', array $options = []): array
    {
        if ($this->authorization === '') {
            return ['success' => false, 'message' => '未配置 CHINAPOST_AUTHORIZATION'];
        }

        if ($this->digestKey === '') {
            return ['success' => false, 'message' => '未配置 CHINAPOST_DIGEST_KEY'];
        }

        $labelAk = (string) ($options['ak'] ?? $this->public('label_ak', ''));
        if ($labelAk === '') {
            return ['success' => false, 'message' => '未配置 CHINAPOST_LABEL_AK'];
        }

        $apiCode = $options['api_code'] ?? ($this->apiCodes['get_label'] ?? '120001');
        $msgType = (string) ($options['msg_type'] ?? $this->public('open_msg_type', '0'));
        $openVersion = (string) ($options['version'] ?? $this->public('open_version', 'V1.0.0'));
        $userCode = (string) ($options['user_code'] ?? $this->public('user_code', ''));
        $senderNo = (string) ($options['sender_no'] ?? $this->ecCompanyId);
        $ecCompanyId = (string) ($options['ec_company_id'] ?? $this->ecCompanyId);
        $innerVersion = (string) ($options['label_version'] ?? $this->public('label_version', '2'));
        $pageType = $pageType ?: (string) $this->public('page_type', 'RM');

        if ($senderNo === '') {
            return ['success' => false, 'message' => '未配置 CHINAPOST_EC_COMPANY_ID'];
        }

        $logisticsInterface = [
            'ecCompanyId' => $ecCompanyId,
            'ak' => $labelAk,
            'barCode' => $waybillNo,
            'version' => $innerVersion,
            'pageType' => $pageType,
        ];

        $jsonContent = json_encode($logisticsInterface, JSON_UNESCAPED_UNICODE);
        $encryptedLogitcsInterface = $this->encryptLogitcsInterface($jsonContent, $this->digestKey);
        if ($encryptedLogitcsInterface === null) {
            return ['success' => false, 'message' => 'logitcsInterface 加密失败，请检查 CHINAPOST_DIGEST_KEY 是否正确'];
        }

        $url = $this->path('open_api', '/amp-prod-api/f/amp/api/open');
        $params = [
            'apiCode' => (string) $apiCode,
            'senderNo' => $senderNo,
            'authorization' => $this->authorization,
            'msgType' => $msgType,
            'timeStamp' => now()->format('Y-m-d H:i:s'),
            'version' => $openVersion,
            'logitcsInterface' => $encryptedLogitcsInterface,
        ];

        if ($userCode !== '') {
            $params['userCode'] = $userCode;
        }

        $this->thirdPartyLog()->info('ChinaPost getLabel request', [
            'waybill_no' => $waybillNo,
            'url' => $url,
            'api_code' => $apiCode,
            'page_type' => $pageType,
            'label_version' => $innerVersion,
        ]);

        try {
            $response = Http::asForm()
                ->withOptions(['verify' => $this->verifySsl])
                ->timeout(30)
                ->post($url, $params);

            $responseBody = $response->body();
            $contentType = (string) $response->header('Content-Type', '');
            $data = json_decode($responseBody, true);

            if (!is_array($data)) {
                $data = [];
            }

            if ($innerVersion === '1' && $response->successful() && $responseBody !== '' && stripos($contentType, 'json') === false) {
                $this->thirdPartyLog()->info('ChinaPost getLabel binary response', [
                    'waybill_no' => $waybillNo,
                    'status' => $response->status(),
                    'content_type' => $contentType,
                    'content_length' => strlen($responseBody),
                ]);

                return [
                    'success' => true,
                    'message' => '面单获取成功',
                    'pdf_content' => $responseBody,
                    'pdf_base64' => base64_encode($responseBody),
                    'waybill_no' => $waybillNo,
                    'ret_code' => null,
                    'ret_msg' => null,
                    'ret_body' => [],
                    'raw' => null,
                ];
            }

            $retBodyRaw = $data['retBody'] ?? null;
            $retBody = $this->decodeRetBody($retBodyRaw);
            $pdfBase64 = $this->extractLabelBase64($retBodyRaw, $retBody);
            $pdfContent = $pdfBase64 !== '' ? base64_decode($pdfBase64, true) : false;

            $this->thirdPartyLog()->info('ChinaPost getLabel response', [
                'waybill_no' => $waybillNo,
                'status' => $response->status(),
                'response' => $data,
            ]);

            if (!empty($data['success']) && (($data['retCode'] ?? '') === '00000' || ($data['retCode'] ?? '') === '')) {
                if ($pdfBase64 === '' && $pdfContent === false) {
                    return [
                        'success' => false,
                        'api_success' => true,
                        'message' => '面单接口调用成功，但未返回面单内容',
                        'waybill_no' => $waybillNo,
                        'ret_code' => $data['retCode'] ?? null,
                        'ret_msg' => $data['retMsg'] ?? null,
                        'ret_body' => $retBody,
                        'raw' => $data,
                        'status' => $response->status(),
                    ];
                }

                return [
                    'success' => true,
                    'message' => '面单获取成功',
                    'pdf_content' => $pdfContent !== false ? $pdfContent : null,
                    'pdf_base64' => $pdfBase64,
                    'waybill_no' => $waybillNo,
                    'ret_code' => $data['retCode'] ?? null,
                    'ret_msg' => $data['retMsg'] ?? null,
                    'ret_body' => $retBody,
                    'raw' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => '面单获取失败: ' . ($data['retMsg'] ?? '未知错误'),
                'error_code' => $data['retCode'] ?? null,
                'ret_body' => $retBody,
                'raw' => $data,
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            $this->thirdPartyLog()->error('ChinaPost getLabel exception', [
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
        $url = $this->path('cancel_order', '/pcpErp-web/a/pcp/orderService/cancelOrder');

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
            $this->thirdPartyLog()->error('ChinaPost cancelOrder exception', [
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
        $url = $this->path('order_fee', '/pcpErp-web/a/pcp/orderFeeService/getOrderFee');

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
                'carogo_weight' => $weight,
                'cargo_weight' => $weight,
                'cargo_description' => mb_substr($item->item_title ?: 'Product', 0, 200),
                'cargo_serial' => '',
                'unit' => (string) $this->public('item_unit', '个'),
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
                'carogo_weight' => $totalWeight,
                'cargo_weight' => $totalWeight,
                'cargo_description' => 'Product',
                'cargo_serial' => '',
                'unit' => (string) $this->public('item_unit', '个'),
                'intemsize' => '',
            ];
        }

        $weight = (int) ($options['weight'] ?? $totalWeight);

        // 收件人信息 - 从订单中提取
        $receiver = [
            'name' => $order->receiver_name ?: ($order->buyer_name ?: 'Customer'),
            'company' => '',
            'post_code' => $order->receiver_zip ?: '',
            'phone' => $order->receiver_phone ?: '',
            'mobile' => $order->receiver_phone ?: '',
            'email' => $order->buyer_email ?: '',
            'id_type' => (string) ($options['receiver_id_type'] ?? ''),
            'id_no' => (string) ($options['receiver_id_no'] ?? ''),
            'nation' => $order->buyer_country_code ?: 'RU',
            'province' => $order->receiver_region ?: '',
            'city' => $order->receiver_city ?: '',
            'county' => '',
            'address' => $order->receiver_street ?: ($order->delivery_address ?: ''),
            'gis' => (string) ($options['receiver_gis'] ?? ''),
            'linker' => $order->receiver_name ?: ($order->buyer_name ?: 'Customer'),
        ];

        if (is_array($options['receiver'] ?? null)) {
            $receiver = array_merge($receiver, $options['receiver']);
        }

        $sender = array_merge([
            'id_type' => '',
            'id_no' => '',
            'gis' => '',
        ], $this->defaultSender);

        if (is_array($options['sender'] ?? null)) {
            $sender = array_merge($sender, $options['sender']);
        }

        return [
            'created_time' => now()->format('Y-m-d H:i:s'),
            'sender_no' => (string) ($options['sender_no'] ?? $this->ecCompanyId),
            'mailType' => $this->mailType,
            'wh_code' => $options['wh_code'] ?? $this->whCode,
            'logistics_order_no' => (string) $order->ae_order_id,
            'batch_no' => (string) ($options['batch_no'] ?? ''),
            'waybill_no' => (string) ($options['waybill_no'] ?? ''),
            'biz_product_no' => $bizProductNo,
            'weight' => $weight,
            'volume' => $options['volume'] ?? null,
            'length' => $options['length'] ?? null,
            'width' => $options['width'] ?? null,
            'height' => $options['height'] ?? null,
            'postage_total' => null,
            'postage_currency' => (string) ($options['postage_currency'] ?? 'USD'),
            'contents_total_weight' => $totalWeight,
            'contents_total_value' => round($totalValue, 2),
            'transfer_type' => $options['transfer_type'] ?? $this->public('transfer_type', 'HK'),
            'battery_flag' => $options['battery_flag'] ?? $this->public('battery_flag', '0'),
            'pickup_notes' => '',
            'insurance_flag' => (string) $this->public('insurance_flag', ''),
            'insurance_amount' => null,
            'undelivery_option' => (string) ($options['undelivery_option'] ?? $this->public('undelivery_option', 2)),
            'back_addr' => (string) ($options['back_addr'] ?? $this->public('back_addr', '')),
            'back_way' => (string) ($options['back_way'] ?? $this->public('back_way', '1')),
            'valuable_flag' => (string) $this->public('valuable_flag', '0'),
            'declare_source' => (string) $this->public('declare_source', '2'),
            'declare_type' => (string) $this->public('declare_type', '1'),
            'declare_curr_code' => (string) $this->public('declare_curr_code', 'USD'),
            'printcode' => (string) $this->public('printcode', '0'),
            'barcode' => (string) $order->ae_order_id,
            'forecastshut' => (string) $this->public('forecastshut', '0'),
            'mail_sign' => (string) $this->public('mail_sign', '2'),
            'mail_flag' => (string) $this->public('mail_flag', '0'),
            'tax_id' => (string) ($options['tax_id'] ?? ''),
            's_tax_id' => (string) ($options['s_tax_id'] ?? ''),
            'prepayment_of_vat' => (string) ($options['prepayment_of_vat'] ?? ''),
            'pickup_flag' => (string) ($options['pickup_flag'] ?? '0'),
            'sender' => $sender,
            'receiver' => $receiver,
            'items' => is_array($options['items'] ?? null) ? $options['items'] : $cargoItems,
        ];
    }

    protected function buildPayloadFromGivenLogisticsInterface(Order $order, string $bizProductNo, array $options = []): array
    {
        $defaultPayload = $this->buildOrderPayload($order, $bizProductNo, $options);
        $payload = array_replace_recursive($defaultPayload, $options['logistics_interface']);

        $payload['created_time'] = (string) ($payload['created_time'] ?? now()->format('Y-m-d H:i:s'));
        $payload['sender_no'] = (string) ($payload['sender_no'] ?? $this->ecCompanyId);
        $payload['mailType'] = (string) ($payload['mailType'] ?? $this->mailType);
        $payload['wh_code'] = (string) ($payload['wh_code'] ?? ($options['wh_code'] ?? $this->whCode));
        $payload['logistics_order_no'] = (string) ($payload['logistics_order_no'] ?? $order->ae_order_id);
        $payload['biz_product_no'] = (string) ($payload['biz_product_no'] ?? $bizProductNo);
        $payload['sender'] = array_merge($defaultPayload['sender'] ?? [], is_array($payload['sender'] ?? null) ? $payload['sender'] : []);
        $payload['receiver'] = array_merge($defaultPayload['receiver'] ?? [], is_array($payload['receiver'] ?? null) ? $payload['receiver'] : []);
        $payload['items'] = is_array($payload['items'] ?? null) && !empty($payload['items'])
            ? array_values($payload['items'])
            : ($defaultPayload['items'] ?? []);

        return $payload;
    }

    protected function buildBarcodeAllocationPayload(Order $order, array $options = []): array
    {
        return [
            'order' => [[
                'ecCompanyId' => (string) ($options['ec_company_id'] ?? $this->ecCompanyId),
                'eventTime' => (string) ($options['event_time'] ?? now()->format('Y-m-d H:i:s')),
                'whCode' => (string) ($options['wh_code'] ?? $this->whCode),
                'logisticsOrderId' => (string) ($options['logistics_order_id'] ?? $order->ae_order_id),
                'tradeId' => (string) ($options['trade_id'] ?? ''),
                'logisticsCompany' => (string) ($options['logistics_company'] ?? $this->public('barcode_logistics_company', 'POST')),
                'logisticsBiz' => (string) ($options['logistics_biz'] ?? $this->public('biz_product_no', '019')),
                'mailType' => (string) ($options['mail_type'] ?? $this->mailType),
                'faceType' => (string) ($options['face_type'] ?? $this->public('barcode_face_type', '1')),
                'rcountry' => (string) ($options['rcountry'] ?? ($order->buyer_country_code ?: 'US')),
            ]],
        ];
    }

    protected function buildBarcodePayloadFromGivenInterface(Order $order, array $options = []): array
    {
        $payload = $options['logistics_interface'];
        $payload['order'] = is_array($payload['order'] ?? null) ? $payload['order'] : [];

        if (empty($payload['order'])) {
            return $this->buildBarcodeAllocationPayload($order, $options);
        }

        foreach ($payload['order'] as $index => $item) {
            if (!is_array($item)) {
                $item = [];
            }

            $payload['order'][$index] = array_merge([
                'ecCompanyId' => (string) ($options['ec_company_id'] ?? $this->ecCompanyId),
                'eventTime' => (string) ($options['event_time'] ?? now()->format('Y-m-d H:i:s')),
                'whCode' => (string) ($options['wh_code'] ?? $this->whCode),
                'logisticsOrderId' => (string) ($options['logistics_order_id'] ?? $order->ae_order_id),
                'tradeId' => (string) ($options['trade_id'] ?? ''),
                'logisticsCompany' => (string) ($options['logistics_company'] ?? $this->public('barcode_logistics_company', 'POST')),
                'logisticsBiz' => (string) ($options['logistics_biz'] ?? $this->public('biz_product_no', '019')),
                'mailType' => (string) ($options['mail_type'] ?? $this->mailType),
                'faceType' => (string) ($options['face_type'] ?? $this->public('barcode_face_type', '1')),
                'rcountry' => (string) ($options['rcountry'] ?? ($order->buyer_country_code ?: 'US')),
            ], $item);
        }

        return $payload;
    }

    protected function decodeRetBody($retBody): array
    {
        if (is_array($retBody)) {
            return $retBody;
        }

        if (!is_string($retBody) || trim($retBody) === '') {
            return [];
        }

        $decoded = json_decode($retBody, true);
        return is_array($decoded) ? $decoded : [];
    }

    protected function extractWaybillNumbers(array $retBody): array
    {
        $waybillNo = (string) (
            data_get($retBody, 'waybillNo')
            ?? data_get($retBody, 'waybill_no')
            ?? data_get($retBody, 'bar_code')
            ?? data_get($retBody, 'barcode')
            ?? data_get($retBody, 'barCode')
            ?? data_get($retBody, 'mailNo')
            ?? data_get($retBody, '0.bar_code')
            ?? data_get($retBody, '0.barCode')
            ?? data_get($retBody, '0.barcode')
            ?? data_get($retBody, 'order.0.waybillNo')
            ?? data_get($retBody, 'order.0.bar_code')
            ?? data_get($retBody, 'order.0.barCode')
            ?? ''
        );

        $cbWaybillNo = (string) (data_get($retBody, 'cbWaybillNo') ?? data_get($retBody, 'order.0.cbWaybillNo') ?? '');

        return [$waybillNo, $cbWaybillNo];
    }

    protected function extractLabelBase64($retBodyRaw, array $retBody): string
    {
        $candidates = [
            data_get($retBody, 'data'),
            data_get($retBody, 'base64'),
            data_get($retBody, 'pdf_base64'),
            data_get($retBody, 'pdfBase64'),
            data_get($retBody, 'fileBase64'),
            data_get($retBody, 'content'),
            data_get($retBody, '0.data'),
            data_get($retBody, '0.base64'),
            data_get($retBody, '0.pdfBase64'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        if (is_string($retBodyRaw) && trim($retBodyRaw) !== '') {
            $raw = trim($retBodyRaw);
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return $raw;
            }
        }

        return '';
    }

    /**
     * 按中邮文档规则加密 logitcsInterface: SM4(ECB/PKCS5Padding, UTF-8) + Base64 + "|$4|" 前缀。
     */
    protected function encryptLogitcsInterface(string $jsonContent, string $key): ?string
    {
        $plainText = $jsonContent . $key;

        // 按官方说明: key 为 Base64 字符串，需先解码为16字节 SM4 密钥。
        $keyBinary = base64_decode($key, true);
        if ($keyBinary === false || strlen($keyBinary) !== 16) {
            return null;
        }

        $cipherRaw = openssl_encrypt($plainText, 'sm4-ecb', $keyBinary, OPENSSL_RAW_DATA);
        if ($cipherRaw === false) {
            return null;
        }

        return '|$4|' . base64_encode($cipherRaw);
    }
}
