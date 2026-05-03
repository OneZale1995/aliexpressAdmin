#!/bin/bash

set -e

cd "$(dirname "$0")"

DIST_DIR="dist"
DEPLOY_BRANCH="deploy"

echo "=============================="
echo " Frontend Build & Deploy"
echo "=============================="

# 构建
echo "[1/2] Building..."
npm run build:prod

# 推送 dist 到 deploy 分支
echo "[2/2] Pushing to $DEPLOY_BRANCH branch..."
cd "$DIST_DIR"
git init
git checkout -b "$DEPLOY_BRANCH"
git add -A
git commit -m "deploy: $(date '+%Y-%m-%d %H:%M:%S')"
REMOTE_URL=$(cd .. && git remote get-url origin)
git push -f "$REMOTE_URL" "$DEPLOY_BRANCH"
rm -rf .git
cd ..

echo ""
echo "=============================="
echo " Done! dist pushed to '$DEPLOY_BRANCH' branch"
echo "=============================="
echo ""
echo "Server first time:"
echo "  git clone -b $DEPLOY_BRANCH <repo-url> /www/wwwroot/aliexpress-admin-web"
echo ""
echo "Server update:"
echo "  cd /www/wwwroot/aliexpress-admin-web && git pull"
echo ""
