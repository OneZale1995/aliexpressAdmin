export const ORDER_TAB_LABELS = {
  allOrders: '所有订单',
  allBackendStatuses: '全部后台状态'
}

export const STATUS_TAG_TYPE = {
  WaitSendGoods: 'warning',
  Complete: 'success',
  Close: 'info',
  InIssue: 'danger',
  InFrozen: 'danger',
  WaitAcceptGoods: ''
}

export const ORDER_DICT_CODE = {
  orderDisplayStatus: 'ae_order_display_status',
  backendStatus: 'order_backend_status',
  orderStatus: 'ae_order_status',
  paymentStatus: 'ae_payment_status',
  deliveryStatus: 'ae_delivery_status',
  antifraudStatus: 'ae_antifraud_status',
  issueStatus: 'ae_issue_status',
  logisticsType: 'ae_logistics_type',
  logisticOrderStatus: 'ae_logistic_order_status',
  handoverListStatus: 'ae_handover_list_status',
  handoverShipmentType: 'ae_handover_shipment_type',
  undeliverableOption: 'ae_undeliverable_option',
  dangerType: 'ae_danger_type',
  finishReason: 'ae_finish_reason',
  syncStatus: 'order_sync_status'
}

const DEFAULT_LIST_QUERY = {
  page: 1,
  limit: 20,
  display_status: '',
  backend_status: '',
  shop_id: '',
  shop_keyword: '',
  ae_order_id: '',
  tracking_number: '',
  receiver_name: '',
  receiver_phone: '',
  buyer_name: '',
  buyer_phone: '',
  address_keyword: '',
  seller_comment: '',
  admin_remark: '',
  has_purchase_image: '',
  has_shipping_image: '',
  issue_status: '',
  date_start: '',
  date_end: '',
  purchase_date_start: '',
  purchase_date_end: '',
  shipping_date_start: '',
  shipping_date_end: ''
}

const DEFAULT_SHIP_FORM = {
  id: null,
  track_number: '',
  logistic_method: '',
  logistics_type: '',
  ship_provider: 'manual',
  provider_name: 'China Post',
  biz_product_no: '001',
  weight: 100,
  total_length: 20,
  total_width: 10,
  total_height: 5,
  total_weight: 0.5,
  undeliverable_option: 'Return',
  danger_type: 'General',
  items: [],
  current_step: 0,
  workflow_loading: false,
  logistic_order_status: '',
  logistic_order_state_status_name: '',
  platform_tracking_code: '',
  cut_off_date: '',
  handover_list_id: null,
  handover_list_status: '',
  handover_arrival_date: '',
  handover_shipment_type: '',
  handover_created_at: '',
  arrival_date: '',
  existing_handover_list_id: null,
  pickup_date: '',
  pickup_time_from: '',
  pickup_time_to: ''
}

const DEFAULT_COMMENT_TEMP = {
  id: null,
  admin_remark: '',
  backend_status: '',
  purchase_image: '',
  shipping_image: '',
  purchase_date: '',
  shipping_date: '',
  lianlian_fee: 0,
  purchase_amount: 0,
  express_fee: 0,
  logistics_fee: 0,
  logistics_template: 'online',
  eub_amazon_ratio: 0,
  eub_base_fee: 0,
  calculated_logistics_fee: 0,
  logistics_fee_override: false,
  apply_qianze_at: '',
  ship_qianze_at: ''
}

const DEFAULT_SYNC_PROGRESS = {
  status: '',
  progress: 0,
  total_shops: 0,
  processed_shops: 0,
  failed_shops: 0,
  synced_orders: 0,
  current_shop_name: '',
  details: []
}

export function createDefaultListQuery(overrides = {}) {
  return {
    ...DEFAULT_LIST_QUERY,
    ...overrides
  }
}

export function createDefaultShipForm(overrides = {}) {
  return {
    ...DEFAULT_SHIP_FORM,
    items: [],
    ...overrides
  }
}

export function createDefaultCommentTemp(overrides = {}) {
  return {
    ...DEFAULT_COMMENT_TEMP,
    ...overrides
  }
}

export function createDefaultSyncProgress(overrides = {}) {
  return {
    ...DEFAULT_SYNC_PROGRESS,
    details: [],
    ...overrides
  }
}
