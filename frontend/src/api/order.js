import request from '@/utils/request'

export function fetchOrderList(data) {
  return request({ url: '/orders/list', method: 'post', data })
}

export function fetchOrderStatusCounts(data) {
  return request({ url: '/orders/status-counts', method: 'post', data })
}

export function fetchOrderBackendStatusCounts(data) {
  return request({ url: '/orders/backend-status-counts', method: 'post', data })
}

export function fetchOrderStatistics(data) {
  return request({ url: '/orders/statistics', method: 'post', data })
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

export function batchUpdateOrderBackendStatus(data) {
  return request({ url: '/orders/batch-update-backend-status', method: 'post', data })
}

export function shipOrder(data) {
  return request({ url: '/orders/ship', method: 'post', data })
}

export function shipFbsOrder(data) {
  return request({ url: '/orders/ship/fbs', method: 'post', data })
}

export function shipDbsChinaPostOrder(data) {
  return request({ url: '/orders/ship/dbs/chinapost', method: 'post', data })
}

export function shipDbsLeiyiOrder(data) {
  return request({ url: '/orders/ship/dbs/leiyi', method: 'post', data })
}

export function syncDbsShipmentToPlatform(data) {
  return request({ url: '/orders/dbs/sync-platform', method: 'post', data })
}

export function syncDbsShipmentReadyForPickup(data) {
  return request({ url: '/orders/dbs/ready-for-pickup', method: 'post', data })
}

export function syncDbsShipmentDelivered(data) {
  return request({ url: '/orders/dbs/delivered', method: 'post', data })
}

export function getOrderLabel(data) {
  return request({ url: '/orders/label', method: 'post', data, silentError: true })
}

export function createOrderTransferSheet(data) {
  return request({ url: '/orders/transfer-sheet', method: 'post', data })
}

export function fetchFbsWorkflow(data) {
  return request({ url: '/orders/fbs/workflow', method: 'post', data })
}

export function createFbsLogisticOrder(data) {
  return request({ url: '/orders/fbs/logistic-order/create', method: 'post', data })
}

export function createFbsHandoverList(data) {
  return request({ url: '/orders/fbs/handover-list/create', method: 'post', data })
}

export function addFbsLogisticOrdersToHandover(data) {
  return request({ url: '/orders/fbs/handover-list/add-orders', method: 'post', data })
}

export function removeFbsLogisticOrdersFromHandover(data) {
  return request({ url: '/orders/fbs/handover-list/remove-orders', method: 'post', data })
}

export function printFbsHandoverList(data) {
  return request({ url: '/orders/fbs/handover-list/label', method: 'post', data })
}

export function readyFbsHandoverForPickup(data) {
  return request({ url: '/orders/fbs/handover-list/ready-for-pickup', method: 'post', data })
}

export function transferFbsHandoverList(data) {
  return request({ url: '/orders/fbs/handover-list/transfer', method: 'post', data })
}

export function exportOrders(data) {
  return request({ url: '/orders/export', method: 'post', data, responseType: 'blob' })
}

export function chinaPostPreviewOrder(data) {
  return request({ url: '/orders/chinapost/preview', method: 'post', data })
}

export function chinaPostCreateOrder(data) {
  return request({ url: '/orders/chinapost/create', method: 'post', data })
}

export function chinaPostGetLabel(data) {
  return request({ url: '/orders/chinapost/label', method: 'post', data, silentError: true })
}

export function chinaPostCancelOrder(data) {
  return request({ url: '/orders/chinapost/cancel', method: 'post', data })
}

export function fetchOrderAddressBookList(data) {
  return request({ url: '/orders/address-books/list', method: 'post', data })
}

export function saveOrderAddressBook(data) {
  return request({ url: '/orders/address-books/save', method: 'post', data })
}

export function deleteOrderAddressBook(data) {
  return request({ url: '/orders/address-books/delete', method: 'post', data })
}

export function fetchOrderAddressBookRegionOptions(data) {
  return request({ url: '/orders/address-books/region-options', method: 'post', data })
}

export function sz56tCreateOrder(data) {
  return request({ url: '/orders/sz56t/create', method: 'post', data })
}

export function fetchSz56tProductList(data) {
  return request({ url: '/orders/sz56t/products', method: 'post', data })
}

export function sz56tGetLabel(data) {
  return request({ url: '/orders/sz56t/label', method: 'post', data, silentError: true })
}

export function sz56tMarkShipped(data) {
  return request({ url: '/orders/sz56t/mark-shipped', method: 'post', data })
}

export function sz56tGetTrackingNumber(data) {
  return request({ url: '/orders/sz56t/tracking-number', method: 'post', data })
}

export function sz56tCancelOrder(data) {
  return request({ url: '/orders/sz56t/cancel', method: 'post', data })
}
