import { fetchMenuList, fetchLogisticsConfigCurrent } from '@/api/system'
import { constantRoutes } from '@/router'
import { buildRoutesFromMenus } from '@/router/menu-routes'

const notFoundRoute = {
  path: '*',
  redirect: '/404',
  hidden: true
}

function filterLogisticsConfigRoutes(routes, roles, switches) {
  return routes
    .map(route => {
      const clonedRoute = { ...route }

      if (clonedRoute.children) {
        clonedRoute.children = filterLogisticsConfigRoutes(clonedRoute.children, roles, switches)
      }

      if (clonedRoute.name === 'ShopLogisticsConfig') {
        const anySwitchOn = switches && (switches.enable_team_logistics_config || switches.enable_user_logistics_config)
        if (!roles.includes('super-admin') && !anySwitchOn) {
          return null
        }
      }

      return clonedRoute
    })
    .filter(Boolean)
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
  async generateRoutes({ commit, state, rootGetters }, roles) {
    const bootstrap = rootGetters.bootstrap || {}
    let menus = Array.isArray(bootstrap.menus) ? bootstrap.menus : []
    if (!menus.length) {
      const response = await fetchMenuList({ tree: 1 })
      menus = response.data || []
    }
    const permissions = rootGetters.permissions || []

    let accessedRoutes = buildRoutesFromMenus(menus, permissions, roles)
    accessedRoutes = filterLogisticsConfigRoutes(accessedRoutes, roles, state.logisticsSwitches)
    accessedRoutes = accessedRoutes.concat([notFoundRoute])

    commit('SET_ROUTES', accessedRoutes)
    return accessedRoutes
  },
  async loadLogisticsSwitches({ commit, rootGetters }) {
    const bootstrap = rootGetters.bootstrap || {}
    const bootstrapSwitches = bootstrap.logistics_switches
    if (bootstrapSwitches && typeof bootstrapSwitches === 'object' && Object.keys(bootstrapSwitches).length) {
      commit('SET_LOGISTICS_SWITCHES', bootstrapSwitches)
      return bootstrapSwitches
    }

    try {
      const switchRes = await fetchLogisticsConfigCurrent()
      const switches = (switchRes && switchRes.data && switchRes.data.switches) || {}
      commit('SET_LOGISTICS_SWITCHES', switches)
      return switches
    } catch (error) {
      commit('SET_LOGISTICS_SWITCHES', {})
      return {}
    }
  }
}

export default {
  namespaced: true,
  state,
  mutations,
  actions
}
