import defaultSettings from '@/settings'

const SITE_SETTINGS_STORAGE_KEY = 'site-settings'
const SITE_SETTINGS_UPDATED_EVENT = 'site-settings-updated'

function getFallbackTitle() {
  return defaultSettings.title || 'Admin'
}

function getStorage() {
  if (typeof window === 'undefined') {
    return null
  }

  return window.localStorage
}

export function getSiteSettings() {
  const storage = getStorage()
  if (!storage) {
    return {}
  }

  try {
    const raw = storage.getItem(SITE_SETTINGS_STORAGE_KEY)
    return raw ? JSON.parse(raw) : {}
  } catch (error) {
    return {}
  }
}

export function getSiteTitle() {
  return getSiteSettings().site_name || getFallbackTitle()
}

export function getSiteLogo() {
  return getSiteSettings().site_logo || ''
}

export function extractSiteSettings(configs = []) {
  return configs.reduce((settings, item) => {
    if (!item || item.group !== 'site') {
      return settings
    }

    if (item.key === 'site_name') {
      settings.site_name = item.value || ''
    }

    if (item.key === 'site_logo') {
      settings.site_logo = item.value || ''
    }

    return settings
  }, {})
}

export function setSiteSettings(settings = {}) {
  const storage = getStorage()
  const nextSettings = {
    ...getSiteSettings(),
    ...settings
  }

  if (storage) {
    try {
      storage.setItem(SITE_SETTINGS_STORAGE_KEY, JSON.stringify(nextSettings))
    } catch (error) {
      // Ignore storage write failures and still notify the app.
    }
  }

  if (typeof window !== 'undefined') {
    window.dispatchEvent(new CustomEvent(SITE_SETTINGS_UPDATED_EVENT, { detail: nextSettings }))
  }

  return nextSettings
}

export function updateSiteSettingsFromConfigs(configs = []) {
  const settings = extractSiteSettings(configs)
  if (!Object.keys(settings).length) {
    return getSiteSettings()
  }

  return setSiteSettings(settings)
}

export function subscribeSiteSettings(handler) {
  if (typeof window === 'undefined' || typeof handler !== 'function') {
    return
  }

  window.addEventListener(SITE_SETTINGS_UPDATED_EVENT, handler)
}

export function unsubscribeSiteSettings(handler) {
  if (typeof window === 'undefined' || typeof handler !== 'function') {
    return
  }

  window.removeEventListener(SITE_SETTINGS_UPDATED_EVENT, handler)
}