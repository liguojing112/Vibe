#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../android"
./gradlew testDebugUnitTest lintDebug assembleDebug
bash ../scripts/check-ui.sh
echo "✅ android 检查通过"
