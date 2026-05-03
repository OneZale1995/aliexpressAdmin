#!/bin/bash

set -e

cd "$(dirname "$0")"

echo "=============================="
echo " Backend Deploy"
echo "=============================="

# 拉取最新代码
echo "[1/5] Pulling latest code..."
git pull origin main

# 安装依赖
echo "[2/5] Installing composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# 执行数据库迁移
echo "[3/5] Running migrations..."
php artisan migrate --force

# 清除并重建缓存
echo "[4/5] Optimizing..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan cache:clear

# 重启队列
echo "[5/5] Restarting queue workers..."
php artisan queue:restart

echo "=============================="
echo " Deploy complete!"
echo "=============================="
