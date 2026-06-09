<?php

namespace App\Services;

use App\Models\Order;
use App\Services\LogisticsConfigResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Sz56tService
{
    protected string $apiUrl;   // URL1: 创建订单等接口
    protected string $labelUrl; // URL2: 打印标签接口
    protected string $cancelApiUrl;
    protected string $cancelAuth;
    protected string $username;
    protected string $password;
    protected ?string $customerId = null;
    protected ?string $customerUserid = null;
    protected string $tradeType;

    public function __construct(?Order $order = null)
    {
        $config = config('services.sz56t', []);
        $resolver = new LogisticsConfigResolver();
        $resolved = $resolver->resolveForOrder($order, LogisticsConfigResolver::PROVIDER_SZ56T);
        $scopeConfig = is_array($resolved['config'] ?? null) ? $resolved['config'] : [];

        $this->init($config, $scopeConfig);
    }

    /**
     * 从指定作用域配置创建实例（用于测试连接，无需 Order）
     */
    public static function fromScopeConfig(array $scopeConfig): self
    {
        $instance = new self(null);
        $config = config('services.sz56t', []);
        $instance->init($config, $scopeConfig);
        return $instance;
    }

    private function init(array $systemConfig, array $scopeConfig): void
    {
        $this->apiUrl = rtrim($systemConfig['api_url'] ?? 'http://139.199.207.170:8082', '/');
        $this->labelUrl = rtrim($systemConfig['label_url'] ?? 'http://139.199.207.170:8089', '/');
        $this->cancelApiUrl = $systemConfig['cancel_api_url'] ?? 'http://139.199.207.170:8082/logistics/api';
        $this->cancelAuth = $systemConfig['cancel_auth'] ?? '';
        $this->username = (string) ($scopeConfig['username'] ?? ($systemConfig['username'] ?? ''));
        $this->password = (string) ($scopeConfig['password'] ?? ($systemConfig['password'] ?? ''));
        $this->customerId = $systemConfig['customer_id'] ?? null;
        $this->customerUserid = $systemConfig['customer_userid'] ?? null;
        $this->tradeType = $systemConfig['trade_type'] ?? 'ZYXT';
    }

    protected function thirdPartyLog()
    {
        return Log::channel('third_party');
    }

    /**
     * 1. 身份认证，获取 customer_id 和 customer_userid
     */
    public function authenticate(): array
    {
        if (!$this->username || !$this->password) {
            return ['success' => false, 'message' => '未配置sz56t账号密码'];
        }

        try {
            $response = Http::timeout(15)
                ->get($this->apiUrl . '/selectAuth.htm', [
                    'username' => $this->username,
                    'password' => $this->password,
                ]);

            $data = $this->decodeResponse($response->body());

            if (isset($data['ack']) && $data['ack'] === 'true') {
                $this->customerId = $data['customer_id'];
                $this->customerUserid = $data['customer_userid'];
                return [
                    'success' => true,
                    'customer_id' => $data['customer_id'],
                    'customer_userid' => $data['customer_userid'],
                ];
            }

            return ['success' => false, 'message' => '认证失败: ' . json_encode($data)];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 2. 获取渠道列表
     */
    public function getProductList(bool $forceRefresh = false): array
    {
        $cacheKey = 'sz56t:product-list:' . md5($this->apiUrl . '|' . $this->username);

        if (!$forceRefresh) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && !empty($cached)) {
                return ['success' => true, 'data' => $cached, 'cached' => true];
            }
        }

        try {
            $response = Http::timeout(15)
                ->get($this->apiUrl . '/getProductList.htm');

            $data = $this->decodeResponse($response->body());
            if (is_array($data) && !empty($data)) {
                Cache::put($cacheKey, $data, now()->addDay());
            }

            return ['success' => true, 'data' => $data, 'cached' => false];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 确保 customer_id 和 customer_userid 已获取
     */
    protected function ensureAuth(): bool
    {
        if ($this->customerId && $this->customerUserid) {
            return true;
        }
        $result = $this->authenticate();
        return $result['success'];
    }

    /**
     * 3. 创建订单（添加订单）
     */
    public function createOrder(Order $order, array $options = []): array
    {
        if (!$this->ensureAuth()) {
            return ['success' => false, 'message' => 'sz56t认证失败，请检查账号密码配置'];
        }

        $productId = trim((string) ($options['product_id'] ?? ''));
        if ($productId === '') {
            return ['success' => false, 'message' => '请选择雷翼运输方式'];
        }

        $param = $this->buildOrderParam($order, array_merge($options, [
            'product_id' => $productId,
        ]));
        $paramJson = json_encode($param, JSON_UNESCAPED_UNICODE);

        $this->thirdPartyLog()->info('Sz56t createOrder request', [
            'order_id' => $order->ae_order_id,
            'product_id' => $productId,
            'url' => $this->apiUrl . '/createOrderApi.htm',
            'param' => $param,
        ]);

        try {
            $response = Http::timeout(30)
                ->asForm()
                ->post($this->apiUrl . '/createOrderApi.htm', [
                    'param' => $paramJson,
                ]);

            $data = $this->decodeResponse($response->body());

            $this->thirdPartyLog()->info('Sz56t createOrder response', [
                'order_id' => $order->ae_order_id,
                'response' => $data,
            ]);

            if (isset($data['ack']) && $data['ack'] === 'true') {
                $trackingNumber = $data['tracking_number'] ?? '';
                $orderId = $data['order_id'] ?? '';
                $message = $data['message'] ?? '';

                // message 可能被 urlencode
                if ($message) {
                    $message = urldecode($message);
                }

                // 延迟获取单号
                $isDelay = ($data['is_delay'] ?? '') === 'Y';

                return [
                    'success' => true,
                    'message' => $isDelay ? '下单成功，单号延迟获取' : '下单成功',
                    'tracking_number' => $trackingNumber,
                    'order_id' => $orderId,          // sz56t系统订单ID，用于打印标签
                    'customer_id' => $this->customerId,
                    'customer_userid' => $this->customerUserid,
                    'is_delay' => $isDelay,
                    'is_remote' => $data['is_remote'] ?? 'N',
                    'reference_number' => $data['reference_number'] ?? '',
                    'raw' => $data,
                ];
            }

            $message = urldecode($data['message'] ?? '');
            return [
                'success' => false,
                'message' => '雷翼下单失败: ' . ($message ?: json_encode($data)),
                'raw' => $data,
            ];
        } catch (\Exception $e) {
            $this->thirdPartyLog()->error('Sz56t createOrder exception', [
                'order_id' => $order->ae_order_id,
                'product_id' => $productId,
                'param' => $param,
                'message' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 测试创建订单（不依赖真实 Order，用于物流配置验证）
     */
    public function testCreateOrder(array $testData): array
    {
        if (!$this->ensureAuth()) {
            return ['success' => false, 'message' => 'sz56t认证失败，请检查账号密码配置'];
        }

        $productId = trim((string) ($testData['product_id'] ?? ''));
        if ($productId === '') {
            return ['success' => false, 'message' => '请提供 product_id（雷翼运输方式ID）'];
        }

        $country = strtoupper(trim((string) ($testData['country'] ?? 'RU')));
        $consigneeName = trim((string) ($testData['consignee_name'] ?? 'Test User'));
        $consigneeAddress = trim((string) ($testData['consignee_address'] ?? 'Test Address, Moscow'));
        $consigneeTelephone = trim((string) ($testData['consignee_telephone'] ?? '79991234567'));
        $consigneeCity = trim((string) ($testData['consignee_city'] ?? 'Moscow'));
        $consigneeState = trim((string) ($testData['consignee_state'] ?? 'Moscow'));
        $consigneePostcode = trim((string) ($testData['consignee_postcode'] ?? '101000'));
        $orderCustomerInvoiceCode = trim((string) ($testData['order_customerinvoicecode'] ?? ('TEST-' . date('YmdHis') . '-' . rand(1000, 9999))));

        $invoiceItems = $testData['invoice_items'] ?? [];
        if (!is_array($invoiceItems) || empty($invoiceItems)) {
            $invoiceItems = [
                [
                    'invoice_title' => 'Test Product',
                    'invoice_amount' => 1.00,
                    'invoice_pcs' => 1,
                    'invoice_weight' => 0.5,
                    'invoice_currency' => 'USD',
                    'origin_country' => 'CN',
                    'invoiceunit_code' => 'PCS',
                    'invoice_export_currency' => 'USD',
                ],
            ];
        }

        $param = [
            'customer_id' => $this->customerId,
            'customer_userid' => $this->customerUserid,
            'order_customerinvoicecode' => $orderCustomerInvoiceCode,
            'product_id' => $productId,
            'trade_type' => trim((string) ($testData['trade_type'] ?? $this->tradeType)) ?: $this->tradeType,
            'order_piece' => (string) max(1, (int) ($testData['order_piece'] ?? 1)),
            'order_returnsign' => 'N',
            'cargo_type' => trim((string) ($testData['cargo_type'] ?? 'P')) ?: 'P',
            'weight' => $testData['weight'] ?? 0.5,
            'consignee_name' => $consigneeName,
            'consignee_address' => $consigneeAddress,
            'consignee_telephone' => $consigneeTelephone,
            'consignee_mobile' => $consigneeTelephone,
            'consignee_city' => $consigneeCity,
            'consignee_state' => $consigneeState,
            'consignee_postcode' => $consigneePostcode,
            'country' => $country,
            'consignee_email' => trim((string) ($testData['consignee_email'] ?? '')),
            'orderInvoiceParam' => $invoiceItems,
        ];

        if ($length = $testData['length'] ?? null) $param['length'] = $length;
        if ($width = $testData['width'] ?? null) $param['width'] = $width;
        if ($height = $testData['height'] ?? null) $param['height'] = $height;

        $paramJson = json_encode($param, JSON_UNESCAPED_UNICODE);

        $this->thirdPartyLog()->info('Sz56t testCreateOrder request', [
            'order_customerinvoicecode' => $orderCustomerInvoiceCode,
            'product_id' => $productId,
            'param' => $param,
        ]);

        try {
            $response = Http::timeout(30)
                ->asForm()
                ->post($this->apiUrl . '/createOrderApi.htm', [
                    'param' => $paramJson,
                ]);

            $data = $this->decodeResponse($response->body());

            $this->thirdPartyLog()->info('Sz56t testCreateOrder response', ['response' => $data]);

            if (isset($data['ack']) && $data['ack'] === 'true') {
                $trackingNumber = $data['tracking_number'] ?? '';
                $orderId = $data['order_id'] ?? '';
                $isDelay = ($data['is_delay'] ?? '') === 'Y';

                return [
                    'success' => true,
                    'message' => $isDelay ? '测试下单成功，单号延迟获取' : '测试下单成功',
                    'tracking_number' => $trackingNumber,
                    'order_id' => $orderId,
                    'customer_id' => $this->customerId,
                    'customer_userid' => $this->customerUserid,
                    'is_delay' => $isDelay,
                    'reference_number' => $data['reference_number'] ?? '',
                    'order_customerinvoicecode' => $orderCustomerInvoiceCode,
                    'raw' => $data,
                ];
            }

            $message = urldecode($data['message'] ?? '');
            return [
                'success' => false,
                'message' => '测试下单失败: ' . ($message ?: json_encode($data)),
                'raw' => $data,
            ];
        } catch (\Exception $e) {
            $this->thirdPartyLog()->error('Sz56t testCreateOrder exception', [
                'product_id' => $productId,
                'message' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 4. 标记发货（交寄预报）
     */
    public function markShipped(string $customerInvoiceCode): array
    {
        if (!$this->ensureAuth()) {
            return ['success' => false, 'message' => 'sz56t认证失败'];
        }

        $customerInvoiceCode = trim($customerInvoiceCode);
        if ($customerInvoiceCode === '') {
            return ['success' => false, 'message' => '缺少 order_customerinvoicecode'];
        }

        $this->thirdPartyLog()->info('Sz56t markShipped request', [
            'customer_id' => $this->customerId,
            'order_customerinvoicecode' => $customerInvoiceCode,
        ]);

        try {
            $response = Http::timeout(15)
                ->get($this->apiUrl . '/postOrderApi.htm', [
                    'customer_id' => $this->customerId,
                    'order_customerinvoicecode' => $customerInvoiceCode,
                ]);

            $data = $this->decodeResponse($response->body());

            $this->thirdPartyLog()->info('Sz56t markShipped response', [
                'order_customerinvoicecode' => $customerInvoiceCode,
                'status' => $response->status(),
                'response' => $data,
            ]);

            if (!is_array($data)) {
                return [
                    'success' => false,
                    'message' => '雷翼交寄预报返回异常响应',
                    'raw' => $data,
                ];
            }

            $ack = filter_var($data['ack'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $status = (string) ($data['status'] ?? '');
            $message = urldecode((string) ($data['message'] ?? $data['msg'] ?? ''));

            if ($ack === true || $status === '200') {
                return [
                    'success' => true,
                    'message' => $message ?: '交寄预报成功',
                    'data' => $data,
                ];
            }

            return [
                'success' => false,
                'message' => $message ?: '雷翼交寄预报失败',
                'raw' => $data,
            ];
        } catch (\Exception $e) {
            $this->thirdPartyLog()->error('Sz56t markShipped exception', [
                'order_customerinvoicecode' => $customerInvoiceCode,
                'message' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 5/6. 获取标签打印URL
     */
    public function getLabelUrl(string $orderId, string $printType = 'lab10_10'): string
    {
        return $this->labelUrl . '/order/FastRpt/PDF_NEW.aspx?PrintType=' . urlencode($printType) . '&order_id=' . urlencode($orderId);
    }

    /**
     * 获取雷翼面单内容；失败时直接返回错误信息，避免前端跳转到第三方错误页。
     */
    public function getLabel(string $orderId, string $printType = 'lab10_10'): array
    {
        $labelUrl = $this->getLabelUrl($orderId, $printType);

        $this->thirdPartyLog()->info('Sz56t getLabel fetch request', [
            'order_id' => $orderId,
            'print_type' => $printType,
            'label_url' => $labelUrl,
        ]);

        try {
            $response = Http::timeout(30)->get($labelUrl);
            $status = $response->status();
            $contentType = strtolower((string) $response->header('Content-Type', ''));
            $body = $response->body();

            $this->thirdPartyLog()->info('Sz56t getLabel fetch response', [
                'order_id' => $orderId,
                'status' => $status,
                'content_type' => $contentType,
                'content_length' => strlen($body),
            ]);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => '雷翼面单接口请求失败: HTTP ' . $status,
                    'label_url' => $labelUrl,
                ];
            }

            if ($body === '') {
                return [
                    'success' => false,
                    'message' => '雷翼面单接口未返回内容',
                    'label_url' => $labelUrl,
                ];
            }

            if (str_contains($contentType, 'pdf') || strncmp($body, '%PDF-', 5) === 0) {
                return [
                    'success' => true,
                    'label_url' => $labelUrl,
                    'pdf_content' => $body,
                    'pdf_base64' => base64_encode($body),
                ];
            }

            $message = $this->extractLabelErrorMessage($body);

            $this->thirdPartyLog()->warning('Sz56t getLabel returned non-pdf content', [
                'order_id' => $orderId,
                'status' => $status,
                'content_type' => $contentType,
                'message' => $message,
            ]);

            return [
                'success' => false,
                'message' => $message !== '' ? $message : '雷翼面单暂不可用',
                'label_url' => $labelUrl,
            ];
        } catch (\Exception $e) {
            $this->thirdPartyLog()->error('Sz56t getLabel fetch exception', [
                'order_id' => $orderId,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'label_url' => $labelUrl,
            ];
        }
    }

    /**
     * 8. 轨迹查询
     */
    public function getTrack(string $trackingNumber): array
    {
        try {
            $response = Http::timeout(15)
                ->get($this->apiUrl . '/selectTrack.htm', [
                    'documentCode' => $trackingNumber,
                ]);

            $data = $this->decodeResponse($response->body());
            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 9. 获取跟踪号（延迟获取时使用）
     */
    public function getTrackingNumber(string $documentCode = '', ?string $orderId = null): array
    {
        try {
            $query = [];
            if ($orderId) {
                $query['order_id'] = $orderId;
            } elseif ($documentCode !== '') {
                $query['documentCode'] = $documentCode;
            } else {
                return ['success' => false, 'message' => '缺少 documentCode 或 order_id'];
            }

            $response = Http::timeout(15)
                ->get($this->apiUrl . '/getOrderTrackingNumber.htm', $query);

            $data = $this->decodeResponse($response->body());

            if (($data['status'] ?? '') === '200') {
                return [
                    'success' => true,
                    'tracking_number' => $data['order_serveinvoicecode'] ?? '',
                    'order_id' => $data['order_id'] ?? '',
                    'raw' => $data,
                ];
            }

            return ['success' => false, 'message' => $data['msg'] ?? '获取跟踪号失败', 'raw' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 10. 取消订单
     */
    public function cancelOrder(string $orderId, ?string $customerId = null, string $reason = 'manual cancel'): array
    {
        if (!$this->ensureAuth()) {
            return ['success' => false, 'message' => 'sz56t认证失败，请检查账号密码配置'];
        }

        $customerId = trim((string) ($customerId ?: $this->customerId));
        if ($customerId === '') {
            return ['success' => false, 'message' => '缺少 customer_id，无法取消雷翼订单'];
        }

        if ($this->cancelAuth === '') {
            return ['success' => false, 'message' => '未配置 SZ56T_CANCEL_AUTH'];
        }

        $reqTime = now()->format('Y-m-d H:i:s');
        $method = 'order.cancel';
        $content = [
            'order_id' => (string) $orderId,
            'customer_id' => $customerId,
            'reason' => $reason,
            'sign' => strtoupper(md5((string) $orderId . $customerId . $reqTime . $method)),
        ];

        $payload = [
            'method' => $method,
            'req_time' => $reqTime,
            'content' => $content,
        ];

        $this->thirdPartyLog()->info('Sz56t cancelOrder request', [
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'payload' => $payload,
        ]);

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'auth' => $this->cancelAuth,
                ])
                ->post($this->cancelApiUrl, $payload);

            $data = $response->json();

            $this->thirdPartyLog()->info('Sz56t cancelOrder response', [
                'order_id' => $orderId,
                'response' => $data,
                'status' => $response->status(),
            ]);

            if (($data['ack'] ?? false) === true || ($data['ack'] ?? '') === 'true') {
                return [
                    'success' => true,
                    'message' => $data['message'] ?? '取消成功',
                    'customer_id' => $customerId,
                    'msg_code' => $data['msg_code'] ?? '200',
                    'raw' => $data,
                ];
            }

            $message = trim((string) ($data['message'] ?? ''));
            if ($message !== '' && str_contains($message, '订单已取消')) {
                return [
                    'success' => true,
                    'message' => '雷翼订单已取消，已同步本地状态',
                    'customer_id' => $customerId,
                    'msg_code' => $data['msg_code'] ?? '420',
                    'raw' => $data,
                    'already_cancelled' => true,
                ];
            }

            return [
                'success' => false,
                'message' => '雷翼取消订单失败: ' . ($message !== '' ? $message : ($data['msg_code'] ?? '未知错误')),
                'raw' => $data,
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            $this->thirdPartyLog()->error('Sz56t cancelOrder exception', [
                'order_id' => $orderId,
                'message' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 获取所有可用标签类型
     */
    public function getLabelTypes(): array
    {
        try {
            $response = Http::timeout(15)
                ->get($this->apiUrl . '/selectLabelType.htm');

            return ['success' => true, 'data' => $this->decodeResponse($response->body())];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * sz56t 部分接口返回单引号 JSON，需要做兼容解析。
     */
    protected function decodeResponse(string $body)
    {
        $body = trim($body);
        if ($body === '') {
            return null;
        }

        // 兼容接口返回 GBK/GB2312 等编码，避免 json_decode 因编码失败。
        if (!mb_check_encoding($body, 'UTF-8')) {
            $body = mb_convert_encoding($body, 'UTF-8', 'UTF-8,GBK,GB2312,BIG5');
        }

        $decoded = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        $normalized = str_replace("'", '"', $body);
        $decoded = json_decode($normalized, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return null;
    }

    protected function extractLabelErrorMessage(string $body): string
    {
        $text = $this->normalizeResponseText($body);

        if ($text === '') {
            return '';
        }

        if (str_contains($text, '返回信息为Null')) {
            return '获取面单为空';
        }

        $text = preg_replace('/具体信息[:：].*$/u', '', $text) ?? $text;
        $text = preg_replace('/Server stack trace:.*$/ui', '', $text) ?? $text;
        $text = trim($text);

        return mb_substr($text, 0, 240);
    }

    protected function normalizeResponseText(string $body): string
    {
        $text = trim($body);
        if ($text === '') {
            return '';
        }

        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8,GBK,GB2312,BIG5');
        }

        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * 构建下单参数
     */
    protected function buildOrderParam(Order $order, array $options = []): array
    {
        $form = is_array($options['form'] ?? null) ? $options['form'] : [];
        $customInvoiceItems = is_array($options['invoice_items'] ?? null) ? $options['invoice_items'] : [];
        $items = $order->items ?? collect();
        $invoiceParams = [];

        $normalizeScalar = static function ($value) {
            if (is_array($value)) {
                return null;
            }

            if (is_string($value)) {
                return trim($value);
            }

            return $value;
        };

        $applyOptionalFields = static function (array &$target, array $source, array $fields) use ($normalizeScalar): void {
            foreach ($fields as $field) {
                if (!array_key_exists($field, $source)) {
                    continue;
                }

                $value = $normalizeScalar($source[$field]);
                if ($value === null && is_array($source[$field])) {
                    continue;
                }

                $target[$field] = $value;
            }
        };

        $invoiceOptionalFields = [
            'hs_code',
            'transaction_url',
            'invoiceunit_code',
            'invoice_imgurl',
            'invoice_brand',
            'invoice_rule',
            'invoice_taxno',
            'invoice_material',
            'invoice_purpose',
            'invoice_export_unitprice',
            'invoice_export_currency',
            'invoice_production_sales_suppliers_name',
            'invoice_production_sales_suppliers_credit_code',
            'import_hs_code',
        ];

        if (!empty($customInvoiceItems)) {
            foreach ($customInvoiceItems as $item) {
                $itemTitle = trim((string) ($item['invoice_title'] ?? 'Product'));
                $skuCode = trim((string) ($item['sku_code'] ?? ''));
                $skuValue = trim((string) ($item['sku'] ?? ''));

                $invoiceParam = [
                    'invoice_amount' => round((float) ($item['invoice_amount'] ?? 0), 2),
                    'invoice_pcs' => max(1, (int) ($item['invoice_pcs'] ?? 1)),
                    'invoice_title' => mb_substr($itemTitle ?: 'Product', 0, 50),
                    'invoice_weight' => isset($item['invoice_weight']) && $item['invoice_weight'] !== '' ? (float) $item['invoice_weight'] : null,
                    'sku' => mb_substr($skuValue ?: ($itemTitle ?: '商品'), 0, 50),
                    'sku_code' => $skuCode,
                    'invoice_currency' => trim((string) ($item['invoice_currency'] ?? 'USD')) ?: 'USD',
                    'origin_country' => trim((string) ($item['origin_country'] ?? 'CN')) ?: 'CN',
                ];

                $applyOptionalFields($invoiceParam, $item, $invoiceOptionalFields);

                if (empty($invoiceParam['invoiceunit_code'])) {
                    $invoiceParam['invoiceunit_code'] = 'PCS';
                }

                if (empty($invoiceParam['invoice_export_currency'])) {
                    $invoiceParam['invoice_export_currency'] = $invoiceParam['invoice_currency'];
                }

                $invoiceParams[] = $invoiceParam;
            }
        } else {
            foreach ($items as $item) {
                $qty = max(1, (int) ($item->quantity ?? 1));
                $price = (float) ($item->item_price ?? 0);
                $weight = (int) ($options['item_weight'] ?? 0);
                $itemTitle = (string) ($item->name ?? 'Product');
                $skuCode = (string) ($item->sku_code ?? ($item->ae_sku_id ?? ''));

                $invoiceParams[] = [
                    'invoice_amount' => round($price * $qty, 2),
                    'invoice_pcs' => $qty,
                    'invoice_title' => mb_substr($itemTitle, 0, 50),
                    'invoice_weight' => $weight ?: null,
                    'sku' => mb_substr($itemTitle ?: '商品', 0, 50),
                    'sku_code' => $skuCode,
                    'invoice_currency' => 'USD',
                    'origin_country' => 'CN',
                    'invoiceunit_code' => 'PCS',
                    'invoice_export_currency' => 'USD',
                ];
            }
        }

        if (empty($invoiceParams)) {
            $invoiceParams[] = [
                'invoice_amount' => round((float) ($order->total_amount ?? 1), 2),
                'invoice_pcs' => 1,
                'invoice_title' => 'Product',
                'invoice_weight' => $options['weight'] ?? null,
                'sku' => '商品',
                'invoice_currency' => 'USD',
                'origin_country' => 'CN',
                'invoiceunit_code' => 'PCS',
                'invoice_export_currency' => 'USD',
            ];
        }

        $consigneeName = trim((string) ($form['consignee_name'] ?? '')) ?: (string) ($order->receiver_name ?: ($order->buyer_name ?: ''));
        $consigneeAddress = trim((string) ($form['consignee_address'] ?? '')) ?: (string) ($order->receiver_street ?: ($order->delivery_address ?: ''));
        $consigneeTelephone = trim((string) ($form['consignee_telephone'] ?? '')) ?: (string) ($order->receiver_phone ?: '');
        $consigneeMobile = trim((string) ($form['consignee_mobile'] ?? '')) ?: (string) ($order->receiver_phone ?: '');
        $consigneeCity = trim((string) ($form['consignee_city'] ?? '')) ?: (string) ($order->receiver_city ?: '');
        $consigneeState = trim((string) ($form['consignee_state'] ?? '')) ?: (string) ($order->receiver_region ?: '');
        $consigneePostcode = trim((string) ($form['consignee_postcode'] ?? '')) ?: (string) ($order->receiver_zip ?: '');
        $country = trim((string) ($form['country'] ?? '')) ?: (string) ($order->buyer_country_code ?: 'RU');
        $consigneeEmail = trim((string) ($form['consignee_email'] ?? ''));
        $returnSign = trim((string) ($form['order_returnsign'] ?? 'N')) ?: 'N';
        $orderCustomerInvoiceCode = trim((string) ($form['order_customerinvoicecode'] ?? '')) ?: (string) $order->ae_order_id;
        $length = $form['length'] ?? ($options['length'] ?? null);
        $width = $form['width'] ?? ($options['width'] ?? null);
        $height = $form['height'] ?? ($options['height'] ?? null);

        $param = [
            'customer_id' => $this->customerId,
            'customer_userid' => $this->customerUserid,
            'order_customerinvoicecode' => $orderCustomerInvoiceCode,
            'product_id' => $options['product_id'] ?? '',
            'trade_type' => trim((string) ($form['trade_type'] ?? $this->tradeType)) ?: $this->tradeType,
            'order_piece' => (string) max(1, (int) ($form['order_piece'] ?? 1)),
            'order_returnsign' => strtoupper($returnSign) === 'Y' ? 'Y' : 'N',
            'cargo_type' => trim((string) ($form['cargo_type'] ?? 'P')) ?: 'P',
            'weight' => $options['weight'] ?? null,
            // 收件人
            'consignee_name' => $consigneeName,
            'consignee_address' => $consigneeAddress,
            'consignee_telephone' => $consigneeTelephone,
            'consignee_mobile' => $consigneeMobile,
            'consignee_city' => $consigneeCity,
            'consignee_state' => $consigneeState,
            'consignee_postcode' => $consigneePostcode,
            'country' => strtoupper($country),
            'consignee_email' => $consigneeEmail,
            // 申报信息
            'orderInvoiceParam' => $invoiceParams,
        ];

        $optionalTopLevelFields = [
            'buyerid',
            'battery_type',
            'order_transactionurl',
            'product_imagepath',
            'consignee_companyname',
            'consignee_suburb',
            'consignee_passportno',
            'consignee_taxno',
            'consignee_taxnotype',
            'consignee_streetno',
            'consignee_doorno',
            'consignee_shortaddress',
            'consignee_taxnocountry',
            'consignee_passportissuedate',
            'consignee_passportissuedby',
            'consignee_datebirth',
            'consignee_passportserialnumber',
            'order_insurance',
            'customs_declaration',
            'order_cargoamount',
            'order_handlingamount',
            'order_customnote',
            'production_sales_suppliers_name',
            'production_sales_suppliers_credit_code',
            'ecommerce_platform_name',
            'ecommerce_platform_code',
            'invoice_no',
            'shipper_reference',
            'shipper_tradetype',
            'consignee_tradetype',
            'duty_type',
            'duty_account',
            'thirdPartyCountryCode',
            'thirdPartyPostCode',
            'thirdpartycompany',
            'store_code',
            'store_name',
            'export_reason',
            'shipper_name',
            'shipper_companyname',
            'shipper_address1',
            'shipper_address2',
            'shipper_address3',
            'shipper_city',
            'shipper_state',
            'shipper_postcode',
            'shipper_country',
            'shipper_telephone',
            'shipper_suburb',
            'shipper_email',
            'shipper_passportno',
            'shipper_taxnotype',
            'shipper_taxno',
            'shipper_taxnocountry',
            'shipper_doorno',
            'import_code',
            'import_name',
            'import_companyname',
            'import_address',
            'import_address2',
            'import_address3',
            'import_telephone',
            'import_email',
            'import_postcode',
            'import_city',
            'import_state',
            'import_country',
            'import_taxno',
            'import_taxtype',
            'import_taxcountry',
        ];

        $applyOptionalFields($param, $form, $optionalTopLevelFields);

        foreach (['thirdPartyCountryCode', 'consignee_taxnocountry', 'shipper_country', 'shipper_taxnocountry', 'import_country', 'import_taxcountry'] as $field) {
            if (!empty($param[$field]) && is_string($param[$field])) {
                $param[$field] = strtoupper($param[$field]);
            }
        }

        $volumeRows = [];
        if (is_array($form['orderVolumeParam'] ?? null)) {
            foreach ($form['orderVolumeParam'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $volumeLength = $item['volume_length'] ?? null;
                $volumeWidth = $item['volume_width'] ?? null;
                $volumeHeight = $item['volume_height'] ?? null;
                $volumeWeight = $item['volume_weight'] ?? null;

                if ($volumeLength === null && $volumeWidth === null && $volumeHeight === null && $volumeWeight === null) {
                    continue;
                }

                $volumeRows[] = [
                    'volume_length' => $volumeLength ?? '',
                    'volume_width' => $volumeWidth ?? '',
                    'volume_height' => $volumeHeight ?? '',
                    'volume_weight' => $volumeWeight ?? '',
                ];
            }
        }

        // 体积信息
        if (!empty($volumeRows)) {
            $param['orderVolumeParam'] = $volumeRows;
        } elseif (!empty($length) || !empty($width) || !empty($height)) {
            $param['orderVolumeParam'] = [[
                'volume_length' => $length ?? '',
                'volume_width' => $width ?? '',
                'volume_height' => $height ?? '',
                'volume_weight' => $options['weight'] ?? '',
            ]];
        }

        return $param;
    }
}
