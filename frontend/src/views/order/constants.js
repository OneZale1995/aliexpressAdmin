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
  shipmentStatus: 'ae_shipment_status',
  logisticsType: 'ae_logistics_type',
  logisticOrderStatus: 'ae_logistic_order_status',
  handoverListStatus: 'ae_handover_list_status',
  handoverShipmentType: 'ae_handover_shipment_type',
  undeliverableOption: 'ae_undeliverable_option',
  dangerType: 'ae_danger_type',
  finishReason: 'ae_finish_reason',
  syncStatus: 'order_sync_status'
}

export const SZ56T_CARGO_TYPE_OPTIONS = [
  { label: '包裹', value: 'P' },
  { label: '文件', value: 'D' },
  { label: 'PAK袋', value: 'B' }
]

export const SZ56T_CUSTOMS_DECLARATION_OPTIONS = [
  { label: '否', value: 'N' },
  { label: '是', value: 'Y' }
]

export const SZ56T_DUTY_TYPE_OPTIONS = [
  { label: '请选择', value: '' },
  { label: 'DDU关税到付', value: 'DDU' },
  { label: 'DDP关税预付', value: 'DDP' },
  { label: '第三方支付', value: 'Other' }
]

export const SZ56T_CURRENCY_OPTIONS = [
  { label: '人民币 RMB', value: 'RMB' },
  { label: '美金 USD', value: 'USD' },
  { label: '港币 HKD', value: 'HKD' },
  { label: '新加坡币 SGD', value: 'SGD' },
  { label: '英镑 GBP', value: 'GBP' }
]

export const SZ56T_TAX_TYPE_OPTIONS = [
  { label: '请选择', value: '' },
  { label: 'VAT', value: 'VAT' },
  { label: 'DAN', value: 'DAN' },
  { label: 'DTF', value: 'DTF' },
  { label: 'TAN', value: 'TAN' },
  { label: 'DUN', value: 'DUN' },
  { label: 'EIN', value: 'EIN' },
  { label: 'EOR', value: 'EOR' },
  { label: 'FED', value: 'FED' },
  { label: 'FTZ', value: 'FTZ' },
  { label: 'SSN', value: 'SSN' },
  { label: 'STA', value: 'STA' },
  { label: 'CNP', value: 'CNP' },
  { label: 'IOSS', value: 'IOSS' },
  { label: 'NO-IOSS', value: 'NO-IOSS' },
  { label: 'OTHER', value: 'OTHER' },
  { label: 'VOEC', value: 'VOEC' },
  { label: 'HMRC', value: 'HMRC' },
  { label: 'SDT', value: 'SDT' },
  { label: 'VATNO', value: 'VATNO' },
  { label: 'CNPJ', value: 'CNPJ' },
  { label: 'RFEC', value: 'RFEC' },
  { label: 'FEDERAL', value: 'FEDERAL' },
  { label: 'RFC', value: 'RFC' },
  { label: 'AFM', value: 'AFM' },
  { label: 'NIF', value: 'NIF' },
  { label: 'CURP', value: 'CURP' }
]

export const SZ56T_EXPORT_REASON_OPTIONS = [
  { label: '请选择', value: '' },
  { label: 'SALE', value: 'SALE' },
  { label: 'GIFT', value: 'GIFT' },
  { label: 'INTERCOMPANY DATA', value: 'INTERCOMPANY DATA' },
  { label: 'SAMPLE', value: 'SAMPLE' },
  { label: 'REPAIR', value: 'REPAIR' },
  { label: 'RETURN', value: 'RETURN' },
  { label: 'OTHER', value: 'OTHER' }
]

export const SZ56T_INVOICE_UNIT_OPTIONS = [
  { label: '件', value: 'PCS' },
  { label: '公斤', value: 'KG' },
  { label: '套', value: 'SET' },
  { label: '箱', value: 'BOX' },
  { label: '打', value: 'DZN' },
  { label: '双', value: 'PR' },
  { label: '单个', value: 'EA' },
  { label: '米', value: 'MTR' },
  { label: '平方米', value: 'M2' },
  { label: '码', value: 'YARD' }
]

export const SZ56T_BATTERY_TYPE_OPTIONS = [
  { label: '电池货请选择类型', value: '' },
  { label: 'IATA restricted as per SP A123', value: 'W' },
  { label: 'PI967', value: 'P' },
  { label: 'PI970 installed/contained in name of equipment', value: 'V' },
  { label: 'PI966', value: 'M' },
  { label: 'PI96 installed/contained in name of equipmentd/contained in name of equipment', value: 'Q' },
  { label: 'PI970 no more than 2 batteries or 4 cells', value: 'U' },
  { label: 'PI965', value: 'L' },
  { label: 'PI968', value: 'R' },
  { label: 'PI969', value: 'S' },
  { label: 'PI970 contained in name of equipment', value: 'T' },
  { label: 'FEDEX PI967 no more than 2 batteries or 4 cells', value: 'F' },
  { label: 'FEDEX PI966', value: 'I' },
  { label: 'FEDEX PI967', value: 'E' }
]

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
  shipment_status: '',
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
  ship_provider: 'chinapost',
  provider_name: 'China Post',
  api_code: '110001',
  sender_no: '',
  msg_type: '0',
  version: 'V1.0.0',
  user_code: '',
  product_type: 'E邮宝',
  biz_product_no: '019',
  product_id: '',
  weight: 100,
  chinapost_request_json: '',
  chinapost_form: {},
  chinapost_sender: {},
  chinapost_receiver: {},
  chinapost_items: [],
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
  pickup_time_to: '',
  sz56t_form: {},
  sz56t_items: []
}

const DEFAULT_CHINAPOST_FORM = {
  created_time: '',
  logistics_order_no: '',
  batch_no: '',
  waybill_no: '',
  mailType: '',
  wh_code: '',
  volume: '',
  length: 0,
  width: 0,
  height: 0,
  transfer_type: 'HK',
  battery_flag: '0',
  pickup_notes: '',
  postage_total: '',
  postage_currency: 'USD',
  contents_total_weight: 0,
  contents_total_value: 0,
  insurance_flag: '',
  insurance_amount: '',
  undelivery_option: '2',
  back_addr: '',
  back_way: '1',
  valuable_flag: '0',
  declare_source: '2',
  declare_type: '1',
  declare_curr_code: 'USD',
  printcode: '0',
  barcode: '',
  forecastshut: '0',
  mail_sign: '2',
  mail_flag: '0',
  tax_id: '',
  s_tax_id: '',
  prepayment_of_vat: '',
  pickup_flag: '0'
}

const DEFAULT_CHINAPOST_CONTACT = {
  name: '',
  company: '',
  post_code: '',
  phone: '',
  mobile: '',
  email: '',
  id_type: '',
  id_no: '',
  nation: 'CN',
  province: '',
  city: '',
  county: '',
  address: '',
  gis: '',
  linker: ''
}

const DEFAULT_CHINAPOST_ITEM = {
  cargo_no: '',
  cargo_name: '',
  cargo_name_en: '',
  cargo_type_name: '',
  cargo_type_name_en: '',
  cargo_origin_name: 'CN',
  cargo_link: '',
  cargo_quantity: 1,
  cargo_value: '',
  cost: '',
  cargo_currency: 'USD',
  cargo_weight: 0,
  cargo_description: '',
  cargo_serial: '',
  unit: '个',
  intemsize: ''
}

const DEFAULT_SZ56T_FORM = {
  buyerid: '',
  order_customerinvoicecode: '',
  order_piece: 1,
  order_returnsign: 'N',
  cargo_type: 'P',
  battery_type: '',
  order_transactionurl: '',
  product_imagepath: '',
  order_insurance: '',
  customs_declaration: 'N',
  order_cargoamount: '',
  order_handlingamount: '',
  order_customnote: '',
  production_sales_suppliers_name: '',
  production_sales_suppliers_credit_code: '',
  ecommerce_platform_name: '',
  ecommerce_platform_code: '',
  invoice_no: '',
  shipper_reference: '',
  shipper_tradetype: '',
  consignee_tradetype: '',
  duty_type: '',
  duty_account: '',
  thirdPartyCountryCode: '',
  thirdPartyPostCode: '',
  thirdpartycompany: '',
  length: null,
  width: null,
  height: null,
  consignee_name: '',
  consignee_companyname: '',
  consignee_address: '',
  consignee_telephone: '',
  consignee_mobile: '',
  consignee_city: '',
  consignee_state: '',
  consignee_postcode: '',
  country: '',
  consignee_email: '',
  consignee_suburb: '',
  consignee_passportno: '',
  consignee_taxno: '',
  consignee_taxnotype: '',
  consignee_streetno: '',
  consignee_doorno: '',
  consignee_shortaddress: '',
  consignee_taxnocountry: '',
  consignee_passportissuedate: '',
  consignee_passportissuedby: '',
  consignee_datebirth: '',
  consignee_passportserialnumber: '',
  store_code: '',
  store_name: '',
  export_reason: '',
  shipper_name: '',
  shipper_companyname: '',
  shipper_address1: '',
  shipper_address2: '',
  shipper_address3: '',
  shipper_city: '',
  shipper_state: '',
  shipper_postcode: '',
  shipper_country: '',
  shipper_telephone: '',
  shipper_suburb: '',
  shipper_email: '',
  shipper_passportno: '',
  shipper_taxnotype: '',
  shipper_taxno: '',
  shipper_taxnocountry: '',
  shipper_doorno: '',
  import_code: '',
  import_name: '',
  import_companyname: '',
  import_address: '',
  import_address2: '',
  import_address3: '',
  import_telephone: '',
  import_email: '',
  import_postcode: '',
  import_city: '',
  import_state: '',
  import_country: '',
  import_taxno: '',
  import_taxtype: '',
  import_taxcountry: '',
  orderVolumeParam: []
}

const DEFAULT_SZ56T_ITEM = {
  sku: '',
  invoice_title: '',
  invoice_amount: '',
  invoice_pcs: 1,
  invoice_weight: 0,
  sku_code: '',
  hs_code: '',
  transaction_url: '',
  invoice_currency: 'USD',
  invoiceunit_code: 'PCS',
  origin_country: 'CN',
  invoice_brand: '',
  invoice_rule: '',
  invoice_taxno: '',
  invoice_material: '',
  invoice_purpose: '',
  invoice_export_unitprice: null,
  invoice_export_currency: 'USD',
  invoice_production_sales_suppliers_name: '',
  invoice_production_sales_suppliers_credit_code: '',
  import_hs_code: '',
  invoice_imgurl: ''
}

const DEFAULT_SZ56T_VOLUME = {
  volume_length: null,
  volume_width: null,
  volume_height: null,
  volume_weight: null
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
  const rawItems = Array.isArray(overrides.items) ? overrides.items : []
  const rawSz56tItems = Array.isArray(overrides.sz56t_items) ? overrides.sz56t_items : []
  const rawChinaPostItems = Array.isArray(overrides.chinapost_items) ? overrides.chinapost_items : []
  const baseShipForm = {
    ...DEFAULT_SHIP_FORM,
    ...overrides
  }

  return {
    ...baseShipForm,
    items: rawItems,
    chinapost_form: createDefaultChinaPostForm(overrides.chinapost_form || {}),
    chinapost_sender: createDefaultChinaPostContact(overrides.chinapost_sender || {}),
    chinapost_receiver: createDefaultChinaPostContact(overrides.chinapost_receiver || {}),
    chinapost_items: rawChinaPostItems.length
      ? rawChinaPostItems.map(item => createDefaultChinaPostItem(item))
      : [createDefaultChinaPostItem()],
    sz56t_form: createDefaultSz56tForm(overrides.sz56t_form || {}),
    sz56t_items: rawSz56tItems.map(item => createDefaultSz56tItem(item))
  }
}

export function createDefaultChinaPostForm(overrides = {}) {
  return {
    ...DEFAULT_CHINAPOST_FORM,
    ...overrides
  }
}

export function createDefaultChinaPostContact(overrides = {}) {
  return {
    ...DEFAULT_CHINAPOST_CONTACT,
    ...overrides
  }
}

export function createDefaultChinaPostItem(overrides = {}) {
  return {
    ...DEFAULT_CHINAPOST_ITEM,
    ...overrides
  }
}

export function createDefaultSz56tForm(overrides = {}) {
  const rawVolumeParams = Array.isArray(overrides.orderVolumeParam) ? overrides.orderVolumeParam : []

  return {
    ...DEFAULT_SZ56T_FORM,
    ...overrides,
    orderVolumeParam: rawVolumeParams.map(item => createDefaultSz56tVolume(item))
  }
}

export function createDefaultSz56tItem(overrides = {}) {
  return {
    ...DEFAULT_SZ56T_ITEM,
    ...overrides
  }
}

export function createDefaultSz56tVolume(overrides = {}) {
  return {
    ...DEFAULT_SZ56T_VOLUME,
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
