<?php
// 旧"编辑区块信息"页：重定向到合一的区块管理页
$id = intval($_GET['id'] ?? 0);
header("Location: manage.php?id=" . $id);
exit();
