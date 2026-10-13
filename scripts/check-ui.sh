#!/usr/bin/env bash
set -euo pipefail
shopt -s nullglob
ROOT="$(dirname "$0")/../android/app/src/main/java/com/qichuang/changliao"
FEATURE="$ROOT/feature"
DS="$ROOT/core/designsystem"
FAIL=0

# 1. feature 里禁止出现的写法
PATTERN='Color\(|\.dp([^a-zA-Z]|$)|\.sp([^a-zA-Z]|$)|FontWeight\.|RoundedCornerShape\(|MaterialTheme\.|material3\.Button([^a-zA-Z]|$)|(^|[^A-Za-z])TextField\(|OutlinedTextField\(|AlertDialog\(|ModalBottomSheet\(|Snackbar\('
if [ -d "$FEATURE" ]; then
  HITS=$(grep -rnE "$PATTERN" "$FEATURE" || true)
  if [ -n "$HITS" ]; then echo "❌ feature 中出现硬编码样式或原生组件："; echo "$HITS"; FAIL=1; fi
fi

# 2. 每个公共组件都必须出现在组件预览页
GALLERY=$(find "$ROOT" -name 'DesignSystemGalleryScreen.kt' 2>/dev/null | head -1)
if [ -z "$GALLERY" ]; then echo "❌ 缺少 DesignSystemGalleryScreen.kt"; FAIL=1; else
  for f in "$DS"/component/*.kt; do
    name=$(basename "$f" .kt)
    grep -q "$name" "$GALLERY" || { echo "❌ 组件 $name 没有加到组件预览页"; FAIL=1; }
  done
fi

# 3. 页面里禁止直接写中文价格单位拼接（价格文案必须来自服务端或 MoneyFormat）
if [ -d "$FEATURE" ]; then
  HITS2=$(grep -rnE '"[^"]*币/条' "$FEATURE" || true)
  if [ -n "$HITS2" ]; then echo "❌ feature 中手写了价格文案，请用 MoneyFormat："; echo "$HITS2"; FAIL=1; fi
fi

# 4. rememberClToast() 必须写在 ClScaffold **里面**
#    Toast 的宿主（ClToastHost）是 ClScaffold 提供的，写在它外面拿到的是兜底实例 ——
#    编译、单测、lint 全都不报错，但那个页面的 Toast 永远不会显示（T5.4 起就悄悄错了）。
if [ -d "$FEATURE" ]; then
  while IFS= read -r f; do
    [ -z "$f" ] && continue
    scaffold_line=$(grep -n "ClScaffold(" "$f" | head -1 | cut -d: -f1)
    toast_line=$(grep -n "rememberClToast()" "$f" | head -1 | cut -d: -f1)
    if [ -n "$scaffold_line" ] && [ -n "$toast_line" ] && [ "$toast_line" -lt "$scaffold_line" ]; then
      echo "❌ $f 第 $toast_line 行：rememberClToast() 写在 ClScaffold 外面，Toast 不会显示"
      FAIL=1
    fi
  done < <(grep -rl "rememberClToast()" "$FEATURE" || true)
fi

[ $FAIL -eq 0 ] && echo "✅ UI 检查通过" || exit 1
