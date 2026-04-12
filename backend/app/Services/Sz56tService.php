<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Sz56tService
{
    protected string $apiUrl;   // URL1: 创建订单等接口
    protected string $labelUrl; // URL2: 打印标签接口
    protected string $username;
    protected string $password;
    protected ?string $customerId = null;
    protected ?string $customerUserid = null;
    protected string $productId;     // 运输方式ID
    protected string $tradeType;

    public function __construct()
    {
        $config = config('services.sz56t', []);
        $this->apiUrl = rtrim($config['api_url'] ?? 'http://www.sz56t.com:8082', '/');
        $this->labelUrl = rtrim($config['label_url'] ?? 'http://www.sz56t.com:8089', '/');
        $this->username = $config['username'] ?? '';
        $this->password = $config['password'] ?? '';
        $this->customerId = $config['customer_id'] ?? null;
        $this->customerUserid = $config['customer_userid'] ?? null;
        $this->productId = $config['product_id'] ?? '';
        $this->tradeType = $config['trade_type'] ?? 'ZYXT';
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
    public function getProductList(): array
    {
        try {
            $response = Http::timeout(15)
                ->get($this->apiUrl . '/getProductList.htm');

            return ['success' => true, 'data' => $this->decodeResponse($response->body())];
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

        $param = $this->buildOrderParam($order, $options);
        $paramJson = json_encode($param, JSON_UNESCAPED_UNICODE);

        Log::info('Sz56t createOrder request', [
            'order_id' => $order->ae_order_id,
            'product_id' => $options['product_id'] ?? $this->productId,
        ]);

        try {
            $response = Http::timeout(30)
                ->asForm()
                ->post($this->apiUrl . '/createOrderApi.htm', [
                    'param' => $paramJson,
                ]);

            $data = $this->decodeResponse($response->body());

            Log::info('Sz56t createOrder response', [
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
            Log::error('Sz56t createOrder exception', [
                'order_id' => $order->ae_order_id,
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

        try {
            $response = Http::timeout(15)
                ->get($this->apiUrl . '/postOrderApi.htm', [
                    'customer_id' => $this->customerId,
                    'order_customerinvoicecode' => $customerInvoiceCode,
                ]);

            $data = $this->decodeResponse($response->body());
            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
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
    public function getTrackingNumber(string $documentCode): array
    {
        try {
            $response = Http::timeout(15)
                ->get($this->apiUrl . '/getOrderTrackingNumber.htm', [
                    'documentCode' => $documentCode,
                ]);

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

    /**
     * 构建下单参数
     */
    protected function buildOrderParam(Order $order, array $options = []): array
    {
        $items = $order->items ?? collect();
        $invoiceParams = [];

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
            ];
        }

        // 没有订单项时默认一个
        if (empty($invoiceParams)) {
            $invoiceParams[] = [
                'invoice_amount' => round((float) ($order->total_amount ?? 1), 2),
                'invoice_pcs' => 1,
                'invoice_title' => 'Product',
                'invoice_weight' => $options['weight'] ?? null,
                'sku' => '商品',
                'invoice_currency' => 'USD',
                'origin_country' => 'CN',
            ];
        }

        $param = [
            'customer_id' => $this->customerId,
            'customer_userid' => $this->customerUserid,
            'order_customerinvoicecode' => (string) $order->ae_order_id,
            'product_id' => $options['product_id'] ?? $this->productId,
            'trade_type' => $this->tradeType,
            'order_piece' => '1',
            'order_returnsign' => 'N',
            'weight' => $options['weight'] ?? null,
            // 收件人
            'consignee_name' => $order->receiver_name ?: ($order->buyer_name ?: ''),
            'consignee_address' => $order->receiver_street ?: ($order->delivery_address ?: ''),
            'consignee_telephone' => $order->receiver_phone ?: '',
            'consignee_mobile' => $order->receiver_phone ?: '',
            'consignee_city' => $order->receiver_city ?: '',
            'consignee_state' => $order->receiver_region ?: '',
            'consignee_postcode' => $order->receiver_zip ?: '',
            'country' => $order->buyer_country_code ?: 'RU',
            'consignee_email' => '',
            // 申报信息
            'orderInvoiceParam' => $invoiceParams,
        ];

        // 体积信息
        if (!empty($options['length']) || !empty($options['width']) || !empty($options['height'])) {
            $param['orderVolumeParam'] = [[
                'volume_length' => $options['length'] ?? '',
                'volume_width' => $options['width'] ?? '',
                'volume_height' => $options['height'] ?? '',
                'volume_weight' => $options['weight'] ?? '',
            ]];
        }

        return $param;
    }
}
