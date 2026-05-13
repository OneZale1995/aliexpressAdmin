import request from '@/utils/request'

// ========== 用户管理 ==========
export function fetchUserList(data) {
  return request({ url: '/users/list', method: 'post', data })
}

export function createUser(data) {
  return request({ url: '/users/create', method: 'post', data })
}

export function updateUser(id, data) {
  return request({ url: '/users/update', method: 'post', data: { id, ...data } })
}

export function deleteUser(id) {
  return request({ url: '/users/delete', method: 'post', data: { id } })
}

// ========== 角色管理 ==========
export function fetchRoleList(data) {
  return request({ url: '/roles/list', method: 'post', data })
}

export function fetchAllRoles() {
  return request({ url: '/roles/list', method: 'post', data: { all: 1 } })
}

export function createRole(data) {
  return request({ url: '/roles/create', method: 'post', data })
}

export function updateRole(id, data) {
  return request({ url: '/roles/update', method: 'post', data: { id, ...data } })
}

export function deleteRole(id) {
  return request({ url: '/roles/delete', method: 'post', data: { id } })
}

// ========== 权限管理 ==========
export function fetchPermissionList(data) {
  return request({ url: '/permissions/list', method: 'post', data })
}

export function fetchPermissionTree() {
  return request({ url: '/permissions/list', method: 'post', data: { tree: 1 } })
}

export function createPermission(data) {
  return request({ url: '/permissions/create', method: 'post', data })
}

export function updatePermission(id, data) {
  return request({ url: '/permissions/update', method: 'post', data: { id, ...data } })
}

export function deletePermission(id) {
  return request({ url: '/permissions/delete', method: 'post', data: { id } })
}

// ========== 菜单管理 ==========
export function fetchMenuList() {
  return request({ url: '/menus/list', method: 'post' })
}

export function createMenu(data) {
  return request({ url: '/menus/create', method: 'post', data })
}

export function updateMenu(id, data) {
  return request({ url: '/menus/update', method: 'post', data: { id, ...data } })
}

export function deleteMenu(id) {
  return request({ url: '/menus/delete', method: 'post', data: { id } })
}

// ========== 操作日志 ==========
export function fetchLogList(data) {
  return request({ url: '/operation-logs/list', method: 'post', data })
}

export function deleteLog(id) {
  return request({ url: '/operation-logs/delete', method: 'post', data: { id } })
}

export function clearLogs(days) {
  return request({ url: '/operation-logs/clear', method: 'post', data: { days } })
}

// ========== 登录日志 ==========
export function fetchLoginLogList(data) {
  return request({ url: '/login-logs/list', method: 'post', data })
}

export function deleteLoginLog(id) {
  return request({ url: '/login-logs/delete', method: 'post', data: { id } })
}

export function clearLoginLogs(days) {
  return request({ url: '/login-logs/clear', method: 'post', data: { days } })
}

// ========== 文件管理 ==========
export function fetchFileList(data) {
  return request({ url: '/files/list', method: 'post', data })
}

export function uploadFile(data) {
  return request({ url: '/files/upload', method: 'post', data, headers: { 'Content-Type': 'multipart/form-data' } })
}

export function deleteFile(id) {
  return request({ url: '/files/delete', method: 'post', data: { id } })
}

// ========== 系统配置 ==========
export function fetchConfigList(data) {
  return request({ url: '/system-configs/list', method: 'post', data })
}

export function createConfig(data) {
  return request({ url: '/system-configs/create', method: 'post', data })
}

export function updateConfig(id, data) {
  return request({ url: '/system-configs/update', method: 'post', data: { id, ...data } })
}

export function deleteConfig(id) {
  return request({ url: '/system-configs/delete', method: 'post', data: { id } })
}

export function batchSaveConfig(configs) {
  return request({ url: '/system-configs/batch', method: 'post', data: { configs } })
}

export function fetchLogisticsConfigCurrent(data) {
  return request({ url: '/logistics-configs/current', method: 'post', data })
}

export function saveLogisticsConfig(data) {
  return request({ url: '/logistics-configs/save', method: 'post', data })
}

// ========== 数据字典 ==========
export function fetchDictTypeList(data) {
  return request({ url: '/dict-types/list', method: 'post', data })
}

export function createDictType(data) {
  return request({ url: '/dict-types/create', method: 'post', data })
}

export function updateDictType(id, data) {
  return request({ url: '/dict-types/update', method: 'post', data: { id, ...data } })
}

export function deleteDictType(id) {
  return request({ url: '/dict-types/delete', method: 'post', data: { id } })
}

export function fetchDictDataList(data) {
  return request({ url: '/dict-data/list', method: 'post', data })
}

export function createDictData(data) {
  return request({ url: '/dict-data/create', method: 'post', data })
}

export function updateDictData(id, data) {
  return request({ url: '/dict-data/update', method: 'post', data: { id, ...data } })
}

export function deleteDictData(id) {
  return request({ url: '/dict-data/delete', method: 'post', data: { id } })
}

export function fetchDictByCode(code) {
  return request({ url: '/dict/get', method: 'post', data: { code } })
}

export function fetchDictBatch(codes) {
  return request({ url: '/dict/batch', method: 'post', data: { codes } })
}

// ========== 个人中心 ==========
export function updateProfile(data) {
  return request({ url: '/profile/update', method: 'post', data })
}

export function updatePassword(data) {
  return request({ url: '/profile/password', method: 'post', data })
}

export function uploadAvatar(data) {
  return request({ url: '/profile/avatar', method: 'post', data, headers: { 'Content-Type': 'multipart/form-data' } })
}

// ========== 报关商品管理 ==========
export function fetchCustomsProductList() {
  return request({ url: '/customs-products/list', method: 'post' })
}

export function createCustomsProduct(data) {
  return request({ url: '/customs-products/create', method: 'post', data })
}

export function updateCustomsProduct(data) {
  return request({ url: '/customs-products/update', method: 'post', data })
}

export function deleteCustomsProduct(data) {
  return request({ url: '/customs-products/delete', method: 'post', data })
}

// ========== 通用导出 ==========
export function exportData(data) {
  return request({ url: '/export', method: 'post', data, responseType: 'blob' })
}
