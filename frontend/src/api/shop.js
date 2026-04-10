import request from '@/utils/request'

// ========== 团队用户管理 ==========
export function fetchTeamUserList(data) {
  return request({url: '/team-users/list', method: 'post', data})
}

export function createTeamUser(data) {
  return request({url: '/team-users/create', method: 'post', data})
}

export function updateTeamUser(id, data) {
  return request({url: '/team-users/update', method: 'post', data: {id, ...data}})
}

export function deleteTeamUser(id) {
  return request({url: '/team-users/delete', method: 'post', data: {id}})
}

// ========== 团队管理 ==========
export function fetchTeamList(data) {
  return request({url: '/teams/list', method: 'post', data})
}

export function fetchAllTeams() {
  return request({url: '/teams/list', method: 'post', data: {all: 1}})
}

export function createTeam(data) {
  return request({url: '/teams/create', method: 'post', data})
}

export function fetchTeamDetail(id) {
  return request({url: '/teams/detail', method: 'post', data: {id}})
}

export function updateTeam(id, data) {
  return request({url: '/teams/update', method: 'post', data: {id, ...data}})
}

export function deleteTeam(id) {
  return request({url: '/teams/delete', method: 'post', data: {id}})
}

// ========== 店铺管理 ==========
export function fetchShopList(data) {
  return request({url: '/shops/list', method: 'post', data})
}

export function createShop(data) {
  return request({url: '/shops/create', method: 'post', data})
}

export function fetchShopDetail(id) {
  return request({url: '/shops/detail', method: 'post', data: {id}})
}

export function updateShop(id, data) {
  return request({url: '/shops/update', method: 'post', data: {id, ...data}})
}

export function deleteShop(id) {
  return request({url: '/shops/delete', method: 'post', data: {id}})
}
