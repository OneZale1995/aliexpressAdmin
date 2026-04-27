<?php

namespace Database\Seeders;

use App\Models\DictData;
use App\Models\DictType;
use Illuminate\Database\Seeder;

class OrderStatusDictSeeder extends Seeder
{
    public function run(): void
    {
        $dicts = [
            'ae_order_display_status' => [
                'name' => '速卖通-订单展示状态',
                'items' => [
                    ['Unknown', '状态未知'],
                    ['PlaceOrderSuccess', '等待付款'],
                    ['PaymentPending', '付款处理中'],
                    ['WaitExamineMoney', '等待付款确认'],
                    ['WaitGroup', '拼团中'],
                    ['WaitSendGoods', '等待发货'],
                    ['PartialSendGoods', '部分发货'],
                    ['WaitAcceptGoods', '等待收货'],
                    ['InCancel', '买家申请取消订单'],
                    ['Complete', '订单完成'],
                    ['Close', '订单取消/关闭'],
                    ['InFrozen', '挂起中'],
                    ['InIssue', '订单争议中'],
                ],
            ],
            'ae_order_status' => [
                'name' => '速卖通-订单状态',
                'items' => [
                    ['Created', '已创建'],
                    ['Cancelled', '已取消'],
                    ['Finished', '已完成'],
                    ['Closed', '已关闭'],
                ],
            ],
            'ae_payment_status' => [
                'name' => '速卖通-付款状态',
                'items' => [
                    ['Hold', '待付款'],
                    ['Paid', '已付款'],
                    ['Refunded', '已退款'],
                    ['PartiallyRefunded', '部分退款'],
                    ['PaymentPending', '付款处理中'],
                ],
            ],
            'ae_delivery_status' => [
                'name' => '速卖通-配送状态',
                'items' => [
                    ['Init', '待发货'],
                    ['Processing', '处理中'],
                    ['Shipped', '已发货'],
                    ['Delivered', '已送达'],
                    ['Returned', '已退回'],
                    ['Cancelled', '已取消'],
                ],
            ],
            'ae_antifraud_status' => [
                'name' => '速卖通-反欺诈状态',
                'items' => [
                    ['Passed', '通过'],
                    ['InReview', '审核中'],
                    ['Rejected', '未通过'],
                ],
            ],
            'ae_issue_status' => [
                'name' => '速卖通-争议状态',
                'items' => [
                    ['NoDispute', '无争议'],
                    ['InProcess', '争议处理中'],
                    ['Finished', '争议已解决'],
                ],
            ],
            'ae_logistics_type' => [
                'name' => '速卖通-配送类型',
                'items' => [
                    ['DBS', '卖家配送'],
                    ['FBS', 'AliExpress配送'],
                ],
            ],
            'ae_first_mile_type' => [
                'name' => '速卖通-首程类型',
                'items' => [
                    ['Pickup', '上门揽收'],
                    ['Dropoff', '自行交寄'],
                ],
            ],
            'ae_logistic_order_status' => [
                'name' => '速卖通-货件状态',
                'items' => [
                    ['New', '新建'],
                    ['AwaitingCreateOrder', '等待创建货件'],
                    ['OrderCreationProblems', '创建货件出错'],
                    ['AwaitingHandoverList', '等待加入交接清单'],
                    ['AddingToHandoverProblems', '加入交接清单出错'],
                    ['AwaitingConfirmation', '等待确认'],
                    ['AwaitingDispatch', '准备发货'],
                    ['OrderReceivedFromSeller', '已从卖家接收货件'],
                    ['CrossDocSorting', '交叉分拣中'],
                    ['CrossDocSent', '交叉分拣已发出'],
                    ['ProviderPostingReceive', '物流已接收'],
                    ['ProviderPostingLeftTheReception', '已离开发货点'],
                    ['ProviderPostingArrivedAtSorting', '已到达分拣中心'],
                    ['ProviderPostingSorting', '分拣中'],
                    ['ProviderPostingLeftTheSorting', '已离开分拣中心'],
                    ['ProviderPostingArrived', '等待买家取货'],
                    ['ProviderPostingDelivered', '已送达买家'],
                    ['ProviderPostingUnsuccessfulAttemptOfDelivery', '投递失败'],
                    ['ProviderPostingInReturn', '退回中'],
                    ['ProviderPostingTemporaryStorage', '临时存储中'],
                    ['ProviderPostingReturned', '已退回发件人'],
                    ['Rejected', '接收拒绝'],
                    ['Cancelled', '已取消'],
                ],
            ],
            'ae_shipment_status' => [
                'name' => '速卖通-发货单状态',
                'items' => [
                    ['NEW', '新建（发货创建中）'],
                    ['AWAITING_ADDING_TO_HANDOVER', '待添加到交接单'],
                    ['AWAITING_REPORT_AS_SHIP', '等待发货确认'],
                    ['DELIVERING', '交付中'],
                    ['DELIVERED', '已交付'],
                    ['RETURNING_TO_SENDER', '退回发件人中'],
                    ['RETURNED_TO_SENDER', '已退回发件人'],
                    ['CANCELLED', '已取消'],
                    ['WITH_ERRORS', '异常'],
                ],
            ],
            'ae_handover_list_status' => [
                'name' => '速卖通-交接清单状态',
                'items' => [
                    ['Created', '已创建'],
                    ['Transferred', '已传输'],
                    ['Accepted', '已接受'],
                    ['PartiallyAccepted', '部分接受'],
                    ['Sent', '已发送'],
                    ['Completed', '已完成'],
                ],
            ],
            'ae_handover_shipment_type' => [
                'name' => '速卖通-交接清单类型',
                'items' => [
                    ['Pickup', '上门揽收'],
                    ['Dropoff', '自行交寄'],
                ],
            ],
            'ae_undeliverable_option' => [
                'name' => '速卖通-FBS投递失败处理',
                'items' => [
                    ['Return', '退回至卖家'],
                    ['Recycling', '由物流服务商处置'],
                ],
            ],
            'ae_danger_type' => [
                'name' => '速卖通-FBS货物危险类型',
                'items' => [
                    ['General', '普通商品'],
                    ['DangerLiquids', '液体'],
                    ['ContainsBattery', '含电池'],
                    ['SeparateBattery', '单独包装电池'],
                ],
            ],
            'ae_dispute_reason' => [
                'name' => '速卖通-纠纷原因',
                'items' => [
                    ['ItemDidNotFit', '商品不合适'],
                    ['LowQuality', '质量低'],
                    ['ItemDamaged', '商品损坏'],
                    ['WrongItem', '商品错误'],
                    ['WrongDescription', '描述不符'],
                    ['Undelivered', '未送达'],
                    ['BoxDamaged', '外箱损坏'],
                    ['GlobalItemNotReceived', '未收到商品'],
                ],
            ],
            'ae_dispute_type' => [
                'name' => '速卖通-纠纷类型',
                'items' => [
                    ['Default', '默认纠纷'],
                    ['LocalTradeInsurance', '本地交易保险'],
                    ['PlatformInsurance', '平台保险'],
                    ['GlobalTradeDefault', '全球交易默认'],
                    ['LargeHeavyItems', '大件重货'],
                    ['KE', 'KE类型'],
                    ['UnclaimedGoods', '无人认领货物'],
                ],
            ],
            'ae_result_refund' => [
                'name' => '速卖通-退款方案',
                'items' => [
                    ['Full', '全额退款'],
                    ['Partial', '部分退款'],
                    ['Rejected', '驳回退款'],
                ],
            ],
            'ae_refund_type' => [
                'name' => '速卖通-退款类型',
                'items' => [
                    ['SimpleRefund', '仅退款'],
                    ['InsuranceReturn', '保险退货退款'],
                ],
            ],
            'ae_refund_budget' => [
                'name' => '速卖通-退款承担方',
                'items' => [
                    ['Seller', '卖家承担'],
                    ['Platform', '平台承担'],
                ],
            ],
            'ae_finish_reason' => [
                'name' => '速卖通-结束原因',
                'items' => [
                    ['PaymentTimeout', '付款时间已过'],
                    ['ShippingTimeout', '发货时间已过'],
                    ['BuyerNotPickUpPosting', '买家自提超时'],
                    ['CancelledByBuyer', '买家取消已付款订单'],
                    ['SecurityClose', '平台因安全问题取消'],
                    ['LogisticOrderToPostingMapFailed', '创建发货单失败'],
                    ['CancelledBySeller', '卖家取消订单'],
                    ['BuyerDoesNotWantOrder', '买家改变主意'],
                    ['BuyerWantChangeProduct', '买家想更换商品'],
                    ['BuyerChangeCoupon', '买家想更换优惠券'],
                    ['BuyerChangeMailAddress', '买家更改收货地址'],
                    ['BuyerChangeLogistic', '买家更改配送方式'],
                    ['BuyerCannotPayment', '买家未付款'],
                    ['BuyerOtherReasons', '买家其他原因'],
                    ['ProductNotEnough', '仓库缺货'],
                    ['SellerDidNotUseBuyerLogisticType', '卖家未使用买家配送方式'],
                    ['BuyerCannotContactSeller', '买家无法联系卖家'],
                    ['SellerRiseOrderAmount', '卖家提高订单价格'],
                    ['CancelGroupBuyAfterPay', '付款后团购取消'],
                    ['GroupBuyFailure', '团购失败'],
                    ['FreightCommitDayNotMatch', '卖家未按承诺时间发货'],
                    ['ConfirmedByBuyer', '买家确认收货'],
                    ['AutoConfirm', '系统自动确认收货'],
                    ['ConfirmedByLogistic', '物流商确认收货'],
                ],
            ],
            'ae_logistic_method' => [
                'name' => '速卖通-配送方式',
                'items' => [
                    ['AE_RU_MP_RUPOST_PH3_FR', 'AliExpress到俄罗斯邮局(在线包裹)'],
                    ['AE_RU_MP_COURIER_PH3_CITY', 'AliExpress城市快递'],
                    ['AE_RU_MP_COURIER_PH3_REGION', 'AliExpress区域快递'],
                    ['AE_RU_MP_PUDO_PH3', 'AliExpress到取货点'],
                    ['AE_RU_MP_OVERSIZE_PH3', 'AliExpress超大件快递'],
                    ['AE_RU_MP_WHOVERSIZE_PH3', 'AliExpress超大件快递(仓)'],
                    ['AE_RU_MP_WHCOURIER_PH3_City', 'AliExpress城市快递(仓)'],
                    ['AE_RU_MP_WHCOURIER_PH3_Prov', 'AliExpress区域快递(仓)'],
                    ['AE_RU_MP_WHCOURIER_PH3_CIS', 'AliExpress独联体快递(仓)'],
                    ['AE_RU_MP_WHPUDO_PH3', 'AliExpress到取货点(仓)'],
                    ['AE_RU_MPFF_RUPOST_FR', 'AliExpress到俄罗斯邮局(FF)'],
                    ['EMS_PROVINCE', 'EMS区域快递'],
                    ['EMS_CITY', 'EMS城市快递'],
                    ['RUPOST_FIRST_PROVINCE_RUB', '俄邮1级包裹-区域'],
                    ['RUPOST_FIRST_CITY_RUB', '俄邮1级包裹-城市'],
                    ['RUSSIAN_POST_RU_PROVINCE_RUB', '俄罗斯邮政-区域'],
                    ['RUSSIAN_POST_CITY_RUB', '俄罗斯邮政-城市'],
                    ['RUPOST_ONLINE_PROVINCE_RUB', '俄邮在线包裹-区域'],
                    ['RUPOST_ONLINE_RUB', '俄邮在线包裹-城市'],
                    ['RUPOST_COURIER_RUB', '俄邮在线快递'],
                    ['CDEK_RU_PROVINCE_RUB', 'CDEK到区域'],
                    ['CDEK_RU_CITY_RUB', 'CDEK到城市'],
                    ['CDEK_PARCEL', 'CDEK包裹(送货上门)'],
                    ['DPD_RU_PROVINCE_RUB', 'DPD到区域'],
                    ['DPD_RU_CITY_RUB', 'DPD到城市'],
                    ['DPD_CITY_DD', 'DPD经济型(门到门)'],
                    ['DPD_CITY_TD', 'DPD经济型(终端到门)'],
                    ['CSE_RU_PROVINCE_RUB', 'CSE到区域'],
                    ['CSE_RU_CITY_RUB', 'CSE到城市'],
                    ['CAINIAO_AE_SELF_RUN_RU_SERVICE', '送货到取货点'],
                ],
            ],
            'order_sync_status' => [
                'name' => '订单同步任务状态',
                'items' => [
                    ['pending', '排队中'],
                    ['running', '同步中'],
                    ['completed', '已完成'],
                    ['failed', '失败'],
                ],
            ],
            'order_backend_status' => [
                'name' => '订单后台状态',
                'items' => [
                    ['wait_review', '等待审核'],
                    ['pending_purchase', '待采购'],
                    ['purchased', '已采购'],
                    ['shipped', '已发货'],
                ],
            ],
        ];

        foreach ($dicts as $code => $meta) {
            $type = DictType::firstOrCreate(
                ['code' => $code],
                ['name' => $meta['name'], 'code' => $code, 'status' => 1]
            );

            foreach ($meta['items'] as $index => $pair) {
                [$value, $label] = $pair;
                DictData::firstOrCreate(
                    ['dict_type_id' => $type->id, 'value' => (string) $value],
                    [
                        'dict_type_id' => $type->id,
                        'label' => $label,
                        'value' => (string) $value,
                        'status' => 1,
                        'sort' => $index + 1,
                    ]
                );
            }
        }
    }
}
