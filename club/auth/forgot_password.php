<?php require_once '../includes/header.php'; ?>
<style>
.container { max-width:500px; margin:60px auto; padding:20px; }
.card { background:white; border-radius:12px; padding:30px; box-shadow:0 4px 20px rgba(0,0,0,0.1); text-align:center; }
.card h2 { margin-bottom:15px; color:#333; }
.card p { color:#666; margin-bottom:20px; font-size:14px; }
.form-input { width:100%; padding:12px; border:1px solid #ddd; border-radius:6px; font-size:14px; margin-bottom:15px; }
.btn { width:100%; padding:12px; background:#ff6b00; color:white; border:none; border-radius:6px; font-size:16px; cursor:pointer; font-weight:bold; }
</style>
<div class="container">
    <div class="card">
        <h2><i class="fas fa-key"></i> 找回密码</h2>
        <p>请输入注册邮箱，我们将发送重置链接</p>
        <form method="POST">
            <input type="email" name="email" class="form-input" placeholder="注册邮箱" required>
            <button type="submit" class="btn">发送重置链接</button>
        </form>
        <div style="margin-top:15px"><a href="login.php" style="color:#3498db;">返回登录</a></div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
