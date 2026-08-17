<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
if (preg_match('/^(127\.0\.0\.1|\[::1\])(:\d+)?$/', $host, $m)) {
    $port = $m[2] ?? '';
    header('Location: http://localhost' . $port . ($_SERVER['REQUEST_URI'] ?? '/admin/login.php'));
    exit;
}

if (Auth::check()) {
    header('Location: index.php');
    exit;
}

$messages = [
    'denied' => 'ยกเลิกการเข้าสู่ระบบด้วย Google',
    'missing_code' => 'ไม่พบรหัสยืนยันจาก Google กรุณาลองใหม่',
    'invalid_state' => 'เซสชันหมดอายุหรือไม่ถูกต้อง กรุณาเข้าสู่ระบบอีกครั้ง',
    'token_failed' => 'ยืนยันบัญชี Google ไม่สำเร็จ กรุณาลองใหม่',
    'userinfo_failed' => 'ดึงข้อมูลบัญชี Google ไม่สำเร็จ กรุณาลองใหม่',
    'email_unverified' => 'อีเมล Google นี้ยังไม่ได้ยืนยัน',
    'not_in_eoffice' => 'ไม่พบบุคลากรนี้ในระบบ eoffice',
    'eoffice_inactive' => 'สถานะบุคลากรนี้ไม่สามารถเข้าใช้งานได้',
    'eoffice_unavailable' => 'เชื่อมฐานข้อมูลบุคลากรไม่สำเร็จ กรุณาลองใหม่',
    'email_not_allowed' => 'อีเมลนี้ยังไม่ได้รับสิทธิ์เข้าใช้ SCiGENIE',
    'origin_mismatch' => 'โดเมนนี้ยังไม่ได้ลงทะเบียนใน Google Cloud Console',
    'redirect_uri_mismatch' => 'Redirect URI ไม่ตรงกับที่ลงใน Google Cloud Console',
    'oauth_error' => 'เข้าสู่ระบบด้วย Google ไม่สำเร็จ ตรวจการเชื่อมต่อฐานข้อมูล scigeniedb แล้วลองใหม่',
];
$code = (string) ($_GET['error'] ?? '');
$error = $messages[$code] ?? '';
if ($error === '' && $code !== '') {
    $error = $messages['oauth_error'];
}
$deniedEmail = trim((string) ($_GET['email'] ?? ''));
if (in_array($code, ['email_not_allowed', 'not_in_eoffice', 'eoffice_inactive'], true) && $deniedEmail !== '') {
    $error .= ' (' . $deniedEmail . ')';
}

$_SESSION['scigenie_login_csrf'] = bin2hex(random_bytes(16));
$base = app_base_path();
$clientId = (string) config('google_client_id');
$redirectUri = google_redirect_uri();
$appName = (string) config('app_name', 'SCiGENIE');
$faculty = (string) config('faculty', 'คณะวิทยาศาสตร์ มหาวิทยาลัยขอนแก่น');
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>เข้าสู่ระบบ | <?= htmlspecialchars($appName) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
  <link rel="icon" href="../assets/img/logo.jpg">
  <link rel="stylesheet" href="../assets/css/admin.css?v=<?= htmlspecialchars(asset_version('assets/css/admin.css')) ?>">
</head>
<body class="login-page">
  <main class="login-center">
    <div class="login-card">
      <div class="login-mark">
        <img src="../assets/img/logo.jpg" alt="<?= htmlspecialchars($appName) ?>">
      </div>
      <p class="login-name"><?= htmlspecialchars($appName) ?></p>
      <p class="login-system">ระบบตอบคำถามอัจฉริยะ</p>
      <p class="login-faculty"><?= htmlspecialchars($faculty) ?></p>
      <hr class="login-rule">
      <h1>เข้าสู่ระบบผู้จัดการ</h1>
      <p class="hint">สำหรับบุคลากรที่ดูแลคลังคำถาม</p>
      <?php if ($error): ?>
        <div class="alert err" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($clientId === ''): ?>
        <div class="alert err" role="alert">
          ยังไม่ได้ตั้งค่า Google Client ID บนเซิร์ฟเวอร์<br>
          <span class="hint" style="display:block;margin-top:8px;color:inherit;">
            ใส่ค่า <code>GOOGLE_CLIENT_ID</code> และ <code>GOOGLE_CLIENT_SECRET</code>
            จาก Google Cloud Console ลงไฟล์ <code>.env</code> ที่รากโปรเจกต์ แล้วรีเฟรชหน้านี้
          </span>
        </div>
      <?php else: ?>
      <a class="btn-google" href="../auth/google/start.php">
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
          <path fill="#4285F4" d="M23.49 12.27c0-.79-.07-1.54-.2-2.27H12v4.51h6.46c-.28 1.5-1.13 2.77-2.4 3.62v3.01h3.89c2.27-2.09 3.54-5.17 3.54-8.87z"/>
          <path fill="#34A853" d="M12 24c3.24 0 5.96-1.07 7.95-2.91l-3.89-3.01c-1.08.72-2.46 1.15-4.06 1.15-3.12 0-5.76-2.11-6.71-4.94H1.28v3.11C3.26 21.3 7.31 24 12 24z"/>
          <path fill="#FBBC05" d="M5.29 14.29A7.22 7.22 0 0 1 4.91 12c0-.79.14-1.56.38-2.29V6.6H1.28A11.99 11.99 0 0 0 0 12c0 1.94.46 3.77 1.28 5.4l4.01-3.11z"/>
          <path fill="#EA4335" d="M12 4.75c1.76 0 3.35.61 4.6 1.8l3.45-3.45C17.95 1.19 15.24 0 12 0 7.31 0 3.26 2.7 1.28 6.6l4.01 3.11C6.24 6.86 8.88 4.75 12 4.75z"/>
        </svg>
        <span>เข้าสู่ระบบด้วย Google</span>
      </a>
      <p class="oauth-help">
        ใน Google Cloud Console ต้องมี Authorized redirect URI ตรงทุกตัวอักษร:<br>
        <code><?= htmlspecialchars($redirectUri) ?></code>
      </p>
      <?php endif; ?>
      <p class="login-secure">เฉพาะผู้ที่ได้รับสิทธิ์</p>
      <a class="login-back" href="../index.php">กลับหน้าเว็บคณะ</a>
    </div>
  </main>
</body>
</html>
