<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireLogin();

$sheet = new SheetService();
$categories = Auth::filterCategories($sheet->categories());
$faqs = Auth::filterFaqs($sheet->all());
$base = app_base_path();
$csrf = Auth::csrf();
$admin = Auth::user();
$isSuper = Auth::isSuper();
$canEdit = Auth::canEdit();

function faq_payload(array $row): array
{
    return [
        'id' => (string) $row['id'],
        'category' => $row['category'],
        'question' => $row['question'],
        'keyword' => $row['keyword'],
        'keyword_back' => $row['keyword_back'] ?? '',
        'answer' => $row['answer'],
        'source' => $row['source'] ?? 'sheet',
    ];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>จัดการคำถาม | SCiGENIE</title>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
  <link rel="icon" href="<?= htmlspecialchars(logo_url()) ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/css/admin.css?v=<?= filemtime(dirname(__DIR__) . '/assets/css/admin.css') ?>">
</head>
<body class="admin-page">
  <header class="topbar-admin">
    <div class="brand-mini">
      <img src="<?= htmlspecialchars(logo_url()) ?>" alt="SCiGENIE">
      <div>
        <strong>จัดการคลังคำถามอัจฉริยะ</strong>
        <span>เลือกจากตารางเพื่อดูคำตอบ กดแก้ไขเพื่อปรับข้อมูล หรือกดเพิ่มคำถามใหม่</span>
      </div>
    </div>
    <div class="stat-pills" aria-label="สรุปคลังคำถาม">
      <span class="pill" data-tip="จำนวนคำถามที่ SCiGENIE นำไปตอบได้ทั้งหมด"><b id="stat-total"><?= count($faqs) ?></b> คำถาม</span>
      <span class="pill" data-tip="จำนวนหมวดที่ใช้จัดกลุ่มคำถาม"><b><?= count($categories) ?></b> หมวด</span>
    </div>
    <nav class="page-tabs" aria-label="หน้าผู้จัดการ">
      <a class="tab active" href="index.php" data-tip="หน้าปัจจุบัน สำหรับเพิ่ม แก้ไข ลบคำถาม">คลังคำถาม</a>
      <a class="tab" href="insights.php" data-tip="ดูว่าใครถามอะไร เมื่อไหร่ ระบบตอบอย่างไร พร้อมกราฟ">สรุปการถาม</a>
      <?php if ($isSuper): ?>
        <a class="tab" href="users.php" data-tip="กำหนดผู้มีสิทธิ์เข้าใช้ระบบ และหมวดที่ดูแล">ผู้ใช้ระบบ</a>
      <?php endif; ?>
    </nav>
    <nav class="top-actions">
      <div class="admin-who" title="<?= htmlspecialchars($admin['email']) ?>">
        <?php if ($admin['picture'] !== ''): ?>
          <img src="<?= htmlspecialchars($admin['picture']) ?>" alt="">
        <?php endif; ?>
        <span><?= htmlspecialchars($admin['name'] !== '' ? $admin['name'] : $admin['email']) ?></span>
      </div>
      <a class="btn ghost" href="<?= htmlspecialchars($base) ?>/index.php" target="_blank" data-tip="เปิดหน้าเว็บคณะเพื่อทดลองถาม SCiGENIE">ทดลองถาม</a>
      <a class="btn ghost" href="logout.php" data-tip="ออกจากระบบผู้จัดการ">ออกจากระบบ</a>
      <button class="btn" type="button" id="btn-add" data-tip="เปิดแบบฟอร์มด้านขวาเพื่อเพิ่มคำถามใหม่เข้าคลัง">+ เพิ่มคำถาม</button>
    </nav>
  </header>

  <div class="workspace">
    <section class="list-pane">
      <div class="toolbar">
        <label class="field-inline">
          <span>หมวด</span>
          <select id="filter-cat" data-tip="กรองเฉพาะหมวดที่ต้องการดู เช่น ลงทะเบียน หรือ การสอบ">
            <option value="">ทุกหมวด</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['short']) ?> (<?= (int) $cat['count'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field-inline grow">
          <span>ค้นหา</span>
          <input id="filter-q" placeholder="พิมพ์คำถาม คีย์เวิร์ด หรือข้อความในคำตอบ" data-tip="พิมพ์คำใดก็ได้ที่อยู่ในคำถาม คีย์เวิร์ด หรือคำตอบ ตารางจะกรองทันที">
        </label>
        <div class="result-count" id="result-count" data-tip="จำนวนรายการที่แสดงหลังกรอง"><?= count($faqs) ?> รายการ</div>
      </div>

      <div class="table-wrap" id="table-wrap">
        <table class="faq-table">
          <thead>
            <tr>
              <th style="width:12%">หมวด</th>
              <th style="width:28%">คำถาม</th>
              <th style="width:32%">คำตอบที่ระบบจะแสดง</th>
              <th style="width:16%">คีย์เวิร์ด</th>
              <th style="width:12%">จัดการ</th>
            </tr>
          </thead>
          <tbody id="faq-body">
            <?php foreach ($faqs as $row): ?>
              <?php $payload = faq_payload($row); ?>
              <tr class="faq-row" data-id="<?= htmlspecialchars($payload['id']) ?>" data-cat="<?= htmlspecialchars($row['category']) ?>" data-text="<?= htmlspecialchars(mb_strtolower($row['category'] . ' ' . $row['question'] . ' ' . $row['keyword'] . ' ' . ($row['keyword_back'] ?? '') . ' ' . $row['answer'])) ?>" data-item="<?= htmlspecialchars(json_encode($payload, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
                <td><span class="tag"><?= htmlspecialchars(Categories::short($row['category'])) ?></span></td>
                <td>
                  <div class="cell-q"><?= htmlspecialchars($row['question']) ?></div>
                  <button type="button" class="linkish preview" data-tip="คลิกเพื่อขยายดูคำตอบเต็มในแถวนี้">ดูคำตอบเต็ม</button>
                </td>
                <td><div class="cell-a"><?= htmlspecialchars($row['answer']) ?></div></td>
                <td><div class="cell-k"><?= htmlspecialchars($row['keyword']) ?></div></td>
                <td>
                  <div class="actions">
                    <button type="button" class="icon-act edit" data-tip="เปิดแบบฟอร์มด้านขวา พร้อมข้อมูลคำถามนี้ให้แก้ไข" aria-label="แก้ไข">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12.5 22H18a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v9.5"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10.4 12.6a2 2 0 1 1 3 3L8 21l-4 1 1-4Z"/></svg>
                    </button>
                    <button type="button" class="icon-act del" data-tip="ลบคำถามนี้ออกจากคลัง SCiGENIE จะไม่ตอบข้อนี้อีก" aria-label="ลบ">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div class="empty-state" id="empty-state" hidden>ไม่พบรายการที่ตรงกับการค้นหา ลองล้างคำค้น หรือเลือกหมวดเป็น “ทุกหมวด”</div>
      </div>

      <div class="coach" id="coach" role="status">
        <span class="coach-ico" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
        </span>
        <span id="coach-text">คลิกแถวเพื่อดูคำตอบเต็ม กดดินสอเพื่อแก้ไข กดถังขยะเพื่อลบ หรือกด “เพิ่มคำถาม” เพื่อสร้างรายการใหม่</span>
      </div>
    </section>
  </div>

  <div class="drawer-backdrop" id="drawer-backdrop" hidden></div>
  <aside class="drawer" id="drawer" aria-label="แบบฟอร์มคำถาม">
    <div class="drawer-head">
      <div>
        <h2 id="drawer-title">เพิ่มคำถามใหม่</h2>
        <p class="hint" id="drawer-sub">กรอกทีละช่องตามคำแนะนำด้านล่าง แล้วกดบันทึก</p>
      </div>
      <button class="icon-act" type="button" id="drawer-close" data-tip="ปิดแบบฟอร์มโดยไม่บันทึกการเปลี่ยนแปลงล่าสุด" aria-label="ปิด">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
      </button>
    </div>
    <form id="add-form" class="drawer-body">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="id" id="faq-id" value="">

      <div class="drawer-grid">
        <div>
          <label for="category">1. เลือกหมวดคำถาม</label>
          <select name="category" id="category" required data-tip="เลือกหมวดให้ตรงกับเรื่องที่ผู้ใช้จะถาม เช่น ลงทะเบียน การสอบ สวัสดิการ">
            <option value="">— เลือกหมวด —</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['short']) ?> (<?= (int) $cat['count'] ?>)</option>
            <?php endforeach; ?>
            <?php if ($isSuper): ?>
            <option value="__new__">+ เพิ่มหมวดใหม่</option>
            <?php endif; ?>
          </select>
          <p class="field-tip" data-for="category">หมวดช่วยให้ SCiGENIE ตอบตรงเรื่องขึ้น เมื่อผู้ใช้เลือกหมวดก่อนถาม</p>
          <div id="new-cat-wrap" hidden>
            <label for="new-category">ชื่อหมวดใหม่</label>
            <input id="new-category" placeholder="เช่น ทุนวิจัย" data-tip="ตั้งชื่อสั้น ชัดเจน เช่น ทุนวิจัย หรือ หอพัก">
            <p class="field-tip">ใช้ชื่อที่ผู้ใช้เข้าใจทันที ไม่ต้องยาว</p>
          </div>

          <label for="question">2. คำถามที่ผู้ใช้มักพิมพ์</label>
          <textarea name="question" id="question" rows="3" required placeholder="เช่น ค่าเทอมเท่าไหร่" data-tip="เขียนให้เหมือนประโยคที่นักศึกษาพิมพ์จริง ไม่ต้องเป็นภาษาทางการเกินไป"></textarea>
          <p class="field-tip" data-for="question">ตัวอย่างที่ดี: "เลื่อนสอบทำอย่างไร" หรือ "ย้ายสิทธิบัตรทอง"</p>

          <label for="keyword">3. คีย์เวิร์ดหลัก <small>(คั่นด้วยจุลภาค)</small></label>
          <input name="keyword" id="keyword" required placeholder="ค่าเทอม, ค่าธรรมเนียม, tuition" data-tip="ใส่คำสั้นๆ ที่มักปรากฏในคำถาม คั่นด้วยเครื่องหมายจุลภาค">
          <p class="field-tip" data-for="keyword">เมื่อผู้ใช้พิมพ์คำเหล่านี้ ระบบจะเลือกคำตอบนี้ ยิ่งเฉพาะเจาะจงยิ่งแม่น</p>
          <div class="kw-preview" id="kw-preview"></div>

          <label for="keyword_back">4. คีย์เวิร์ดสำรอง <small>(ไม่บังคับ)</small></label>
          <input name="keyword_back" id="keyword_back" placeholder="ค่าเล่าเรียน, ค่าหน่วยกิต" data-tip="คำใกล้เคียงหรือคำที่สะกดต่างกัน ใช้เมื่อคีย์เวิร์ดหลักยังไม่ครอบคลุม">
          <p class="field-tip" data-for="keyword_back">ใส่ได้ถ้ามีคำพ้อง เช่น ที่อยู่ / ที่ตั้ง / อยู่ที่ไหน</p>
        </div>

        <div>
          <label for="answer">5. คำตอบที่ระบบจะแสดง</label>
          <textarea name="answer" id="answer" rows="12" required placeholder="ใส่ขั้นตอน เบอร์โทร ลิงก์ หรือเงื่อนไขให้ครบ" data-tip="ข้อความนี้จะโชว์ในแชท ใส่ลิงก์และเบอร์ติดต่อได้เลย"></textarea>
          <p class="field-tip" data-for="answer">ควรมีขั้นตอนชัดเจน และใส่ลิงก์เต็มๆ หากมีเอกสารหรือระบบที่เกี่ยวข้อง</p>
        </div>

        <div class="field-full">
          <div id="form-msg"></div>
          <div class="form-actions">
            <button class="btn" type="submit" id="save-btn" data-tip="บันทึกแล้ว SCiGENIE จะใช้ข้อมูลนี้ตอบผู้ใช้ทันที">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/></svg>
              <span id="save-label">บันทึกคำถาม</span>
            </button>
            <button class="btn ghost" type="button" id="cancel-btn" data-tip="ปิดแบบฟอร์มและไม่บันทึกการแก้ไขครั้งนี้">ยกเลิก</button>
          </div>
          <p class="hint save-note">รายการจะถูกบันทึกลงฐานข้อมูล scigeniedb</p>
        </div>
      </div>
    </form>
  </aside>

  <div id="list-msg" class="toast" hidden></div>
  <script src="<?= htmlspecialchars($base) ?>/assets/js/admin.js?v=<?= htmlspecialchars(asset_version('assets/js/admin.js')) ?>"></script>
</body>
</html>
