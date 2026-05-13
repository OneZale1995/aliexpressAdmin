import { asyncRoutes, constantRoutes } from '@/router'

/**
 * Use meta.role to determine if the current user has permission
 * @param roles
 * @param route
 */
function hasPermission(roles, route) {
  if (route.meta && route.meta.roles) {
    return roles.some(role => route.meta.roles.includes(role))
  } else {
    return true
  }
}

/**
 * Filter asynchronous routing tables by recursion
 * @param routes asyncRoutes
 * @param roles
 * @param switches
 */
export function filterAsyncRoutes(routes, roles, switches) {
  const res = []

  routes.forEach(route => {
    const tmp = { ...route }
    if (hasPermission(roles, tmp)) {
      if (tmp.children) {
        tmp.children = filterAsyncRoutes(tmp.children, roles, switches)
      }
      // 物流配置菜单：开关都关闭时仅超级管理员可见
      if (tmp.name === 'ShopLogisticsConfig') {
        const anySwitchOn = switches && (switches.enable_team_logistics_config || switches.enable_user_logistics_config)
        if (!roles.includes('super-admin') && !anySwitchOn) {
          return
        }
      }
      res.push(tmp)
    }
  })

  return res
}

const state = {
  routes: [],
  addRoutes: [],
  logisticsSwitches: {}
}

const mutations = {
  SET_ROUTES: (state, routes) => {
    state.addRoutes = routes
    state.routes = constantRoutes.concat(routes)
  },
  SET_LOGISTICS_SWITCHES: (state, switches) => {
    state.logisticsSwitches = switches
  }
}

const actions = {
  generateRoutes({ commit, state }, roles) {
    return new Promise(resolve => {
      let accessedRoutes
      if (roles.includes('super-admin')) {
        accessedRoutes = asyncRoutes || []
      } else {
        accessedRoutes = filterAsyncRoutes(asyncRoutes, roles, state.logisticsSwitches)
      }
      commit('SET_ROUTES', accessedRoutes)
      resolve(accessedRoutes)
    })
  }
}

export default {
  namespaced: true,
  state,
  mutations,
  actions
}
