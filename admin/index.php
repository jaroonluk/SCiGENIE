<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireLogin();

$sheet = new SheetService();
$categories = $sheet->categories();
$faqs = $sheet->all();
$base = app_base_path();
$csrf = Auth::csrf();
$sheetConnected = trim((string) config('apps_script_url')) !== '';
$counts = [];
foreach ($faqs as $row) {
    $src = $row['source'] ?? 'sheet';
    $counts[$src] = ($counts[$src] ?? 0) + 1;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>เพิ่มคำถาม | SCiGENIE</title>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/css/admin.css">
</head>
<body>
  <div class="admin-shell">
    <aside class="side">
      <div class="login-mark" style="background:linear-gradient(135deg,#ffd54a,#d4a017);color:#1c1608;display:grid;place-items:center;width:48px;height:48px;border-radius:14px;">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"/><path d="M20.2 20.2c2.04-2.03.02-7.36-4.5-11.9-4.54-4.52-9.87-6.54-11.9-4.5-2.04 2.03-.02 7.36 4.5 11.9 4.54 4.52 9.87 6.54 11.9 4.5Z"/></svg>
      </div>
      <h2>SCiGENIE</h2>
      <p class="hint" style="color:#efe3c4;">คลังคำถามคณะวิทยาศาสตร์</p>
      <a class="active" href="index.php">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
        เพิ่มคำถาม
      </a>
      <a href="<?= htmlspecialchars($base) ?>/index.php" target="_blank">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        ดูหน้าต่างช่วยเหลือ
      </a>
      <a href="<?= htmlspecialchars((string) config('sheet_url')) ?>" target="_blank" rel="noopener">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
        เปิด Google Sheet
      </a>
      <a href="logout.php">ออกจากระบบ</a>
    </aside>

    <main class="main">
      <div class="top">
        <div>
          <h1 style="margin:0;">เพิ่มคำถามเข้าคลังอัจฉริยะ</h1>
          <p class="hint">เลือกหมวด กรอกคำถาม คีย์เวิร์ด และคำตอบ เพื่อให้ SCiGENIE นำไปตอบผู้ใช้</p>
        </div>
        <a class="btn ghost" href="<?= htmlspecialchars((string) config('sheet_url')) ?>" target="_blank" rel="noopener">เปิดชีตต้นทาง</a>
      </div>

      <div class="stats">
        <div class="stat"><span class="hint">คำถามทั้งหมด</span><b><?= count($faqs) ?></b></div>
        <div class="stat"><span class="hint">หมวด</span><b><?= count($categories) ?></b></div>
        <div class="stat"><span class="hint">จาก Google Sheet</span><b><?= (int) ($counts['sheet'] ?? 0) ?></b></div>
        <div class="stat"><span class="hint">เพิ่มจากระบบนี้</span><b><?= (int) ($counts['local'] ?? 0) ?></b></div>
      </div>

      <div class="layout">
        <section class="panel">
          <h3>
            <span class="ico">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
            </span>
            แบบฟอร์มเพิ่มคำถาม
          </h3>
          <form id="add-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <label>หมวดคำถาม</label>
            <select name="category" id="category" required>
              <option value="">— เลือกหมวด —</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['short']) ?> (<?= (int) $cat['count'] ?>)</option>
              <?php endforeach; ?>
              <option value="__new__">+ เพิ่มหมวดใหม่</option>
            </select>
            <div id="new-cat-wrap" style="display:none;">
              <label>ชื่อหมวดใหม่</label>
              <input id="new-category" placeholder="เช่น ทุนวิจัย">
            </div>
            <label>คำถามที่ผู้ใช้มักถาม</label>
            <textarea name="question" rows="3" required placeholder="เช่น คณะวิทยาศาสตร์อยู่ที่ไหน"></textarea>
            <label>คีย์เวิร์ดหลัก (คั่นด้วยเครื่องหมายจุลภาค)</label>
            <input name="keyword" required placeholder="ที่อยู่, คณะวิทยาศาสตร์, ที่ตั้ง">
            <p class="help">ระบบจะตอบเมื่อข้อความที่ผู้ใช้พิมพ์มีคำเหล่านี้ เช่น พิมพ์ “ที่อยู่คณะ” จะตรงกับคีย์เวิร์ด “ที่อยู่”</p>
            <label>คีย์เวิร์ดสำรอง (ไม่บังคับ)</label>
            <input name="keyword_back" placeholder="ที่ไหน, address, แผนที่">
            <label>คำตอบที่ระบบจะแสดง</label>
            <textarea name="answer" rows="6" required placeholder="รายละเอียด ขั้นตอน ลิงก์ หรือเบอร์ติดต่อ"></textarea>
            <button class="btn full" type="submit" id="save-btn">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"/><path d="M7 3v4a1 1 0 0 0 1 1h7"/></svg>
              บันทึกลงคลังคำถาม
            </button>
            <div id="form-msg"></div>
          </form>

          <div class="help" style="margin-top:16px;">
            <b>เชื่อม Google Sheet เพื่อบันทึกลงชีตจริง</b>
            <?php if ($sheetConnected): ?>
              <p>เชื่อม Apps Script แล้ว รายการใหม่จะถูก append ลงชีต</p>
            <?php else: ?>
              <ol class="steps">
                <li>เปิด Google Sheet แล้วไปที่ ส่วนขยาย → Apps Script</li>
                <li>วางโค้ดจากไฟล์ <code>apps-script/Code.gs</code></li>
                <li>Deploy → New deployment → Web app (Execute as Me, Anyone)</li>
                <li>นำ URL ไปใส่ที่ <code>includes/config.php</code> คีย์ <code>apps_script_url</code></li>
              </ol>
              <p>ก่อนเชื่อม ระบบจะเก็บคำถามไว้ในเครื่องก่อน แล้วนำไปใช้ตอบได้ทันที</p>
            <?php endif; ?>
          </div>
        </section>

        <section class="panel">
          <h3>
            <span class="ico">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </span>
            คำถามในคลัง
          </h3>
          <div class="searchbar">
            <select id="filter-cat">
              <option value="">ทุกหมวด</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['short']) ?></option>
              <?php endforeach; ?>
            </select>
            <input id="filter-q" placeholder="ค้นหาคำถาม / คีย์เวิร์ด">
          </div>
          <div style="overflow:auto;max-height:70vh;">
            <table>
              <thead>
                <tr><th>หมวด</th><th>คำถาม</th><th>คีย์เวิร์ด</th></tr>
              </thead>
              <tbody id="faq-body">
                <?php foreach ($faqs as $row): ?>
                  <tr data-cat="<?= htmlspecialchars($row['category']) ?>" data-text="<?= htmlspecialchars(mb_strtolower($row['category'] . ' ' . $row['question'] . ' ' . $row['keyword'] . ' ' . $row['keyword_back'])) ?>">
                    <td><span class="tag"><?= htmlspecialchars(Categories::short($row['category'])) ?></span></td>
                    <td><?= htmlspecialchars($row['question']) ?></td>
                    <td><?= htmlspecialchars($row['keyword']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </main>
  </div>
  <script src="<?= htmlspecialchars($base) ?>/assets/js/admin.js"></script>
</body>
</html>
