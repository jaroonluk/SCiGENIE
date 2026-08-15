<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (Auth::check()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim((string) ($_POST['user'] ?? ''));
    $pass = (string) ($_POST['pass'] ?? '');
    if (Auth::login($user, $pass)) {
        header('Location: index.php');
        exit;
    }
    $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
}
$base = app_base_path();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>เข้าสู่ระบบผู้จัดการ | SCiGENIE</title>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/css/admin.css">
</head>
<body>
  <div class="login-wrap">
    <form class="login-card" method="post">
      <div class="login-mark">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      </div>
      <h1>ผู้จัดการระบบ</h1>
      <p class="hint">เพิ่มคำถามและคีย์เวิร์ดเข้าคลัง SCiGENIE คณะวิทยาศาสตร์ มข.</p>
      <?php if ($error): ?><div class="alert err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <label>ชื่อผู้ใช้</label>
      <input name="user" required autocomplete="username" value="admin">
      <label>รหัสผ่าน</label>
      <input name="pass" type="password" required autocomplete="current-password">
      <button class="btn full" type="submit">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" x2="3" y1="12" y2="12"/></svg>
        เข้าสู่ระบบ
      </button>
      <p class="hint" style="margin-top:14px;">ค่าเริ่มต้น: <code>admin</code> / <code>SciGenie@2569</code></p>
      <p class="hint"><a href="<?= htmlspecialchars($base) ?>/index.php">กลับหน้าเว็บคณะ</a></p>
    </form>
  </div>
</body>
</html>
