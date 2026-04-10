# 开发规范

## 一、API 接口规范

### 1. 请求方式
- **所有接口统一使用 POST 方法**，无任何例外
- **所有参数通过 JSON Body 传输**（`Content-Type: application/json`）
- **URL 中不携带任何参数**：不使用路径参数 `{id}`，不使用查询参数 `?key=value`
- 唯一例外：文件上传使用 `multipart/form-data`

### 2. URL 命名规范
```
POST /api/{模块名}/{操作}
```

| 操作 | 命名 | 示例 |
|------|------|------|
| 列表 | list | `POST /api/users/list` |
| 新增 | create | `POST /api/users/create` |
| 详情 | detail | `POST /api/users/detail` |
| 更新 | update | `POST /api/users/update` |
| 删除 | delete | `POST /api/users/delete` |

### 3. 请求参数示例
```json
// 列表查询
{ "page": 1, "limit": 20, "username": "admin" }

// 更新（ID 放在 body 中）
{ "id": 1, "username": "admin", "nickname": "管理员" }

// 删除
{ "id": 1 }
```

### 4. 响应格式
所有接口统一返回 HTTP 200，通过 `code` 区分成功/失败：
```json
// 成功
{ "code": 20000, "message": "success", "data": {} }

// 失败
{ "code": 50000, "message": "错误信息", "data": null }
```

### 5. 时间格式
所有时间字段统一格式：`YYYY-MM-DD HH:mm:ss`（如 `2026-04-10 14:30:00`）

---

## 二、后端规范（Laravel）

### 1. 路由定义
```php
// ✅ 正确：统一 POST，操作写在 URL 中
Route::post('/users/list', [UserController::class, 'index']);
Route::post('/users/create', [UserController::class, 'store']);
Route::post('/users/update', [UserController::class, 'update']);
Route::post('/users/delete', [UserController::class, 'destroy']);

// ❌ 禁止：RESTful 风格、路径参数
Route::get('/users', ...);
Route::put('/users/{id}', ...);
Route::delete('/users/{id}', ...);
Route::apiResource('users', ...);
```

### 2. 控制器
- **不使用路由模型绑定**（Route Model Binding）
- 通过 `$request->id` 获取 ID，使用 `Model::findOrFail()` 查找
- 使用 `ApiResponse` trait 统一返回格式
```php
// ✅ 正确
public function update(Request $request)
{
    $user = User::findOrFail($request->id);
    // ...
    return $this->success($user);
}

// ❌ 禁止
public function update(Request $request, User $user) { ... }
```

### 3. Model
- 所有 Model 必须使用 `SerializeDateFormat` trait，确保时间格式统一
```php
class User extends Authenticatable
{
    use SerializeDateFormat;
}
```

### 4. 目录结构
```
backend/
├── app/
│   ├── Http/Controllers/Api/    # 所有 API 控制器
│   ├── Models/                  # 数据模型
│   └── Traits/
│       ├── ApiResponse.php      # 统一响应格式
│       └── SerializeDateFormat.php  # 统一时间格式
├── routes/
│   └── api.php                  # 所有 API 路由
└── database/
    ├── migrations/              # 数据库迁移
    └── seeders/                 # 数据填充
```

---

## 三、前端规范（Vue + Element UI）

### 1. API 调用
- 所有请求使用 `method: 'post'`
- 参数通过 `data` 传递，不使用 `params`
- ID 合并到 data 中传递
```js
// ✅ 正确
export function updateUser(id, data) {
  return request({ url: '/users/update', method: 'post', data: { id, ...data } })
}

// ❌ 禁止
export function updateUser(id, data) {
  return request({ url: `/users/${id}`, method: 'put', data })
}
```

### 2. 语言
- Element UI 使用中文语言包（`zh-CN`）
- 所有界面文案使用中文

### 3. 目录结构
```
frontend/src/
├── api/
│   ├── user.js        # 登录/登出/用户信息
│   └── system.js      # 所有业务模块 API
├── views/
│   ├── dashboard/     # 首页
│   ├── login/         # 登录页
│   ├── profile/       # 个人中心
│   └── system/        # 系统管理模块
├── store/modules/     # Vuex 状态管理
├── router/            # 路由配置
└── utils/             # 工具函数
```

---

## 四、数据库规范

### 1. 认证方式
- 使用 `username` 字段登录（非邮箱）
- 密码最少 6 位
- Token 认证（Laravel Sanctum）

### 2. 命名规范
- 表名：小写复数（`users`、`roles`、`operation_logs`）
- 字段名：snake_case（`created_at`、`user_name`）
- 外键：`{关联表单数}_id`（`user_id`、`role_id`）

### 3. 通用字段
- `status`：状态字段，1=启用，0=禁用
- `sort`：排序字段，数值越小越靠前
- `created_at` / `updated_at`：Laravel 自动维护
