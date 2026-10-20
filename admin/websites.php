<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireLogin();

$base = app_base_path();
$csrf = Auth::csrf();
$admin = Auth::user();
$isSuper = Auth::isSuper();
$ready = false;
$rows = [];
$dbError = '';
try {
    $ready = WebSources::ready();
    if ($ready) {
        $rows = WebSources::all();
    }
} catch (Throwable $e) {
    $dbError = 'เชื่อมต่อฐานข้อมูลไม่สำเร็จ';
    error_log('SCiGENIE web sources page: ' . $e->getMessage());
}
$sql = WebSources::installSql();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>เว็บอ้างอิง | SCiGENIE</title>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
  <link rel="icon" href="<?= htmlspecialchars(logo_url()) ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/css/admin.css?v=<?= htmlspecialchars(asset_version('assets/css/admin.css')) ?>">
</head>
<body class="admin-page insights-page">
  <header class="topbar-admin">
    <div class="brand-mini">
      <img src="<?= htmlspecialchars(logo_url()) ?>" alt="SCiGENIE">
      <div>
        <strong>เว็บไซต์ที่ใช้ค้นคำตอบ</strong>
        <span>ใช้เมื่อคลังคำถามไม่มีคำตอบ ระบบจะค้นจากลิงก์เหล่านี้แล้วอ้างอิงหน้าเว็บที่พบ</span>
      </div>
    </div>
    <nav class="page-tabs" aria-label="หน้าผู้จัดการ">
      <a class="tab" href="index.php">คลังคำถาม</a>
      <a class="tab" href="insights.php">สรุปการถาม</a>
      <a class="tab active" href="websites.php">เว็บอ้างอิง</a>
      <?php if ($isSuper): ?>
        <a class="tab" href="users.php">ผู้ใช้ระบบ</a>
      <?php endif; ?>
    </nav>
    <nav class="top-actions">
      <div class="admin-who" title="<?= htmlspecialchars($admin['email']) ?>">
        <?php if ($admin['picture'] !== ''): ?>
          <img src="<?= htmlspecialchars($admin['picture']) ?>" alt="">
        <?php endif; ?>
        <span><?= htmlspecialchars($admin['name'] !== '' ? $admin['name'] : $admin['email']) ?></span>
      </div>
      <a class="btn ghost" href="logout.php">ออกจากระบบ</a>
    </nav>
  </header>

  <div class="insights-wrap">
    <?php if ($dbError !== ''): ?>
      <section class="chart-card">
        <h3>ยังเปิดรายการไม่ได้</h3>
        <p class="hint"><?= htmlspecialchars($dbError) ?></p>
      </section>
    <?php elseif (!$ready): ?>
      <section class="chart-card">
        <h3>ยังไม่มีตารางเก็บลิงก์เว็บไซต์</h3>
        <p class="hint">รันคำสั่งด้านล่างในฐานข้อมูลเดิมของ SCiGENIE ก่อน แล้วรีเฟรชหน้านี้ ไม่ต้องสร้างฐานข้อมูลใหม่</p>
        <pre class="sql-box"><?= htmlspecialchars($sql) ?></pre>
      </section>
    <?php else: ?>
      <section class="chart-card">
        <h3 id="site-form-title">เพิ่มเว็บไซต์</h3>
        <p class="hint">ใส่ชื่อที่ทีมงานจำได้ และลิงก์หน้าเว็บที่ต้องการให้ SCiGENIE ไปค้น ปิดใช้งานได้ถ้ายังไม่ต้องการให้ค้นลิงก์นั้น</p>
        <form id="site-form" class="site-form">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" id="site-id" value="">
          <label for="site-title">ชื่อแหล่งข้อมูล</label>
          <input id="site-title" required maxlength="191" placeholder="เช่น ปฏิทินการศึกษา">
          <label for="site-url">ลิงก์เว็บไซต์</label>
          <input id="site-url" required maxlength="500" placeholder="https://">
          <label for="site-note">คำอธิบายสั้น ๆ</label>
          <input id="site-note" maxlength="255" placeholder="เช่น ปฏิทินการศึกษาปริญญาตรี">
          <label for="site-sort">ลำดับการค้น</label>
          <input id="site-sort" type="number" value="0">
          <label class="check">
            <input type="checkbox" id="site-active" checked>
            เปิดให้ SCiGENIE ค้นลิงก์นี้
          </label>
          <div id="site-msg" hidden></div>
          <div class="form-actions">
            <button class="btn" type="submit"><span id="site-save-label">บันทึก</span></button>
            <button class="btn ghost" type="button" id="site-cancel">ล้างฟอร์ม</button>
          </div>
        </form>
      </section>

      <section class="chart-card table-card">
        <h3>ลิงก์ทั้งหมด <b><?= count($rows) ?></b></h3>
        <div class="table-wrap">
          <table class="faq-table">
            <thead>
              <tr>
                <th>ชื่อ</th>
                <th>ลิงก์</th>
                <th>คำอธิบาย</th>
                <th>สถานะ</th>
                <th>ลำดับ</th>
                <th>จัดการ</th>
              </tr>
            </thead>
            <tbody id="site-body">
              <?php if ($rows === []): ?>
                <tr><td colspan="6">ยังไม่มีเว็บไซต์ กดบันทึกเพื่อเพิ่มลิงก์แรก</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $row): ?>
                  <tr data-item="<?= htmlspecialchars(json_encode($row, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
                    <td><?= htmlspecialchars($row['title']) ?></td>
                    <td><a href="<?= htmlspecialchars($row['url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($row['url']) ?></a></td>
                    <td><?= htmlspecialchars($row['note']) ?></td>
                    <td><?= $row['is_active'] ? 'เปิดใช้' : 'ปิด' ?></td>
                    <td><?= (int) $row['sort_order'] ?></td>
                    <td>
                      <div class="actions">
                        <button type="button" class="icon-act edit" title="แก้ไข" aria-label="แก้ไข">
                          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12.5 22H18a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v9.5"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10.4 12.6a2 2 0 1 1 3 3L8 21l-4 1 1-4Z"/></svg>
                        </button>
                        <button type="button" class="icon-act del" title="ลบ" aria-label="ลบ">
                          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
      <script src="<?= htmlspecialchars($base) ?>/assets/js/websites.js?v=<?= htmlspecialchars(asset_version('assets/js/websites.js')) ?>"></script>
    <?php endif; ?>
  </div>
</body>
</html>
