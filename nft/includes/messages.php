<?php
// messages.php - 用于显示系统消息提示

// 检查是否有待显示的消息
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;

// 如果有消息则显示，并在显示后清空
if ($success_message || $error_message): 
?>
<div class="system-messages">
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-2"></i>
            <?= htmlspecialchars($success_message) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php 
        unset($_SESSION['success_message']);
    endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <?= htmlspecialchars($error_message) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php 
        unset($_SESSION['error_message']);
    endif; ?>
</div>
<?php endif; ?>

<style>
.system-messages {
    position: fixed;
    top: 80px;
    right: 20px;
    z-index: 1050;
    min-width: 300px;
    max-width: 400px;
}
.alert {
    box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
}
</style>

<script>
// 自动隐藏消息
$(document).ready(function() {
    $(".alert").delay(5000).fadeOut("slow", function() {
        $(this).alert('close');
    });
    
    // 点击关闭按钮时立即移除
    $('.alert .close').on('click', function() {
        $(this).closest('.alert').remove();
    });
});
</script>