import { login, logout, getInfo, getBootstrapContext } from '@/api/user'
import { getToken, setToken, removeToken } from '@/utils/auth'
import router, { resetRouter } from '@/router'

const state = {
  token: getToken(),
  name: '',
  avatar: '',
  introduction: '',
  roles: [],
  permissions: [],
  bootstrap: {
    menus: [],
    logistics_switches: {},
    system_configs: []
  }
}

const mutations = {
  SET_TOKEN: (state, token) => {
    state.token = token
  },
  SET_INTRODUCTION: (state, introduction) => {
    state.introduction = introduction
  },
  SET_NAME: (state, name) => {
    state.name = name
  },
  SET_AVATAR: (state, avatar) => {
    state.avatar = avatar
  },
  SET_ROLES: (state, roles) => {
    state.roles = roles
  },
  SET_PERMISSIONS: (state, permissions) => {
    state.permissions = permissions
  },
  SET_BOOTSTRAP: (state, bootstrap) => {
    state.bootstrap = {
      menus: Array.isArray(bootstrap.menus) ? bootstrap.menus : [],
      logistics_switches: bootstrap.logistics_switches && typeof bootstrap.logistics_switches === 'object'
        ? bootstrap.logistics_switches
        : {},
      system_configs: Array.isArray(bootstrap.system_configs) ? bootstrap.system_configs : []
    }
  }
}

const actions = {
  // user login
  login({ commit }, userInfo) {
    const { username, password } = userInfo
    return new Promise((resolve, reject) => {
      login({ username: username.trim(), password: password }).then(response => {
        const { data } = response
        commit('SET_TOKEN', data.token)
        setToken(data.token)
        resolve()
      }).catch(error => {
        reject(error)
      })
    })
  },

  // get user info
  getInfo({ commit, state }) {
    return new Promise((resolve, reject) => {
      getBootstrapContext().then(response => {
        const payload = response.data || {}
        const data = payload.user || payload
        const bootstrap = {
          menus: payload.menus || [],
          logistics_switches: payload.logistics_switches || {},
          system_configs: payload.system_configs || []
        }

        if (!data) {
          reject('Verification failed, please Login again.')
        }

        const { roles, permissions, name, avatar, introduction } = data

        // roles must be a non-empty array
        if (!roles || roles.length <= 0) {
          reject('getInfo: roles must be a non-null array!')
        }

        commit('SET_ROLES', roles)
        commit('SET_PERMISSIONS', Array.isArray(permissions) ? permissions.map(permission => permission.name || permission).filter(Boolean) : [])
        commit('SET_NAME', name)
        commit('SET_AVATAR', avatar)
        commit('SET_INTRODUCTION', introduction)
        commit('SET_BOOTSTRAP', bootstrap)
        resolve(data)
      }).catch(() => {
        getInfo().then(response => {
          const { data } = response

          if (!data) {
            reject('Verification failed, please Login again.')
          }

          const { roles, permissions, name, avatar, introduction } = data

          if (!roles || roles.length <= 0) {
            reject('getInfo: roles must be a non-null array!')
          }

          commit('SET_ROLES', roles)
          commit('SET_PERMISSIONS', Array.isArray(permissions) ? permissions.map(permission => permission.name || permission).filter(Boolean) : [])
          commit('SET_NAME', name)
          commit('SET_AVATAR', avatar)
          commit('SET_INTRODUCTION', introduction)
          commit('SET_BOOTSTRAP', { menus: [], logistics_switches: {}, system_configs: [] })
          resolve(data)
        }).catch(error => {
          reject(error)
        })
      })
    })
  },

  // user logout
  logout({ commit, state, dispatch }) {
    return new Promise((resolve, reject) => {
      logout(state.token).then(() => {
        commit('SET_TOKEN', '')
        commit('SET_ROLES', [])
        commit('SET_PERMISSIONS', [])
        commit('SET_BOOTSTRAP', { menus: [], logistics_switches: {}, system_configs: [] })
        removeToken()
        resetRouter()

        // reset visited views and cached views
        // to fixed https://github.com/PanJiaChen/vue-element-admin/issues/2485
        dispatch('tagsView/delAllViews', null, { root: true })

        resolve()
      }).catch(error => {
        reject(error)
      })
    })
  },

  // remove token
  resetToken({ commit }) {
    return new Promise(resolve => {
      commit('SET_TOKEN', '')
      commit('SET_ROLES', [])
      commit('SET_PERMISSIONS', [])
      commit('SET_BOOTSTRAP', { menus: [], logistics_switches: {}, system_configs: [] })
      removeToken()
      resolve()
    })
  },

  // dynamically modify permissions
  async changeRoles({ commit, dispatch }, role) {
    const token = role + '-token'

    commit('SET_TOKEN', token)
    setToken(token)

    const { roles } = await dispatch('getInfo')

    resetRouter()

    // generate accessible routes map based on roles
    const accessRoutes = await dispatch('permission/generateRoutes', roles, { root: true })
    // dynamically add accessible routes
    router.addRoutes(accessRoutes)

    // reset visited views and cached views
    dispatch('tagsView/delAllViews', null, { root: true })
  }
}

export default {
  namespaced: true,
  state,
  mutations,
  actions
}
