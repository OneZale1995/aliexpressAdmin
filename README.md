# Laravel Element Admin

基于 **Laravel 12 + Vue 2 + Element UI** 的后台管理系统脚手架，开箱即用。

---

## 技术栈

| 层级 | 技术 | 版本 |
|------|------|------|
| 后端 | Laravel | 12.x |
| 认证 | Sanctum | 4.x |
| 前端 | Vue.js | 2.x |
| UI 框架 | Element UI | 2.x（中文） |
| 前端模板 | vue-element-admin | 4.4.0 |
| 数据库 | MySQL | 5.7+ |

## 功能模块

- **用户管理** — username 登录，角色分配，状态开关
- **角色管理** — 角色 CRUD，权限分配（树形勾选）
- **权限管理** — 树形权限节点，支持层级
- **菜单管理** — 动态菜单配置，图标选择器（SVG + Element UI 图标）
- **操作日志** — 自动记录写操作，支持筛选/清理
- **登录日志** — 登录成功/失败记录，IP/UA 记录
- **文件管理** — 上传/预览/删除，支持多种文件类型
- **系统配置** — 分组配置项，支持批量保存
- **数据字典** — 字典类型 + 字典数据，前端下拉框复用
- **个人中心** — 修改昵称/密码/头像
- **通用导出** — 任意表 CSV 导出（白名单控制）

## 快速开始

### 环境要求

- PHP >= 8.2
- Composer
- Node.js >= 16
- MySQL >= 5.7

### 1. 克隆项目

```bash
git clone https://github.com/OneZale1995/laravel-element-admin-init.git
cd laravel-element-admin-init
```

### 2. 后端安装

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

编辑 `.env` 配置数据库：

```env
DB_DATABASE=你的数据库名
DB_USERNAME=root
DB_PASSWORD=你的密码
```

初始化数据库：

```bash
php artisan migrate
php artisan db:seed
php artisan storage:link
```

默认管理员账号：**admin** / **123456**

启动后端：

```bash
php artisan serve
```

后端运行在 http://localhost:8000

### 3. 前端安装

```bash
cd frontend
npm install
npm run dev
```

前端运行在 http://localhost:9527 ，开发模式下 `/api` 请求自动代理到后端 `http://127.0.0.1:8000`。

### 4. 生产构建

```bash
cd frontend
npm run build:prod
```

构建产物在 `frontend/dist/`，部署时将 Nginx 指向此目录，API 请求反代到 Laravel。

## 项目结构

```
├── backend/                        # Laravel 后端
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/Api/    # API 控制器（12个）
│   │   │   └── Middleware/         # 操作日志中间件
│   │   ├── Models/                 # 数据模型（10个）
│   │   └── Traits/
│   │       ├── ApiResponse.php         # 统一响应格式
│   │       └── SerializeDateFormat.php # 统一时间格式 Y-m-d H:i:s
│   ├── database/
│   │   ├── migrations/             # 数据库迁移
│   │   └── seeders/                # 初始数据填充（admin 用户 + 菜单 + 权限）
│   └── routes/api.php              # API 路由（全部 POST）
│
├── frontend/                       # Vue 前端
│   ├── src/
│   │   ├── api/                    # API 接口定义
│   │   ├── views/
│   │   │   ├── dashboard/          # 首页
│   │   │   ├── login/              # 登录
│   │   │   ├── system/             # 系统管理（9个子页面）
│   │   │   └── profile/            # 个人中心
│   │   ├── layout/                 # 布局框架
│   │   ├── router/                 # 路由配置（静态 + 动态）
│   │   ├── store/                  # Vuex 状态管理
│   │   └── components/             # 公共组件（Pagination/Upload/IconPicker 等）
│   └── vue.config.js               # 开发代理 /api → localhost:8000
│
├── DEVELOPMENT.md                  # 开发规范（必读）
└── .gitignore
```

## 接口规范

> 详细规范请阅读 [DEVELOPMENT.md](DEVELOPMENT.md)

| 规则 | 说明 |
|------|------|
| 请求方式 | **所有接口统一 POST** |
| 参数传递 | JSON Body（`Content-Type: application/json`） |
| URL 格式 | `/api/{模块}/{操作}`，如 `/api/users/list`、`/api/roles/update` |
| ID 传递 | 放在 Body 中：`{ "id": 1, ... }`，URL 不带参数 |
| 响应格式 | HTTP 200 + `{ "code": 20000, "message": "success", "data": {} }` |
| 错误码 | 20000 成功 / 50000 业务错误 / 50008 登录过期 / 40000 参数校验失败 |
| 时间格式 | `Y-m-d H:i:s` |

## 数据库表

| 表名 | 说明 |
|------|------|
| admin_users | 用户（username 登录） |
| admin_roles | 角色 |
| admin_permissions | 权限节点 |
| admin_role_user | 用户-角色关联 |
| admin_permission_role | 角色-权限关联 |
| admin_menus | 动态菜单 |
| admin_operation_logs | 操作日志 |
| admin_login_logs | 登录日志 |
| admin_files | 文件记录 |
| admin_system_configs | 系统配置 |
| admin_dict_types | 字典类型 |
| admin_dict_data | 字典数据 |

## 新增模块指南

1. **后端**：新建 Controller + Model + Migration，在 `routes/api.php` 添加 POST 路由
2. **前端**：在 `src/api/` 添加接口，`src/views/` 添加页面，`src/router/` 添加路由
3. **菜单**：在后台「菜单管理」中配置，角色授权后自动显示
4. **规范**：遵循 [DEVELOPMENT.md](DEVELOPMENT.md) 中的约定

## License

MIT
