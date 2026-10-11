#!/usr/bin/env bash
set -euo pipefail
DIR="$(cd "$(dirname "$0")" && pwd)"
bash "$DIR/check-server.sh"
bash "$DIR/check-admin.sh"
bash "$DIR/check-android.sh"
echo "✅✅✅ 全部检查通过"
