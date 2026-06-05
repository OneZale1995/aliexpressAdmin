import Layout from '@/layout'

const viewContext = require.context('../views', true, /\.vue$/)

const componentAliasMap = {
  'shop/team': 'team/index'
}

const routeNameMap = {
  '/order': 'Order',
  '/product': 'Product',
  '/shop': 'Shop',
  '/system': 'System',
  '/team': 'Team',
  'order/blacklist': 'OrderBlacklist',
  'order/index': 'OrderManage',
  'order/statistics': 'OrderStatistics',
  'product/export': 'ProductExportManage',
  'product/index': 'ProductListManage',
  'shop/index': 'ShopManage',
  'shop/logistics-config': 'ShopLogisticsConfig',
  'shop/team': 'TeamManage',
  'system/config': 'SystemConfig',
  'system/customs-product': 'CustomsProduct',
  'system/dict': 'DictManage',
  'system/file': 'FileManage',
  'system/log': 'OperationLog',
  'system/login-log': 'LoginLog',
  'system/menu': 'MenuManage',
  'system/permission': 'PermissionManage',
  'system/role': 'RoleManage',
  'system/user': 'UserManage',
  'team/index': 'TeamManage',
  'team/user': 'TeamUserManage'
}

function normalizeComponentPath(component) {
  return componentAliasMap[component] || component
}

function buildRouteName(menu) {
  const componentKey = menu.component === 'Layout' ? menu.path : menu.component
  return routeNameMap[componentKey] || routeNameMap[menu.path] || menu.name || menu.title || 'Route'
}

function resolveRouteComponent(component) {
  const resolvedComponent = normalizeComponentPath(component)
  const viewPath = `./${resolvedComponent}.vue`

  if (!viewContext.keys().includes(viewPath)) {
    return null
  }

  return viewContext(viewPath).default || viewContext(viewPath)
}

function resolveRedirect(basePath, childPath) {
  if (!basePath) {
    return childPath
  }

  if (childPath.startsWith('/')) {
    return childPath
  }

  return `${basePath.replace(/\/$/, '')}/${childPath}`
}

function hasMenuPermission(menu, permissions, roles) {
  if (roles.includes('super-admin')) {
    return true
  }

  if (!menu.permission) {
    // 没有配置 permission 的菜单目录，交给 children 判断：
    // 如果有可见子菜单则保留父级，否则隐藏
    return false
  }

  return permissions.includes(menu.permission) || permissions.some(permission => permission.startsWith(`${menu.permission}.`))
}

function toBoolean(value) {
  return value === true || value === 1 || value === '1'
}

function buildRouteFromMenu(menu, permissions, roles) {
  if (!menu || toBoolean(menu.status) === false || Number(menu.type) === 3) {
    return null
  }

  const children = Array.isArray(menu.children)
    ? menu.children.map(child => buildRouteFromMenu(child, permissions, roles)).filter(Boolean)
    : []
  const hasAccess = hasMenuPermission(menu, permissions, roles)

  if (!hasAccess && children.length === 0) {
    return null
  }

  const route = {
    path: menu.path,
    hidden: toBoolean(menu.hidden),
    name: buildRouteName(menu),
    meta: {
      title: menu.title,
      icon: menu.icon,
      permission: menu.permission || ''
    }
  }

  if (menu.component === 'Layout') {
    route.component = Layout

    if (children.length) {
      route.children = children
      route.redirect = resolveRedirect(menu.path, children[0].path)
      route.alwaysShow = children.length > 1
    } else {
      route.redirect = 'noRedirect'
    }

    return route
  }

  const component = resolveRouteComponent(menu.component)
  if (!component) {
    return null
  }

  route.component = component

  if (children.length) {
    route.children = children
  }

  return route
}

export function buildRoutesFromMenus(menus = [], permissions = [], roles = []) {
  return menus
    .map(menu => buildRouteFromMenu(menu, permissions, roles))
    .filter(Boolean)
}
