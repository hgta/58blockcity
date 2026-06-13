<?php
/**
 * 同步现有管理员用户到 admin_assignments 表
 * 执行方式: php init/sync_admin_users.php
 */

require_once __DIR__ . '/../config/database.php';

echo "=== 同步管理员用户 ===\n\n";

// 1. 检查 admin_assignments 表是否存在
$stmt = $pdo->query("SHOW TABLES LIKE 'admin_assignments'");
if ($stmt->rowCount() == 0) {
    echo "错误: admin_assignments 表不存在，请先执行 admin_tables.sql\n";
    exit(1);
}

// 2. 查找 users 表中 role='admin' 或 id=1 的用户
$stmt = $pdo->query("SELECT id, username, role FROM users WHERE role = 'admin' OR id = 1 ORDER BY id");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "找到 " . count($admins) . " 个管理员用户:\n";

$inserted = 0;
$skipped = 0;

foreach ($admins as $admin) {
    $roleKey = ($admin['id'] == 1) ? 'super_admin' : 'admin';
    $siteScope = 'all';

    // 检查是否已存在
    $check = $pdo->prepare("SELECT id FROM admin_assignments WHERE user_id = ? AND site_scope = 'all'");
    $check->execute([$admin['id']]);

    if ($check->fetch()) {
        echo "  - #{$admin['id']} {$admin['username']}: 已存在，跳过\n";
        $skipped++;
        continue;
    }

    // 插入
    $insert = $pdo->prepare("INSERT INTO admin_assignments (user_id, role_key, site_scope) VALUES (?, ?, ?)");
    $insert->execute([$admin['id'], $roleKey, $siteScope]);
    echo "  - #{$admin['id']} {$admin['username']}: 已同步为 {$roleKey}\n";
    $inserted++;
}

echo "\n结果: 插入 {$inserted} 条，跳过 {$skipped} 条\n";
echo "同步完成!\n";
