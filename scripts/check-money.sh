#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../server/app"
# 只有这些文件可以直接写钱包表
ALLOW='Service/Cl/Wallet/CoinWalletService.php|Service/Cl/Wallet/PointWalletService.php|Repository/Cl/CoinWalletRepository.php|Repository/Cl/PointWalletRepository.php|Model/Cl/CoinWallet.php|Model/Cl/PointWallet.php'
HITS=$(grep -rnE "cl_(coin|point)_wallet|CoinWallet::|PointWallet::|CoinWalletRepository|PointWalletRepository" . 2>/dev/null | grep -vE "$ALLOW" || true)
if [ -n "$HITS" ]; then
  echo "❌ 以下位置绕过了钱包服务："; echo "$HITS"; exit 1
fi
# 禁止用浮点算钱
FLOAT=$(grep -rnE "\(float\)|floatval\(|round\(" Service/Cl/Billing Service/Cl/Wallet Service/Cl/Withdraw 2>/dev/null || true)
if [ -n "$FLOAT" ]; then
  echo "❌ 资金代码里出现浮点运算："; echo "$FLOAT"; exit 1
fi
echo "✅ money 检查通过"
