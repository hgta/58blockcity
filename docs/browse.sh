#!/bin/bash
# BlockCity 浏览器工具 - 快捷脚本
# 用法: bash docs/browse.sh <url>
# 例: bash docs/browse.sh https://www.blockcity.vip/

export PATH="/Users/liangshishi/.workbuddy/binaries/node/versions/20.18.0/bin:$PATH"
export PLAYWRIGHT_BROWSERS_PATH="/Users/liangshishi/Library/Caches/ms-playwright"
PCLI="/Users/liangshishi/.browser-tools/node_modules/.bin/playwright-cli"

if [ ! -f "$PCLI" ]; then
  mkdir -p /Users/liangshishi/.browser-tools
  cd /Users/liangshishi/.browser-tools
  npm init -y
  npm install @playwright/cli@latest
  cd -
fi

URL="${1:-https://www.blockcity.vip/}"
echo "Opening: $URL"
$PCLI open "$URL" && sleep 3 && $PCLI eval "document.body.innerText"
echo ""
echo "Browser still open. Use: $PCLI close"
