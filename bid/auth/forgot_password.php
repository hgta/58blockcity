<?php require_once '../includes/header.php'; ?>
<style>
.ac-auth-wrap { max-width: 460px; margin: 56px auto; padding: 0 15px; }
.ac-auth-card { background: var(--surface); border: 1px solid var(--line); border-radius: 16px; padding: 30px; box-shadow: var(--shadow); text-align: center; }
.ac-auth-card h2 { margin: 0 0 14px; color: var(--text); font-size: 20px; }
.ac-auth-card p { color: var(--muted); margin: 0 0 20px; font-size: 14px; }
.ac-auth-input { width: 100%; padding: 12px; border: 1px solid var(--line-strong); border-radius: 10px; font-size: 14px; margin-bottom: 15px; background: var(--inset); color: var(--text); }
.ac-auth-input:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-dim); }
.ac-auth-btn { width: 100%; padding: 12px; background: var(--brand); color: var(--on-brand); border: none; border-radius: 10px; font-size: 16px; cursor: pointer; font-weight: 700; }
.ac-auth-btn:hover { filter: brightness(1.06); }
.ac-auth-back { margin-top: 15px; }
.ac-auth-back a { color: var(--brand); text-decoration: none; }
</style>
<div class="ac-auth-wrap">
    <div class="ac-auth-card">
        <h2><i class="fas fa-key"></i> 找回密码</h2>
        <p>请输入注册邮箱，我们将发送重置链接</p>
        <form method="POST">
            <input type="email" name="email" class="ac-auth-input" placeholder="注册邮箱" required>
            <button type="submit" class="ac-auth-btn">发送重置链接</button>
        </form>
        <div class="ac-auth-back"><a href="login.php">返回登录</a></div>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
