#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../admin-web"
pnpm lint
pnpm build
echo "✅ admin-web 检查通过"
