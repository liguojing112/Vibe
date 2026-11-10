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
# 注意：**不要用 `--delete`**。
# 带 hash 的资源是「发版那一刻才换掉」的，但用户可能还开着上一个版本的页面，
# 它请求的老文件一旦被删就是整页白屏。所以只增量覆盖，旧文件按时间慢慢清。
if command -v rsync >/dev/null 2>&1; then
  rsync -a "$REPO/admin-web/dist/" "$WEB/"
else
  cp -a "$REPO/admin-web/dist/." "$WEB/"
fi

# 保留 7 天：足够覆盖还开着的页面，磁盘也不会无限涨。
# 站点根目录里的 404.html / download / .user.ini 等不在 static 下，不会被碰到。
find "$WEB/static" -type f -mtime +7 -delete 2>/dev/null || true
find "$WEB/static" -type d -empty -delete 2>/dev/null || true
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
