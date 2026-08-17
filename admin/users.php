<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireSuper();

$sheet = new SheetService();
$categories = $sheet->categories();
$users = Auth::listAdmins();
$base = app_base_path();
$csrf = Auth::csrf();
$admin = Auth::user();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ผู้ใช้ระบบ | SCiGENIE</title>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
  <link rel="icon" href="<?= htmlspecialchars(logo_url()) ?>">
  <link rel="stylesheet" href="../assets/css/admin.css?v=<?= htmlspecialchars(asset_version('assets/css/admin.css')) ?>">
</head>
<body class="admin-page insights-page">
  <header class="topbar-admin">
    <div class="brand-mini">
      <img src="<?= htmlspecialchars(logo_url()) ?>" alt="SCiGENIE">
      <div>
        <strong>กำหนดผู้เข้าใช้งาน</strong>
        <span>ค้นหาบุคลากรจาก eoffice แล้วกำหนดหมวดคำถามที่ดูแลได้</span>
      </div>
    </div>
    <nav class="page-tabs" aria-label="หน้าผู้จัดการ">
      <a class="tab" href="index.php">คลังคำถาม</a>
      <a class="tab" href="insights.php">สรุปการถาม</a>
      <a class="tab active" href="users.php">ผู้ใช้ระบบ</a>
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

  <div class="insights-wrap users-wrap">
    <section class="chart-card user-form-card">
      <h3 id="user-form-title">เพิ่มผู้ใช้</h3>
      <p class="hint">พิมพ์ชื่อหรืออีเมลเพื่อค้นจากฐานข้อมูลบุคลากร แล้วเลือกหมวดที่ให้จัดการ</p>
      <form id="user-form">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="id" id="user-id" value="">
        <label for="user-search">ค้นหาบุคลากร</label>
        <input id="user-search" placeholder="ชื่อ หรืออีเมล" autocomplete="off">
        <div class="user-suggest" id="user-suggest" hidden></div>
        <label for="user-email">อีเมล</label>
        <input id="user-email" name="email" required placeholder="name@kku.ac.th">
        <label for="user-name">ชื่อ</label>
        <input id="user-name" name="name" placeholder="ดึงจาก eoffice เมื่อเลือกจากผลการค้นหา">
        <label for="user-role">สิทธิ์</label>
        <select id="user-role" name="role">
          <option value="manager">ผู้จัดการหมวด</option>
          <option value="super">ผู้ดูแลระบบ (ทุกหมวด)</option>
        </select>
        <fieldset class="cat-checks" id="cat-checks">
          <legend>หมวดที่จัดการได้</legend>
          <?php foreach ($categories as $cat): ?>
            <label class="check">
              <input type="checkbox" name="categories[]" value="<?= htmlspecialchars($cat['name']) ?>">
              <?= htmlspecialchars($cat['short']) ?>
            </label>
          <?php endforeach; ?>
        </fieldset>
        <label class="check">
          <input type="checkbox" name="is_active" id="user-active" checked>
          เปิดใช้งาน
        </label>
        <div id="user-form-msg"></div>
        <div class="form-actions">
          <button class="btn" type="submit" id="user-save">บันทึกผู้ใช้</button>
          <button class="btn ghost" type="button" id="user-reset">ล้างฟอร์ม</button>
        </div>
      </form>
    </section>

    <section class="chart-card">
      <h3>ผู้มีสิทธิ์ในระบบ</h3>
      <div class="table-wrap log-table-wrap">
        <table class="faq-table user-table">
          <thead>
            <tr>
              <th>ชื่อ</th>
              <th>อีเมล</th>
              <th>สิทธิ์</th>
              <th>หมวด</th>
              <th>สถานะ</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="user-body">
            <?php foreach ($users as $row): ?>
              <tr data-item="<?= htmlspecialchars(json_encode($row, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
                <td><?= htmlspecialchars((string) $row['name']) ?></td>
                <td><?= htmlspecialchars((string) $row['email']) ?></td>
                <td><?= $row['role'] === 'super' ? 'ผู้ดูแลระบบ' : 'ผู้จัดการหมวด' ?></td>
                <td><?= $row['role'] === 'super' ? 'ทุกหมวด' : htmlspecialchars(implode(', ', $row['categories'])) ?></td>
                <td><?= !empty($row['is_active']) ? 'เปิด' : 'ปิด' ?></td>
                <td>
                  <div class="actions">
                    <button type="button" class="icon-act edit-user" aria-label="แก้ไข">แก้</button>
                    <button type="button" class="icon-act del del-user" aria-label="ลบ">ลบ</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
  <div id="list-msg" class="toast" hidden></div>
  <script src="../assets/js/users.js?v=<?= htmlspecialchars(asset_version('assets/js/users.js')) ?>"></script>
</body>
</html>
