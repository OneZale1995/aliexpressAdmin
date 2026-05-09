import {
  ORDER_DICT_CODE,
  ORDER_TAB_LABELS,
  STATUS_TAG_TYPE,
  createDefaultSz56tForm,
  createDefaultSz56tItem,
  createDefaultSz56tVolume,
  createDefaultChinaPostContact,
  createDefaultChinaPostItem
} from './constants'

export function translateByCode(value, code, dictLabelMap = {}) {
  if (value === undefined || value === null || value === '') return '-'
  const dict = dictLabelMap[code] || {}
  return dict[String(value)] || String(value)
}

export function getStatusLabel(status, dictLabelMap) {
  return translateByCode(status, ORDER_DICT_CODE.orderDisplayStatus, dictLabelMap)
}

export function getOrderStatusLabel(status, dictLabelMap) {
  return translateByCode(status, ORDER_DICT_CODE.orderStatus, dictLabelMap)
}

export function getPaymentStatusLabel(status, dictLabelMap) {
  return translateByCode(status, ORDER_DICT_CODE.paymentStatus, dictLabelMap)
}

export function getDeliveryStatusLabel(status, dictLabelMap) {
  return translateByCode(status, ORDER_DICT_CODE.deliveryStatus, dictLabelMap)
}

export function getOrderDeliveryStatusLabel(order, dictLabelMap) {
  if (!order || typeof order !== 'object') return '-'

  return getDeliveryStatusLabel(order.delivery_status || order.deliveryStatus || '', dictLabelMap)
}

export function getBackendStatusLabel(status, dictLabelMap) {
  return translateByCode(status, ORDER_DICT_CODE.backendStatus, dictLabelMap)
}

export function getAntifraudStatusLabel(status, dictLabelMap) {
  return translateByCode(status, ORDER_DICT_CODE.antifraudStatus, dictLabelMap)
}

export function getIssueStatusLabel(status, dictLabelMap) {
  return translateByCode(status, ORDER_DICT_CODE.issueStatus, dictLabelMap)
}

export function getFinishReasonLabel(reason, dictLabelMap) {
  return translateByCode(reason, ORDER_DICT_CODE.finishReason, dictLabelMap)
}

export function getLogisticsTypeLabel(type, dictLabelMap) {
  return translateByCode(type, ORDER_DICT_CODE.logisticsType, dictLabelMap)
}

export function getShipmentStatusLabel(status, dictLabelMap) {
  return translateByCode(status, ORDER_DICT_CODE.shipmentStatus, dictLabelMap)
}

export function getLogisticOrderStatusLabel(status, dictLabelMap) {
  if (status === undefined || status === null || status === '') return '-'

  const value = String(status)
  const shipmentDict = dictLabelMap[ORDER_DICT_CODE.shipmentStatus] || {}
  if (shipmentDict[value]) {
    return shipmentDict[value]
  }

  const logisticOrderDict = dictLabelMap[ORDER_DICT_CODE.logisticOrderStatus] || {}
  if (logisticOrderDict[value]) {
    return logisticOrderDict[value]
  }

  return value
}

export function getHandoverListStatusLabel(status, dictLabelMap) {
  return translateByCode(status, ORDER_DICT_CODE.handoverListStatus, dictLabelMap)
}

export function getHandoverShipmentTypeLabel(type, dictLabelMap) {
  return translateByCode(type, ORDER_DICT_CODE.handoverShipmentType, dictLabelMap)
}

export function getSyncStatusLabel(status, dictLabelMap) {
  return translateByCode(status, ORDER_DICT_CODE.syncStatus, dictLabelMap)
}

export function getStatusTagType(status) {
  return STATUS_TAG_TYPE[status] || ''
}

export function getLogisticsTemplateLabel(template) {
  if (!template) return ''
  if (template === 'leiyi' || template === 'offline_leiyi') return '雷翼'
  if (template === 'chinapost' || template === 'offline_epacket') return '中国邮政'
  if (template === 'fbs' || template === 'online') return 'FBS'
  return template
}

export function buildDictLabelMap(dictItems = []) {
  return dictItems.reduce((labelMap, item) => {
    labelMap[String(item.value)] = item.label
    return labelMap
  }, {})
}

export function buildDictOptions(dictItems = []) {
  return dictItems.map(item => ({
    value: String(item.value),
    label: item.label
  }))
}

export function getDictOptionsByCode(code, dictLabelMap = {}) {
  return Object.entries(dictLabelMap[code] || {}).map(([value, label]) => ({ value, label }))
}

function buildFilterTabs(options = [], allLabel) {
  return [
    ...options.map(item => ({ key: String(item.value), label: item.label, countKey: String(item.value) })),
    { key: '', label: allLabel, countKey: 'all' }
  ]
}

const DISPLAY_STATUS_ORDER = [
  'WaitSendGoods',
  'WaitAcceptGoods',
  'InCancel',
  'Complete',
  'Close',
  'InIssue'
]

export function buildStatusTabs(options = []) {
  const sorted = [...options].sort((a, b) => {
    const ai = DISPLAY_STATUS_ORDER.indexOf(a.value)
    const bi = DISPLAY_STATUS_ORDER.indexOf(b.value)
    const aIdx = ai === -1 ? DISPLAY_STATUS_ORDER.length : ai
    const bIdx = bi === -1 ? DISPLAY_STATUS_ORDER.length : bi
    return aIdx - bIdx
  }).filter(item => DISPLAY_STATUS_ORDER.includes(item.value))
  return [
    ...sorted.map(item => ({ key: String(item.value), label: item.label, countKey: String(item.value) })),
    { key: '', label: ORDER_TAB_LABELS.allOrders, countKey: 'all' }
  ]
}

export function buildBackendStatusTabs(options = []) {
  return buildFilterTabs(options, ORDER_TAB_LABELS.allBackendStatuses)
}

export function buildListQuery(baseQuery, dateRange = [], purchaseDateRange = [], shippingDateRange = []) {
  const query = { ...baseQuery }
  if (Array.isArray(dateRange) && dateRange.length === 2) {
    query.date_start = dateRange[0]
    query.date_end = dateRange[1]
  }
  if (Array.isArray(purchaseDateRange) && purchaseDateRange.length === 2) {
    query.purchase_date_start = purchaseDateRange[0]
    query.purchase_date_end = purchaseDateRange[1]
  }
  if (Array.isArray(shippingDateRange) && shippingDateRange.length === 2) {
    query.shipping_date_start = shippingDateRange[0]
    query.shipping_date_end = shippingDateRange[1]
  }
  return query
}

export function buildSyncParams(syncForm, syncDateRange = []) {
  const params = {
    shop_id: syncForm.shop_id || undefined
  }
  if (Array.isArray(syncDateRange) && syncDateRange.length === 2) {
    params.date_start = syncDateRange[0]
    params.date_end = syncDateRange[1]
  }
  return params
}

export function formatDate(value) {
  if (!value) return '-'
  return String(value).replace('T', ' ').substring(0, 16)
}

export function formatAddress(order) {
  const parts = [order.receiver_country, order.receiver_region, order.receiver_city, order.receiver_street, order.receiver_zip]
  return parts.filter(Boolean).join(', ') || order.delivery_address || '-'
}

export function getItemLink(item) {
  if (!item || typeof item !== 'object') return ''

  const candidates = [
    item.item_url,
    item.product_url,
    item.detail_url,
    item.ae_item_url,
    item.url,
    item.link
  ]

  if (item.properties && typeof item.properties === 'object') {
    candidates.push(
      item.properties.item_url,
      item.properties.product_url,
      item.properties.detail_url,
      item.properties.url,
      item.properties.link
    )
  }

  const raw = candidates.find(value => typeof value === 'string' && value.trim())
  if (!raw) {
    const itemIdRaw = [
      item.ae_item_id,
      item.item_id,
      item.product_id,
      item.properties && item.properties.ae_item_id,
      item.properties && item.properties.item_id,
      item.properties && item.properties.product_id
    ].find(value => value !== undefined && value !== null && String(value).trim() !== '')

    if (!itemIdRaw) return ''

    const itemId = String(itemIdRaw).trim()
    const itemPath = itemId.includes('_') ? itemId : `1_${itemId}`
    const skuIdRaw = [
      item.ae_sku_id,
      item.sku_id,
      item.properties && item.properties.ae_sku_id,
      item.properties && item.properties.sku_id
    ].find(value => value !== undefined && value !== null && String(value).trim() !== '')

    let builtUrl = `https://aliexpress.ru/item/${itemPath}.html`
    if (skuIdRaw) {
      builtUrl += `?sku_id=${encodeURIComponent(String(skuIdRaw).trim())}`
    }
    return builtUrl
  }

  const value = raw.trim()
  if (/^https?:\/\//i.test(value)) return value
  if (/^\/\//.test(value)) return `https:${value}`
  return `https://${value}`
}

export function getCategoryFromSku(item) {
  if (item.properties && typeof item.properties === 'object') {
    return Object.values(item.properties).slice(0, 2).join(' / ')
  }
  return item.name ? item.name.substring(0, 30) : '-'
}

export function calcFee(order) {
  return (parseFloat(order.platform_fee || 0) + parseFloat(order.affiliate_fee || 0)).toFixed(2)
}

export function calcTotalBack(order) {
  return parseFloat(order.estimate_revenue || 0).toFixed(2)
}

export function calcProfit(order) {
  const total = parseFloat(order.total_amount || 0)
  const fee = parseFloat(order.platform_fee || 0) + parseFloat(order.affiliate_fee || 0)
  const lianlian = parseFloat(order.lianlian_fee || 0)
  const purchase = parseFloat(order.purchase_amount || 0)
  const logistics = parseFloat(order.logistics_fee || 0)
  return +(total - fee - lianlian - purchase - logistics).toFixed(2)
}

export function calcProfitRate(order) {
  const profit = calcProfit(order)
  const base = parseFloat(order.total_amount || 0)
  if (!base) return '0.00'
  return ((profit / base) * 100).toFixed(2)
}

export function buildShipItemsFromOrder(order) {
  return (order.items || []).map(item => ({
    quantity: Number(item.quantity || 1),
    sku_id: item.ae_sku_id || item.sku_id || '',
    product_source_id: item.product_source_id || (item.properties && item.properties.product_source_id) || '',
    title: item.name || '',
    img_url: item.img_url || '',
    sku_code: item.sku_code || ''
  }))
}

function getSz56tPayload(order) {
  const logistics = order && order.current_logistics
  const payload = logistics && logistics.payload
  return payload && payload.sz56t ? payload.sz56t : {}
}

export function getSz56tProductIdFromOrder(order) {
  const payload = getSz56tPayload(order)
  return payload.product_id || ''
}

export function getSz56tWeightFromOrder(order, fallback = 100) {
  const payload = getSz56tPayload(order)
  const form = payload.form || {}
  const savedWeight = Number(form.weight || 0)
  if (savedWeight > 0) {
    return savedWeight
  }

  return Number(fallback || 100)
}

function normalizeSz56tVolumeParams(savedForm = {}, fallbackWeight = 100) {
  if (Array.isArray(savedForm.orderVolumeParam) && savedForm.orderVolumeParam.length) {
    return savedForm.orderVolumeParam.map(item => createDefaultSz56tVolume(item))
  }

  const hasLegacyVolume = savedForm.length || savedForm.width || savedForm.height
  if (!hasLegacyVolume) {
    return []
  }

  return [createDefaultSz56tVolume({
    volume_length: savedForm.length || null,
    volume_width: savedForm.width || null,
    volume_height: savedForm.height || null,
    volume_weight: Number(savedForm.weight || fallbackWeight || 0) || null
  })]
}

export function buildSz56tFormFromOrder(order, fallbackWeight = 100) {
  const payload = getSz56tPayload(order)
  const savedForm = payload.form || {}
  const orderWeight = getSz56tWeightFromOrder(order, fallbackWeight)
  const transactionUrl = order.trade_order_url || order.order_transactionurl || ''

  return createDefaultSz56tForm({
    order_customerinvoicecode: order.ae_order_id || '',
    consignee_name: order.receiver_name || order.buyer_name || '',
    consignee_companyname: order.receiver_company || '',
    consignee_address: order.receiver_street || order.delivery_address || '',
    consignee_telephone: order.receiver_phone || order.buyer_phone || '',
    consignee_mobile: order.receiver_phone || order.buyer_phone || '',
    consignee_city: order.receiver_city || '',
    consignee_state: order.receiver_region || '',
    consignee_postcode: order.receiver_zip || '',
    consignee_email: order.receiver_email || order.buyer_email || '',
    country: order.buyer_country_code || order.receiver_country || '',
    order_transactionurl: transactionUrl,
    product_imagepath: order.main_image || order.product_imagepath || '',
    store_name: order.shop_name || '',
    store_code: order.shop_code || '',
    weight: orderWeight,
    ...savedForm,
    orderVolumeParam: normalizeSz56tVolumeParams(savedForm, orderWeight)
  })
}

export function buildSz56tItemsFromOrder(order, fallbackWeight = 100) {
  const payload = getSz56tPayload(order)
  const savedItems = payload.items
  if (Array.isArray(savedItems) && savedItems.length) {
    return savedItems.map(item => createDefaultSz56tItem(item))
  }

  const orderItems = Array.isArray(order.items) ? order.items : []
  const totalQuantity = orderItems.reduce((sum, item) => sum + Number(item.quantity || 1), 0)
  const defaultUnitWeight = Math.max(1, Math.round(Number(fallbackWeight || 100) / Math.max(totalQuantity, 1)))

  if (!orderItems.length) {
    return [createDefaultSz56tItem({
      sku: '商品',
      invoice_title: 'Product',
      invoice_amount: Number(order.total_amount || 0),
      invoice_pcs: 1,
      invoice_weight: Number(fallbackWeight || 100),
      transaction_url: order.trade_order_url || order.order_transactionurl || ''
    })]
  }

  return orderItems.map(item => createDefaultSz56tItem({
    sku: item.name || '商品',
    invoice_title: item.name || 'Product',
    invoice_amount: '',
    invoice_pcs: Number(item.quantity || 1),
    invoice_weight: defaultUnitWeight,
    sku_code: item.sku_code || '',
    hs_code: item.hs_code || '',
    transaction_url: order.trade_order_url || order.order_transactionurl || '',
    invoice_currency: 'USD',
    invoiceunit_code: 'PCS',
    origin_country: 'CN',
    invoice_export_currency: 'USD',
    invoice_imgurl: item.img_url || ''
  }))
}

export function buildChinaPostReceiverFromOrder(order) {
  return createDefaultChinaPostContact({
    name: order.receiver_name || order.buyer_name || '',
    phone: order.receiver_phone || order.buyer_phone || '',
    mobile: order.receiver_phone || order.buyer_phone || '',
    email: order.buyer_email || '',
    post_code: order.receiver_zip || '',
    province: order.receiver_region || '',
    city: order.receiver_city || '',
    county: '',
    address: order.receiver_street || order.delivery_address || '',
    company: '',
    nation: 'RU',
    linker: order.receiver_name || order.buyer_name || 'Customer'
  })
}

export function buildChinaPostItemsFromOrder(order, fallbackWeight = 100) {
  const orderItems = Array.isArray(order.items) ? order.items : []
  const totalQuantity = orderItems.reduce((sum, item) => sum + Number(item.quantity || 1), 0)
  const defaultUnitWeight = Math.max(1, Math.round(Number(fallbackWeight || 100) / Math.max(totalQuantity, 1)))

  if (!orderItems.length) {
    return [createDefaultChinaPostItem({
      cargo_no: order.ae_order_id || '',
      cargo_name: '商品',
      cargo_name_en: 'Product',
      cost: Number(order.total_amount || 0),
      cargo_quantity: 1,
      cargo_weight: Number(fallbackWeight || 100)
    })]
  }

  return orderItems.map(item => createDefaultChinaPostItem({
    cargo_no: item.sku_code || item.ae_sku_id || '',
    cargo_name: item.name || '商品',
    cargo_name_en: item.name || 'Product',
    cargo_type_name: item.name || '商品',
    cargo_type_name_en: item.name || 'Product',
    cost: '',
    cargo_value: '',
    cargo_quantity: Number(item.quantity || 1),
    cargo_weight: defaultUnitWeight,
    cargo_origin_name: 'CN',
    cargo_currency: 'USD',
    unit: '个'
  }))
}
export function isDbsLogisticsType(value) {
  return String(value || '').toUpperCase() === 'DBS'
}

export function getFbsWorkflowStep(shipForm) {
  if (!shipForm || isDbsLogisticsType(shipForm.logistics_type)) {
    return 0
  }

  const currentStep = Number(shipForm.current_step || 0)
  const handoverStatus = String(shipForm.handover_list_status || '')

  let baseStep = 0

  if (!shipForm.logistic_order_id) {
    baseStep = 0
  } else if (!shipForm.handover_list_id) {
    baseStep = 1
  } else if (['Accepted', 'PartiallyAccepted', 'Sent', 'Transferred', 'Completed'].includes(handoverStatus)) {
    baseStep = 4
  } else {
    baseStep = 2
  }

  return currentStep > baseStep ? currentStep : baseStep
}

function normalizeLogisticsText(value) {
  return String(value || '').trim().toLowerCase()
}

function normalizeTrackingNumber(value) {
  return String(value || '').trim().toUpperCase()
}

function resolvePrintLabelProviderByTrackingNumber(trackingNumber) {
  const normalizedTrackingNumber = normalizeTrackingNumber(trackingNumber)

  if (normalizedTrackingNumber.startsWith('AML')) {
    return 'sz56t'
  }

  if (/^L[A-Z]\d{9}CN$/.test(normalizedTrackingNumber)) {
    return 'chinapost'
  }

  return ''
}

function appendPrintLabelProvider(providers, provider) {
  if (!provider || providers.includes(provider)) {
    return
  }

  providers.push(provider)
}

function getCurrentLogistics(order) {
  if (!order || typeof order !== 'object') return null

  const logistics = order.current_logistics || order.currentLogistics
  return logistics && typeof logistics === 'object' ? logistics : null
}

function isCancelledOrder(order) {
  if (!order || typeof order !== 'object') {
    return false
  }

  const deliveryStatus = normalizeLogisticsText(order.delivery_status || order.deliveryStatus)
  const finishReason = normalizeLogisticsText(order.finish_reason || order.finishReason)

  return deliveryStatus === 'cancelled' || deliveryStatus === 'canceled' || finishReason.includes('cancel')
}

function ensureCurrentLogistics(order) {
  if (!order || typeof order !== 'object') return null
  if (!order.current_logistics || typeof order.current_logistics !== 'object') {
    order.current_logistics = {}
  }

  return order.current_logistics
}

function getPrintLabelContext(order) {
  if (!order || typeof order !== 'object') {
    return {
      logistics: {},
      isDbs: false,
      providerCode: '',
      providerName: '',
      templateCode: '',
      externalOrderId: '',
      platformLogisticOrderId: '',
      trackingNumber: ''
    }
  }

  const logistics = getCurrentLogistics(order) || {}
  const payload = logistics.payload && typeof logistics.payload === 'object' ? logistics.payload : {}
  const sz56tPayload = payload.sz56t && typeof payload.sz56t === 'object' ? payload.sz56t : {}
  const aliexpressPayload = payload.aliexpress && typeof payload.aliexpress === 'object' ? payload.aliexpress : {}
  const platformLogisticOrders = Array.isArray(aliexpressPayload.logistic_orders) ? aliexpressPayload.logistic_orders : []

  return {
    logistics,
    isDbs: isDbsLogisticsType(order.logistics_type),
    providerCode: normalizeLogisticsText(logistics.provider_code || logistics.providerCode),
    providerName: normalizeLogisticsText(logistics.provider_name || logistics.providerName),
    templateCode: normalizeLogisticsText(logistics.template_code || logistics.templateCode || order.logistics_template),
    externalOrderId: logistics.external_order_id || logistics.externalOrderId || sz56tPayload.order_id || order.sz56t_order_id,
    platformLogisticOrderId: logistics.platform_logistic_order_id || logistics.platformLogisticOrderId || (platformLogisticOrders[0] && platformLogisticOrders[0].id) || order.logistic_order_id,
    trackingNumber: order.tracking_number || logistics.tracking_number || logistics.trackingNumber || ''
  }
}

function getDbsPlatformShipmentContext(order) {
  const logistics = getCurrentLogistics(order) || {}
  const payload = logistics.payload && typeof logistics.payload === 'object' ? logistics.payload : {}
  const offlineShip = payload.aliexpress_offline_ship && typeof payload.aliexpress_offline_ship === 'object'
    ? payload.aliexpress_offline_ship
    : {}

  let currentStatus = normalizeLogisticsText(offlineShip.current_status)
  if (!currentStatus) {
    if (offlineShip.delivered_at) {
      currentStatus = 'delivered'
    } else if (offlineShip.ready_for_pickup_at) {
      currentStatus = 'ready_for_pickup'
    } else if (offlineShip.in_transit_at || order.marked_ship_at) {
      currentStatus = 'in_transit'
    }
  }

  return {
    logistics,
    offlineShip,
    currentStatus
  }
}

export function resolvePrintLabelProvider(order) {
  return resolvePrintLabelProviderCandidates(order)[0] || ''
}

export function resolvePrintLabelProviderCandidates(order) {
  if (!order || typeof order !== 'object') return []

  const {
    isDbs,
    providerCode,
    externalOrderId,
    trackingNumber
  } = getPrintLabelContext(order)

  const providers = []

  if (!isDbs) {
    appendPrintLabelProvider(providers, 'platform')
    return providers
  }

  if (providerCode === 'chinapost') {
    appendPrintLabelProvider(providers, 'chinapost')
    return providers
  }

  if (providerCode === 'sz56t' || providerCode === 'leiyi') {
    appendPrintLabelProvider(providers, 'sz56t')
    return providers
  }

  const trackingProvider = resolvePrintLabelProviderByTrackingNumber(trackingNumber)
  if (trackingProvider === 'sz56t') {
    appendPrintLabelProvider(providers, 'sz56t')
    return providers
  }

  if (trackingProvider === 'chinapost') {
    appendPrintLabelProvider(providers, 'chinapost')
    return providers
  }

  if (externalOrderId) {
    appendPrintLabelProvider(providers, 'sz56t')
  }

  return providers
}

export function canPrintLabel(order) {
  const providers = resolvePrintLabelProviderCandidates(order)
  const logistics = getCurrentLogistics(order) || {}
  const logisticStatus = normalizeLogisticsText(logistics.logistic_status || logistics.logisticStatus)
  const {
    externalOrderId,
    platformLogisticOrderId,
    trackingNumber
  } = getPrintLabelContext(order)

  return providers.some(provider => {
    if (provider === 'sz56t') {
      return Boolean(externalOrderId || trackingNumber)
    }

    if (provider === 'chinapost') {
      return Boolean(trackingNumber)
    }

    if (provider === 'platform') {
      if (logisticStatus === 'cancelled' || isCancelledOrder(order)) {
        return false
      }
      return Boolean(platformLogisticOrderId)
    }

    return false
  })
}

export function canCreateTransferSheet(order) {
  return Boolean(order && !isDbsLogisticsType(order.logistics_type) && !isCancelledOrder(order) && order.logistic_order_id)
}

export function canShipOrder(order) {
  if (!order || !['WaitSendGoods', 'WaitingSellerSendGoods'].includes(order.order_display_status)) {
    return false
  }

  if (!isDbsLogisticsType(order.logistics_type)) {
    return true
  }

  return !order.actual_ship_at && !canCancelSz56tWaybill(order)
}

export function canSyncDbsOrderToPlatform(order) {
  return canMarkDbsInTransit(order)
}

export function canMarkDbsInTransit(order) {
  const { isDbs, trackingNumber } = getPrintLabelContext(order)
  const logistics = getCurrentLogistics(order) || {}
  const logisticStatus = normalizeLogisticsText(logistics.logistic_status || logistics.logisticStatus)
  const { currentStatus } = getDbsPlatformShipmentContext(order)

  return Boolean(order && isDbs && logisticStatus !== 'cancelled' && order.actual_ship_at && !currentStatus && trackingNumber)
}

export function canMarkDbsReadyForPickup(order) {
  const { isDbs } = getPrintLabelContext(order)
  const logistics = getCurrentLogistics(order) || {}
  const logisticStatus = normalizeLogisticsText(logistics.logistic_status || logistics.logisticStatus)
  const { currentStatus } = getDbsPlatformShipmentContext(order)

  return Boolean(order && isDbs && logisticStatus !== 'cancelled' && currentStatus === 'in_transit')
}

export function canMarkDbsDelivered(order) {
  const { isDbs } = getPrintLabelContext(order)
  const logistics = getCurrentLogistics(order) || {}
  const logisticStatus = normalizeLogisticsText(logistics.logistic_status || logistics.logisticStatus)
  const { currentStatus } = getDbsPlatformShipmentContext(order)

  return Boolean(order && isDbs && logisticStatus !== 'cancelled' && currentStatus === 'ready_for_pickup')
}

export function canCancelSz56tWaybill(order) {
  if (!order || typeof order !== 'object') {
    return false
  }

  const logistics = getCurrentLogistics(order) || {}
  const logisticStatus = normalizeLogisticsText(logistics.logistic_status || logistics.logisticStatus)
  const {
    isDbs,
    providerCode,
    templateCode,
    externalOrderId,
    trackingNumber
  } = getPrintLabelContext(order)
  const { currentStatus } = getDbsPlatformShipmentContext(order)

  if (!isDbs || logisticStatus === 'cancelled' || currentStatus) {
    return false
  }

  const isSz56t =
    providerCode === 'sz56t' ||
    providerCode === 'leiyi' ||
    templateCode === 'leiyi' ||
    Boolean(order && order.sz56t_order_id)

  return Boolean(isSz56t && (externalOrderId || trackingNumber))
}

export function getMarkShipButtonText(order) {
  return canSyncDbsOrderToPlatform(order) ? '发送到速卖通' : '更新订单'
}

export function createPdfObjectUrl(pdfBase64) {
  if (!pdfBase64) return ''
  const binary = atob(pdfBase64)
  const bytes = new Uint8Array(binary.length)
  for (let index = 0; index < binary.length; index++) {
    bytes[index] = binary.charCodeAt(index)
  }
  const blob = new Blob([bytes], { type: 'application/pdf' })
  return window.URL.createObjectURL(blob)
}

export function buildExportFilename(prefix = 'orders') {
  return `${prefix}_${new Date().getTime()}.csv`
}

export function applyCommentTempToOrder(order, commentTemp) {
  if (!order) return

  ;[
    'admin_remark',
    'backend_status',
    'purchase_image',
    'shipping_image',
    'purchase_date',
    'shipping_date',
    'purchase_amount',
    'logistics_fee',
    'apply_qianze_at',
    'ship_qianze_at'
  ].forEach(field => {
    order[field] = commentTemp[field]
  })
}

export function applyChinaPostCreateResult(order, waybillNo) {
  if (!order || !waybillNo) return
  order.tracking_number = waybillNo
  order.logistics_template = 'chinapost'

  const logistics = ensureCurrentLogistics(order)
  if (logistics) {
    Object.assign(logistics, {
      logistics_mode: 'DBS',
      provider_code: 'chinapost',
      provider_name: 'China Post',
      template_code: 'chinapost',
      tracking_number: waybillNo,
      logistic_status: 'created'
    })
  }
}

export function applySz56tCreateResult(order, data = {}) {
  if (!order) return
  if (data.tracking_number) {
    order.tracking_number = data.tracking_number
  }
  if (data.order_id) {
    order.sz56t_order_id = data.order_id
  }
  order.logistics_template = 'leiyi'

  const logistics = ensureCurrentLogistics(order)
  if (logistics) {
    Object.assign(logistics, {
      logistics_mode: 'DBS',
      provider_code: 'sz56t',
      provider_name: 'SZ56T',
      template_code: 'leiyi',
      external_order_id: data.order_id || order.sz56t_order_id || null,
      tracking_number: data.tracking_number || order.tracking_number || '',
      logistic_status: 'created'
    })
  }
}

export function applyShipResult(order, resultData = {}, fallbackTracking = '', shipProvider = '') {
  if (!order) return
  const returnedTracking = resultData && resultData.tracking_number ? resultData.tracking_number : fallbackTracking
  order.tracking_number = returnedTracking
  order.actual_ship_at = resultData && resultData.actual_ship_at ? resultData.actual_ship_at : new Date().toISOString()

  const logistics = ensureCurrentLogistics(order)
  if (logistics) {
    logistics.tracking_number = returnedTracking
    if (!logistics.logistics_mode) {
      logistics.logistics_mode = order.logistics_type || ''
    }
  }

  const providerResult = resultData && resultData.provider_result ? resultData.provider_result : {}
  if (shipProvider === 'leiyi') {
    order.logistics_template = 'leiyi'
    if (providerResult.order_id) {
      order.sz56t_order_id = providerResult.order_id
    }

    if (logistics) {
      Object.assign(logistics, {
        logistics_mode: 'DBS',
        provider_code: 'sz56t',
        provider_name: 'SZ56T',
        template_code: 'leiyi',
        external_order_id: providerResult.order_id || order.sz56t_order_id || null,
        logistic_status: providerResult.mark_shipped && providerResult.mark_shipped.success ? 'posted' : 'created'
      })
    }
  }

  if (shipProvider === 'chinapost') {
    order.logistics_template = 'chinapost'

    if (logistics) {
      Object.assign(logistics, {
        logistics_mode: 'DBS',
        provider_code: 'chinapost',
        provider_name: 'China Post',
        template_code: 'chinapost',
        logistic_status: 'created'
      })
    }
  }

  if (shipProvider === 'manual' && logistics) {
    Object.assign(logistics, {
      logistics_mode: 'DBS',
      provider_code: 'manual',
      provider_name: order.provider_name || logistics.provider_name || 'Manual'
    })
  }
}

export function applyPlatformSyncResult(order, resultData = {}) {
  if (!order) return

  const platformStatus = resultData && resultData.platform_status ? resultData.platform_status : ''

  if (resultData && resultData.marked_ship_at) {
    order.marked_ship_at = resultData.marked_ship_at
  } else if (platformStatus === 'in_transit') {
    order.marked_ship_at = new Date().toISOString()
  }

  if (!order.actual_ship_at) {
    if (resultData && resultData.actual_ship_at) {
      order.actual_ship_at = resultData.actual_ship_at
    } else if (platformStatus === 'in_transit') {
      order.actual_ship_at = new Date().toISOString()
    }
  }

  const logistics = ensureCurrentLogistics(order)
  if (!logistics) {
    return
  }

  const currentPayload = logistics.payload && typeof logistics.payload === 'object' ? logistics.payload : {}
  const currentOfflineShip = currentPayload.aliexpress_offline_ship && typeof currentPayload.aliexpress_offline_ship === 'object'
    ? currentPayload.aliexpress_offline_ship
    : {}
  const nextOfflineShip = resultData && resultData.platform_status_payload && typeof resultData.platform_status_payload === 'object'
    ? resultData.platform_status_payload
    : (platformStatus ? { current_status: platformStatus } : null)

  if (nextOfflineShip) {
    logistics.payload = {
      ...currentPayload,
      aliexpress_offline_ship: {
        ...currentOfflineShip,
        ...nextOfflineShip
      }
    }
  }
}

export function applySz56tCancelResult(order) {
  if (!order) return

  order.tracking_number = ''
  order.sz56t_order_id = ''
  order.actual_ship_at = ''
  order.marked_ship_at = ''
  order.logistics_template = ''

  const logistics = ensureCurrentLogistics(order)
  if (!logistics) {
    return
  }

  const currentPayload = logistics.payload && typeof logistics.payload === 'object' ? logistics.payload : {}
  logistics.payload = {
    ...currentPayload,
    sz56t: {
      ...(currentPayload.sz56t || {}),
      order_id: null
    }
  }

  Object.assign(logistics, {
    logistics_mode: 'DBS',
    provider_code: 'sz56t',
    provider_name: 'SZ56T',
    template_code: 'leiyi',
    external_order_id: null,
    tracking_number: '',
    logistic_status: 'cancelled'
  })
}
