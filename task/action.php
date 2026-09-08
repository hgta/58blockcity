<?php
/**
 * 任务广场动作处理器（统一 POST 端点）
 * 领取 / 提交凭证 / 验收(通过·驳回) / 关闭任务 / 重试结算 / 发起争议 / 评价
 * 所有动作：登录 + CSRF + 归属校验在数据类内完成，处理完成后 flash + 跳回。
 */
require_once '../config/database.php';
require_once '../classes/Task.php';
require_once '../classes/TaskClaim.php';
require_once '../classes/TaskReview.php';
require_once '../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// 登录（数据类内还有二次校验）
if (!isLoggedIn()) {
    setFlashMessage('error', '请先登录');
    header('Location: auth/login.php');
    exit;
}
validateCsrfToken();

$uid    = (int)$_SESSION['user_id'];
$action = (string)($_POST['action'] ?? '');
$task   = new Task($pdo);
$tc     = new TaskClaim($pdo);

/** 回到任务详情 */
function backToTask($taskId) {
    header('Location: view.php?id=' . (int)$taskId);
    exit;
}

switch ($action) {
    // ---------- 领取 ----------
    case 'claim': {
        $taskId = (int)($_POST['task_id'] ?? 0);
        [$ok, $msg] = $tc->claim($taskId, $uid);
        setFlashMessage($ok ? 'success' : 'error', $msg);
        header('Location: view.php?id=' . $taskId);
        exit;
    }

    // ---------- 提交交付凭证（文字 + 可选图片） ----------
    case 'submit': {
        $claimId   = (int)($_POST['claim_id'] ?? 0);
        $proofText = trim((string)($_POST['proof_text'] ?? ''));
        $imagePath = '';

        if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg', 'image/png', 'image/gif'];
            $finfo   = new finfo(FILEINFO_MIME_TYPE);
            $mime    = $finfo->file($_FILES['proof_image']['tmp_name']);
            if (!in_array($mime, $allowed, true)) {
                setFlashMessage('error', '凭证图片只支持 JPG / PNG / GIF');
                header('Location: view.php?id=' . (int)($_POST['task_id'] ?? 0));
                exit;
            }
            if ($_FILES['proof_image']['size'] > 8 * 1024 * 1024) {
                setFlashMessage('error', '凭证图片不能超过 8MB');
                header('Location: view.php?id=' . (int)($_POST['task_id'] ?? 0));
                exit;
            }
            $uploadDir = __DIR__ . '/uploads/task_proofs/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $ext        = strtolower(pathinfo($_FILES['proof_image']['name'], PATHINFO_EXTENSION));
            $fileName   = 'proof_' . uniqid() . '.' . $ext;
            $uploadPath = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['proof_image']['tmp_name'], $uploadPath)) {
                $imagePath = compressImage($uploadPath, $uploadDir, $fileName, 1600);
                // 转成相对 task 根目录路径（/uploads/task_proofs/xxx.jpg），供 DB 存储与页面 / 前缀展示
                $imagePath = ltrim(str_replace(__DIR__ . '/', '', $imagePath), '/');
            } else {
                setFlashMessage('error', '凭证图片上传失败');
                header('Location: view.php?id=' . (int)($_POST['task_id'] ?? 0));
                exit;
            }
        }

        [$ok, $msg] = $tc->submitProof($claimId, $uid, $proofText, $imagePath);
        setFlashMessage($ok ? 'success' : 'error', $msg);
        header('Location: view.php?id=' . (int)($_POST['task_id'] ?? 0));
        exit;
    }

    // ---------- 雇主验收（通过 / 驳回） ----------
    case 'review': {
        $claimId = (int)($_POST['claim_id'] ?? 0);
        $note    = trim((string)($_POST['employer_note'] ?? ''));
        $claim   = $tc->getClaim($claimId);
        $taskId  = $claim ? (int)$claim['task_id'] : 0;
        [$ok, $msg] = $tc->review($claimId, $uid, (string)($_POST['decision'] ?? ''), $note);
        setFlashMessage($ok ? 'success' : 'error', $msg);
        backToTask($taskId);
    }

    // ---------- 雇主关闭任务 ----------
    case 'close': {
        $taskId = (int)($_POST['task_id'] ?? 0);
        [$ok, $msg] = $task->closeByEmployer($taskId, $uid);
        setFlashMessage($ok ? 'success' : 'error', $msg);
        header('Location: my.php?tab=publish');
        exit;
    }

    // ---------- 重试结算（settling 且雇主） ----------
    case 'settle': {
        $claimId = (int)($_POST['claim_id'] ?? 0);
        $claim   = $tc->getClaim($claimId);
        if (!$claim || (int)$claim['employer_id'] !== $uid) {
            setFlashMessage('error', '无权操作');
            backToTask($claim ? (int)$claim['task_id'] : 0);
        }
        [$ok, $msg] = $tc->settleOnce($claimId);
        setFlashMessage($ok ? 'success' : 'error', $msg);
        backToTask((int)$claim['task_id']);
    }

    // ---------- 发起争议 ----------
    case 'dispute': {
        $claimId = (int)($_POST['claim_id'] ?? 0);
        $reason  = trim((string)($_POST['reason'] ?? ''));
        [$ok, $msg] = $tc->openDispute($claimId, $uid, $reason);
        setFlashMessage($ok ? 'success' : 'error', $msg);
        header('Location: my.php');
        exit;
    }

    // ---------- 双向评价 ----------
    case 'review_add': {
        $claimId = (int)($_POST['claim_id'] ?? 0);
        $toUser  = (int)($_POST['to_user_id'] ?? 0);
        $rating  = (int)($_POST['rating'] ?? 0);
        $content = trim((string)($_POST['content'] ?? ''));
        $claim   = $tc->getClaim($claimId);
        if (!$claim) {
            setFlashMessage('error', '认领不存在');
            backToTask(0);
        }
        $rv = new TaskReview($pdo);
        [$ok, $msg] = $rv->add((int)$claim['task_id'], $claimId, $uid, $toUser, $rating, $content);
        setFlashMessage($ok ? 'success' : 'error', $msg);
        backToTask((int)$claim['task_id']);
    }

    default:
        setFlashMessage('error', '未知操作');
        header('Location: index.php');
        exit;
}
