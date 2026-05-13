import request from '@/utils/request'

export function getProductList(data) {
  return request({ url: '/products/list', method: 'post', data })
}

export function syncShopProducts(data) {
  return request({ url: '/products/sync-shop', method: 'post', data })
}

export function startProductExport(data) {
  return request({
    url: '/products/export',
    method: 'post',
    data,
    timeout: 0
  })
}

export function fetchProductExportProgress(data) {
  return request({ url: '/products/export-progress', method: 'post', data })
}

export function getProductExportHistory(data) {
  return request({ url: '/products/export-history', method: 'post', data })
}

export function downloadProductExport(data) {
  return request({
    url: '/products/export-download',
    method: 'post',
    data,
    responseType: 'blob',
    timeout: 0
  })
}

export function deleteProductExport(data) {
  return request({ url: '/products/export-delete', method: 'post', data })
}
