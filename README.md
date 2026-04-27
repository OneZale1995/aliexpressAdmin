# AliExpress Admin

基于 Laravel 12 + Vue 2 + Element UI 的速卖通订单履约后台。

项目定位不是通用后台模板，而是围绕 订单同步、物流履约、团队协同 的业务系统。

## 核心能力

- 订单中心
	- 订单列表、筛选、状态看板
	- 订单备注与后台状态维护
	- 订单导出
- 订单统计
	- 日/月统计
	- 单量与实发单量统计
	- 利润统计
- 订单同步
	- 手动触发同步任务
	- 异步任务执行与进度查询
- 物流履约
	- 通用发货、面单打印、交接单
	- DBS 状态同步（in_transit / ready_for_pickup / delivered）
	- FBS 工作流（物流单、交接单、交接确认）
	- 第三方物流渠道：ChinaPost、SZ56T（雷翼）
- 组织协作
	- 团队管理、采购用户管理
	- 店铺管理
- 系统治理
	- 用户、角色、权限、菜单
	- 数据字典、系统配置、文件管理
	- 登录日志、操作日志

## 技术栈

| 层级 | 技术 |
| --- | --- |
| 后端 | Laravel 12, Sanctum |
| 前端 | Vue 2, vue-element-admin, Element UI |
| 数据库 | MySQL 5.7+ |
| 异步任务 | Laravel Queue + Job |

## 目录说明

```text
backend/
	app/
		Http/Controllers/Api/   # API 控制器
		Services/               # 平台与物流服务封装
		Jobs/                   # 异步任务（订单同步等）
		Models/                 # 业务模型
	routes/api.php            # 后端 API 路由

frontend/
	src/
		api/                    # 前端接口定义
		views/order/            # 订单与履约页面
		views/system/           # 系统管理页面
		views/team/             # 团队管理页面
		views/shop/             # 店铺管理页面
		router/index.js         # 路由与权限入口

DEVELOPMENT.md              # 开发约定（强约束）
```

## 本地开发

### 1. 环境要求

- PHP >= 8.2
- Composer
- Node.js >= 16
- MySQL >= 5.7

### 2. 启动后端

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve
```

后端默认地址: http://127.0.0.1:8000

### 3. 启动前端

```bash
cd frontend
npm install
npm run dev
```

前端默认地址: http://localhost:9527

开发环境下 /api 会代理到后端服务。

## 任务与调度

- 手动同步订单会创建任务并交由队列执行
- 相关实现位于 backend/app/Jobs 和 backend/app/Services
- 如需自动同步，可结合定时任务执行命令:

```bash
php artisan order:auto-sync
```

## API 约定

项目统一遵循以下规则:

- 所有业务接口统一使用 POST
- 参数统一放在 JSON Body
- 返回结构统一为 code/message/data

详细约定见 DEVELOPMENT.md。

## 默认账号

- 用户名: admin
- 密码: 123456

## 生产构建

```bash
cd frontend
npm run build:prod
```

构建产物位于 frontend/dist。

## License

MIT
