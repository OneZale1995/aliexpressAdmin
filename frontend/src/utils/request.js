import axios from 'axios'
import { MessageBox, Message } from 'element-ui'
import store from '@/store'
import { getToken } from '@/utils/auth'

const service = axios.create({
  baseURL: process.env.VUE_APP_BASE_API,
  timeout: 30000
})

// request interceptor
service.interceptors.request.use(
  config => {
    // do something before request is sent

    if (store.getters.token) {
      // let each request carry token
      config.headers['Authorization'] = 'Bearer ' + getToken()
    }
    return config
  },
  error => {
    // do something with request error
    console.log(error) // for debug
    return Promise.reject(error)
  }
)

// 从 Laravel 422 errors 对象中提取第一条可读错误信息
function extractLaravelValidationMessage(errorData) {
  if (!errorData) return null
  // Laravel 422: { message: '...', errors: { field: ['msg1', ...] } }
  const errors = errorData.errors
  if (errors && typeof errors === 'object') {
    const firstField = Object.keys(errors)[0]
    if (firstField) {
      const msgs = errors[firstField]
      if (Array.isArray(msgs) && msgs.length) {
        return `${firstField}: ${msgs[0]}`
      }
    }
  }
  // 如果 message 本身是翻译 key（含点号且无空格），也尝试用 errors
  const msg = errorData.message || ''
  if (msg && !/\s/.test(msg) && msg.includes('.')) {
    return null // 让调用方用 fallback
  }
  return msg || null
}

// response interceptor
service.interceptors.response.use(
  response => {
    if (response.config && response.config.responseType === 'blob') {
      return response
    }

    const res = response.data
    const silentError = Boolean(response.config && response.config.silentError)

    // if the custom code is not 20000, it is judged as an error.
    if (res.code !== 20000) {
      if (!silentError) {
        Message({
          message: res.message || 'Error',
          type: 'error',
          duration: 5 * 1000
        })
      }

      // 50008: Illegal token; 50012: Other clients logged in; 50014: Token expired;
      if (res.code === 50008 || res.code === 50012 || res.code === 50014) {
        MessageBox.confirm('登录已过期，请重新登录', '提示', {
          confirmButtonText: '重新登录',
          cancelButtonText: '取消',
          type: 'warning'
        }).then(() => {
          store.dispatch('user/resetToken').then(() => {
            location.reload()
          })
        })
      }
      // 标记已弹过，业务层 catch 不再重复弹
      const err = new Error(res.message || 'Error')
      err.alreadyNotified = true
      return Promise.reject(err)
    } else {
      return res
    }
  },
  error => {
    console.log('err' + error)
    const silentError = Boolean(error.config && error.config.silentError)
    if (!silentError) {
      // 尝试从 Laravel 422 响应中提取可读错误信息
      const responseData = error.response && error.response.data
      const validationMsg = extractLaravelValidationMessage(responseData)
      const displayMsg = validationMsg || error.message
      Message({
        message: displayMsg,
        type: 'error',
        duration: 5 * 1000
      })
      // 标记已弹过，业务层 catch 不再重复弹
      const notifiedError = new Error(displayMsg)
      notifiedError.alreadyNotified = true
      return Promise.reject(notifiedError)
    }
    return Promise.reject(error)
  }
)

export default service
