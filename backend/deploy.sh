#!/bin/bash

set -euo pipefail

cd "$(dirname "$0")"

APP_MAINTENANCE=0

clear_bootstrap_cache() {
	local cache_dir="bootstrap/cache"

	if [ -d "$cache_dir" ]; then
		echo "[pre] Removing stale bootstrap cache files..."
		rm -f "$cache_dir"/*.php
	fi
	}

cleanup() {
	if [ "$APP_MAINTENANCE" -eq 1 ]; then
		php artisan up >/dev/null 2>&1 || true
	fi
}

trap cleanup EXIT

echo "=============================="
echo " Backend Deploy"
echo "=============================="

# 开启维护模式
echo "[1/8] Enabling maintenance mode..."
php artisan down --retry=30 || true
APP_MAINTENANCE=1

# 拉取最新代码
echo "[2/8] Pulling latest code..."
git pull --ff-only origin main

# composer install 会触发 artisan package:discover，先删掉旧缓存文件避免读取旧配置
clear_bootstrap_cache

# 安装依赖
echo "[3/8] Installing composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# 清除旧缓存，确保后续 artisan 命令读取到最新配置
echo "[4/8] Clearing stale caches..."
php artisan optimize:clear

# 执行数据库迁移
echo "[5/8] Running migrations..."
php artisan migrate --force

# 重建缓存
echo "[6/8] Rebuilding caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 确保 storage 链接存在
echo "[7/8] Ensuring storage link..."
php artisan storage:link >/dev/null 2>&1 || true

# 重启队列
echo "[8/8] Restarting queue workers..."
php artisan queue:restart

php artisan up
APP_MAINTENANCE=0
trap - EXIT

echo "=============================="
echo " Deploy complete!"
echo "=============================="
