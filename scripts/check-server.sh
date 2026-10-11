#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

if grep -qi microsoft /proc/version 2>/dev/null; then
  # 已经在 WSL 里，直接跑
  export COMPOSER_ALLOW_SUPERUSER=1
  cd "$ROOT/server"
  composer test
  composer analyse
  bash "$ROOT/scripts/check-money.sh"
else
  # 本机 Windows 没有原生 PHP/Swoole，后端跑在 WSL2 里，转发过去执行
  ROOT_WIN="$(cd "$ROOT" && pwd -W 2>/dev/null || echo "$ROOT")"
  WSLROOT="/mnt/$(echo "$ROOT_WIN" | sed -E 's|^([A-Za-z]):|\L\1|')"
  echo "→ 在 WSL 里执行后端检查：$WSLROOT/server"
  wsl.exe -d Ubuntu -u root -- bash -lc "export COMPOSER_ALLOW_SUPERUSER=1 && cd '$WSLROOT/server' && composer test && composer analyse && bash '$WSLROOT/scripts/check-money.sh'"
fi
echo "✅ server 检查通过"
