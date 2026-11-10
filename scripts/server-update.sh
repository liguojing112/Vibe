#!/usr/bin/env bash
#
# 生产服务器的一键部署脚本（放在服务器上，不进 CI）。
#
# 服务器上的位置：/www/wwwroot/cljy/update.sh
# 用法：本地 git push 之后，在服务器执行 `bash /www/wwwroot/cljy/update.sh`
#
# 目录约定：
#   /www/wwwroot/cljy                  git 仓库（来自 GitHub）
#   /www/wwwroot/admin.cyjiaoyou.lol   管理后台静态站点（Nginx root）
#
# 几个踩过的坑（别改回去）：
#   1. `composer install` 不能加 `--no-dev` —— `migrate` / `db:seed` 由 hyperf/devtool 提供，
#      而它在 require-dev 里，去掉就没命令了。
#   2. 进程名是 **MineAdmin.Master / MineAdmin.Worker.N**，`pkill -f hyperf.php` 杀不到，
#      端口会一直被占，重启就报 Address already in use。
#   3. 后备启动必须用 **www 身份 + umask 022**：以 root 启动会让 runtime/ 和 storage/uploads/
#      变成 root 属主，宝塔守护（www）之后写不进去、重启直接 FAILED。
#   4. `composer install` 会清 runtime/container（composer.json 的 post-autoload-dump），
#      冷缓存第一次注解路由是空的 —— 所以冒烟不通要再起一次。
set -euo pipefail

REPO=/www/wwwroot/cljy
WEB=/www/wwwroot/admin.cyjiaoyou.lol

cd "$REPO"
# WSL/跨系统挂载的目录 git 会报 dubious ownership
git config --global --add safe.directory "$REPO" 2>/dev/null || true

echo "==> 1/6 拉取代码"
git pull --ff-only

echo "==> 2/6 后端依赖"
cd "$REPO/server"
COMPOSER_ALLOW_SUPERUSER=1 composer install --optimize-autoloader

echo "==> 3/6 数据库迁移"
php bin/hyperf.php migrate --force

echo "==> 4/6 前端构建"
cd "$REPO/admin-web"
pnpm install --frozen-lockfile
pnpm build

echo "==> 5/6 发布前端"
# 站点根目录里有几样**不是构建产物**的东西，必须保留：
#   404.html、download/（APK 下载页）、.htaccess、.user.ini（宝塔/PHP 配置）、.well-known（证书验证）
# `--delete` 会把它们一起删掉，所以统一排除。
KEEP_PATTERNS='^(\.user\.ini|\.htaccess|404\.html|download/|\.well-known/)'
if command -v rsync >/dev/null 2>&1; then
  rsync -a --delete \
    --exclude='/.user.ini' --exclude='/.htaccess' --exclude='/404.html' \
    --exclude='/download' --exclude='/.well-known' \
    "$REPO/admin-web/dist/" "$WEB/"
else
  # 没有 rsync 时 `cp -a` 不会删旧文件 —— 之前就是这样堆了几千个带 hash 的 JS。
  # 这里按「新产物清单」反向清理一次，规则和 rsync 的 --delete 对齐。
  cp -a "$REPO/admin-web/dist/." "$WEB/"

  NEW_LIST=/tmp/cl-admin-dist-files.txt
  OLD_LIST=/tmp/cl-admin-web-files.txt
  (cd "$REPO/admin-web/dist" && find . -type f | sed 's|^\./||' | sort) > "$NEW_LIST"
  (cd "$WEB" && find . -type f | sed 's|^\./||' | sort) > "$OLD_LIST"

  comm -13 "$NEW_LIST" "$OLD_LIST" | grep -vE "$KEEP_PATTERNS" | while IFS= read -r stale; do
    rm -f -- "$WEB/$stale"
  done
fi
chown -R www:www "$REPO/server/runtime" "$REPO/server/storage" "$WEB" 2>/dev/null || true

echo "==> 6/6 重启后端"
pkill -9 -f "MineAdmin" >/dev/null 2>&1 || true
sleep 8

if ! curl -fsS -m 5 http://127.0.0.1:9501/api/v1/app/config >/dev/null 2>&1; then
  echo "    守护没拉起来，手动启动（www 身份 + umask 022）"
  su -s /bin/sh www -c "cd '$REPO/server' && umask 022 && setsid nohup php bin/hyperf.php start > /www/wwwlogs/hyperf.log 2>&1 < /dev/null &"
  sleep 6
fi

echo "==> 冒烟"
curl -fsS -m 5 http://127.0.0.1:9501/api/v1/app/config | head -c 80
echo
echo "✅ 部署完成"
