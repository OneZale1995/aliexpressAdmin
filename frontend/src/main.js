import Vue from 'vue'

import Cookies from 'js-cookie'

import 'normalize.css/normalize.css' // a modern alternative to CSS resets

import Element from 'element-ui'
import './styles/element-variables.scss'
import zhLang from 'element-ui/lib/locale/lang/zh-CN'

import '@/styles/index.scss' // global css

import App from './App'
import store from './store'
import router from './router'

import './icons' // icon
import './permission' // permission control
import './utils/error-log' // error log

import * as filters from './filters' // global filters
import { fetchConfigList } from '@/api/system'
import { getToken } from '@/utils/auth'
import getPageTitle from '@/utils/get-page-title'
import { updateSiteSettingsFromConfigs } from '@/utils/site-settings'

Vue.use(Element, {
  size: Cookies.get('size') || 'medium', // set element-ui default size
  locale: zhLang
})

// register global utility filters
Object.keys(filters).forEach(key => {
  Vue.filter(key, filters[key])
})

Vue.config.productionTip = false

new Vue({
  el: '#app',
  router,
  store,
  render: h => h(App)
})

if (getToken()) {
  fetchConfigList({ group: 'site' }).then(response => {
    updateSiteSettingsFromConfigs(Array.isArray(response.data) ? response.data : [])
    const currentTitle = router.currentRoute.meta && router.currentRoute.meta.title
    document.title = getPageTitle(currentTitle)
  }).catch(() => {})
}
