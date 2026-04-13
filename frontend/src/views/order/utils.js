import { ORDER_DICT_CODE, ORDER_TAB_LABELS, STATUS_TAG_TYPE } from './constants'

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

export function getLogisticOrderStatusLabel(status, dictLabelMap) {
  return translateByCode(status, ORDER_DICT_CODE.logisticOrderStatus, dictLabelMap)
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
  if (template === 'offline_leiyi') return '线下-雷翼/邮政'
  if (template === 'offline_epacket') return '线下-E邮宝'
  if (template === 'online') return '线上'
  return template || '-'
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
    { key: '', label: allLabel, countKey: 'all' },
    ...options.map(item => ({ key: String(item.value), label: item.label, countKey: String(item.value) }))
  ]
}

export function buildStatusTabs(options = []) {
  return buildFilterTabs(options, ORDER_TAB_LABELS.allOrders)
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

export function calculateEubLogisticsFee({ purchaseAmount, amazonRatio, baseFee }) {
  const purchase = Number(purchaseAmount || 0)
  const ratio = Number(amazonRatio || 0)
  const base = Number(baseFee || 0)
  return Number((purchase * ratio / 100 + base).toFixed(2))
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

export function calcProfitCny(order, cnyExchangeRate) {
  const profit = calcProfit(order)
  const rate = parseFloat(cnyExchangeRate || 0)
  if (!(rate > 0)) return '0.00'
  return (profit / rate).toFixed(2)
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

export function canPrintLabel(order) {
  return Boolean(order && (order.sz56t_order_id || (order.tracking_number && isDbsLogisticsType(order.logistics_type)) || order.logistic_order_id))
}

export function canCreateTransferSheet(order) {
  return Boolean(order && !isDbsLogisticsType(order.logistics_type) && order.logistic_order_id)
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
    'lianlian_fee',
    'purchase_amount',
    'express_fee',
    'logistics_fee',
    'logistics_template',
    'eub_amazon_ratio',
    'eub_base_fee',
    'calculated_logistics_fee',
    'logistics_fee_override',
    'apply_qianze_at',
    'ship_qianze_at'
  ].forEach(field => {
    order[field] = commentTemp[field]
  })
}

export function applyChinaPostCreateResult(order, waybillNo) {
  if (!order || !waybillNo) return
  order.tracking_number = waybillNo
  order.logistics_template = 'offline_epacket'
}

export function applySz56tCreateResult(order, data = {}) {
  if (!order) return
  if (data.tracking_number) {
    order.tracking_number = data.tracking_number
  }
  if (data.order_id) {
    order.sz56t_order_id = data.order_id
  }
  order.logistics_template = 'offline_leiyi'
}

export function applyShipResult(order, resultData = {}, fallbackTracking = '') {
  if (!order) return
  const returnedTracking = resultData && resultData.tracking_number ? resultData.tracking_number : fallbackTracking
  order.tracking_number = returnedTracking
  order.actual_ship_at = resultData && resultData.actual_ship_at ? resultData.actual_ship_at : new Date().toISOString()
}
