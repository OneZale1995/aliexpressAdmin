#!/bin/bash

set -euo pipefail

# ============================================================
#  AliExpress Admin 一键部署脚本
#  用法: bash deploy.sh [backend|frontend|all]
#  默认: all（前后端都更新）
# ============================================================

# ---------- 配置区 ----------
BACKEND_DIR="/www/wwwroot/aliexpress-admin-api"
FRONTEND_DIR="/www/wwwroot/aliexpress-admin-web"
PHP_BIN="php"
COMPOSER_BIN="composer"
GIT_BRANCH="main"
# ----------------------------

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log()   { echo -e "${GREEN}[$(date '+%H:%M:%S')] $1${NC}"; }
warn()  { echo -e "${YELLOW}[$(date '+%H:%M:%S')] $1${NC}"; }
fail()  { echo -e "${RED}[$(date '+%H:%M:%S')] $1${NC}"; exit 1; }

BACKEND_MAINTENANCE=0

clear_backend_bootstrap_cache() {
    local cache_dir="$BACKEND_DIR/bootstrap/cache"

    if [ -d "$cache_dir" ]; then
        log "清理 bootstrap/cache 下旧缓存文件..."
        rm -f "$cache_dir"/*.php
    fi
}

cleanup() {
    if [ "${BACKEND_MAINTENANCE:-0}" -eq 1 ] && [ -d "${BACKEND_DIR:-}" ]; then
        warn "部署未完成，正在关闭维护模式..."
        cd "$BACKEND_DIR" 2>/dev/null || return
        $PHP_BIN artisan up >/dev/null 2>&1 || true
    fi
}

trap cleanup EXIT

deploy_backend() {
    echo ""
    echo "============================================"
    log "开始更新后端"
    echo "============================================"

    [ -d "$BACKEND_DIR" ] || fail "后端目录不存在: $BACKEND_DIR"
    cd "$BACKEND_DIR"

    # 开启维护模式
    log "开启维护模式..."
    $PHP_BIN artisan down --retry=30 || true
    BACKEND_MAINTENANCE=1

    # 拉取最新代码
    log "拉取最新代码..."
    git pull --ff-only origin "$GIT_BRANCH"

    # composer install 会触发 artisan package:discover，先删掉旧缓存文件避免读取旧配置
    clear_backend_bootstrap_cache

    # 安装依赖
    log "安装 Composer 依赖..."
    $COMPOSER_BIN install --no-dev --optimize-autoloader --no-interaction

    # 先清旧缓存，确保迁移和后续命令读取到最新配置
    log "清理旧缓存..."
    $PHP_BIN artisan optimize:clear

    # 数据库迁移
    log "执行数据库迁移..."
    $PHP_BIN artisan migrate --force

    # 重建缓存
    log "重建缓存..."
    $PHP_BIN artisan config:cache
    $PHP_BIN artisan route:cache
    $PHP_BIN artisan view:cache
    $PHP_BIN artisan event:cache

    # 确保 storage 链接存在
    $PHP_BIN artisan storage:link 2>/dev/null || true

    # 重启队列 Worker（让 worker 平滑重启，跑完当前任务后加载新代码）
    log "重启队列 Worker..."
    $PHP_BIN artisan queue:restart

    # 关闭维护模式
    log "关闭维护模式..."
    $PHP_BIN artisan up
    BACKEND_MAINTENANCE=0

    log "后端更新完成"
}

deploy_frontend() {
    echo ""
    echo "============================================"
    log "开始更新前端"
    echo "============================================"

    [ -d "$FRONTEND_DIR" ] || fail "前端目录不存在: $FRONTEND_DIR"
    cd "$FRONTEND_DIR"

    log "拉取最新代码..."
    git pull --ff-only origin "$GIT_BRANCH"

    log "前端更新完成（静态文件，即拉即生效）"
}

# ---------- 入口 ----------
TARGET="${1:-all}"

echo ""
echo "============================================"
log "AliExpress Admin 部署开始"
log "目标: $TARGET"
echo "============================================"

START_TIME=$(date +%s)

case "$TARGET" in
    backend)  deploy_backend ;;
    frontend) deploy_frontend ;;
    all)      deploy_backend; deploy_frontend ;;
    *)        fail "参数错误，可选: backend | frontend | all" ;;
esac

END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))

echo ""
echo "============================================"
log "全部完成，耗时 ${DURATION} 秒"
echo "============================================"
