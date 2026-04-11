import request from '@/utils/request'

export function fetchOrderList(data) {
  return request({ url: '/orders/list', method: 'post', data })
}

export function fetchOrderStatusCounts(data) {
  return request({ url: '/orders/status-counts', method: 'post', data })
}

export function syncOrders(data) {
  return request({ url: '/orders/sync', method: 'post', data })
}

export function syncOrdersStart(data) {
  return request({ url: '/orders/sync-start', method: 'post', data })
}

export function fetchSyncProgress(data) {
  return request({ url: '/orders/sync-progress', method: 'post', data })
}

export function updateOrderComment(data) {
  return request({ url: '/orders/update-comment', method: 'post', data })
}

export function updateOrderBackendFields(data) {
  return request({ url: '/orders/update-backend-fields', method: 'post', data })
}

export function shipOrder(data) {
  return request({ url: '/orders/ship', method: 'post', data })
}

export function getOrderLabel(data) {
  return request({ url: '/orders/label', method: 'post', data })
}

export function exportOrders(data) {
  return request({ url: '/orders/export', method: 'post', data, responseType: 'blob' })
}

export function chinaPostCreateOrder(data) {
  return request({ url: '/orders/chinapost/create', method: 'post', data })
}

export function chinaPostGetLabel(data) {
  return request({ url: '/orders/chinapost/label', method: 'post', data })
}

export function chinaPostCancelOrder(data) {
  return request({ url: '/orders/chinapost/cancel', method: 'post', data })
}

export function sz56tCreateOrder(data) {
  return request({ url: '/orders/sz56t/create', method: 'post', data })
}

export function sz56tGetLabel(data) {
  return request({ url: '/orders/sz56t/label', method: 'post', data })
}

export function sz56tMarkShipped(data) {
  return request({ url: '/orders/sz56t/mark-shipped', method: 'post', data })
}

export function sz56tGetTrackingNumber(data) {
  return request({ url: '/orders/sz56t/tracking-number', method: 'post', data })
}
