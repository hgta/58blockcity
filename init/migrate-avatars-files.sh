#!/bin/bash
# ============================================================
# unify-user-avatar：存量头像文件一次性迁移（幂等，可重复执行）
# 作用：把 bct / hufang / mall 三处旧目录的头像文件复制到
#       主仓库 assets/images/uploads/avatars/（线上 58.tl/assets/images/uploads/avatars/）
# 用法：在服务器仓库根目录执行  bash init/migrate-avatars-files.sh
#       （若线上仓库路径与脚本所在目录不同，先 cd 到仓库根）
# 说明：cp -n（no-clobber）保证不覆盖已有同名文件，重复执行结果不变。
#       旧目录保留一段时间作备份，确认线上无 404 后再手动清理（不进代码库）。
# ============================================================
set -euo pipefail
cd "$(dirname "$0")/.."   # 切到仓库根

MAIN="assets/images/uploads/avatars"
mkdir -p "$MAIN"

copied=0
for d in bct hufang mall; do
  case "$d" in
    bct|hufang) SRC="$d/assets/images/uploads/avatars" ;;
    mall)       SRC="$d/assets/uploads/avatars" ;;
  esac
  if [ -d "$SRC" ]; then
    n=$(ls -1 "$SRC" 2>/dev/null | wc -l | tr -d ' ')
    if [ "$n" -gt 0 ]; then
      cp -n "$SRC/"* "$MAIN/" 2>/dev/null || true
      copied=$((copied + n))
      echo "已从 $SRC 复制 $n 个文件"
    else
      echo "跳过（空目录）: $SRC"
    fi
  else
    echo "跳过（不存在）: $SRC"
  fi
done

echo "完成：共尝试复制 $copied 个文件到 $MAIN/（同名未覆盖）"
echo "下一步：执行 SQL 归一化  mysql 库名 < init/migrate-avatar.sql"
