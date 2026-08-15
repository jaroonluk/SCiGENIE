<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireLogin();

$base = app_base_path();
$sheet = new SheetService();
$log = new AskLog();
$log->seedIfEmpty($sheet->all());
$rows = $log->all();
$summary = $log->summarize($rows, 14);
$hasSample = false;
foreach ($rows as $row) {
    if (!empty($row['sample'])) {
        $hasSample = true;
        break;
    }
}

function thai_when(string $iso): string
{
    $ts = strtotime($iso) ?: time();
    $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    return (int) date('j', $ts) . ' ' . $months[(int) date('n', $ts) - 1] . ' ' . (date('Y', $ts) + 543) . ' เวลา ' . date('H:i', $ts) . ' น.';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>สรุปการถาม | SCiGENIE</title>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
  <link rel="icon" href="<?= htmlspecialchars(logo_url()) ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/css/admin.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body class="admin-page insights-page">
  <header class="topbar-admin">
    <div class="brand-mini">
      <img src="<?= htmlspecialchars(logo_url()) ?>" alt="SCiGENIE">
      <div>
        <strong>สรุปการถาม–ตอบ</strong>
        <span>ดูว่าใครถามอะไร เมื่อไหร่ และระบบตอบอย่างไร พร้อมกราฟช่วงเวลาและคำถามยอดนิยม</span>
      </div>
    </div>
    <nav class="page-tabs" aria-label="หน้าผู้จัดการ">
      <a class="tab" href="index.php" data-tip="กลับไปเพิ่ม แก้ไข หรือลบคลังคำถาม">คลังคำถาม</a>
      <a class="tab active" href="insights.php" data-tip="หน้านี้นะ สรุปประวัติการถามและกราฟ">สรุปการถาม</a>
    </nav>
    <nav class="top-actions">
      <a class="btn ghost" href="<?= htmlspecialchars($base) ?>/index.php" target="_blank" data-tip="เปิดวิดเจ็ตเพื่อทดลองถาม แล้วกลับมาดูสถิติ">ทดลองถาม</a>
      <a class="btn ghost" href="logout.php" data-tip="ออกจากระบบผู้จัดการ">ออกจากระบบ</a>
    </nav>
  </header>

  <div class="insights-wrap">
    <?php if ($hasSample): ?>
      <div class="banner" data-tip="ข้อมูลชุดนี้สร้างไว้ให้ดูตัวอย่างกราฟ เมื่อมีผู้ใช้ถามจริง รายการใหม่จะถูกบันทึกต่อท้าย">
        มีข้อมูลตัวอย่างเพื่อให้เห็นกราฟทันที คำถามจริงจากวิดเจ็ตจะถูกบันทึกเพิ่มในตารางด้านล่าง
      </div>
    <?php endif; ?>

    <div class="kpi-grid">
      <article class="kpi" data-tip="จำนวนครั้งที่มีผู้ใช้พิมพ์ถามในช่วง 14 วันที่เลือก">
        <span>คำถามในช่วงนี้</span>
        <b id="kpi-total"><?= (int) $summary['total'] ?></b>
      </article>
      <article class="kpi" data-tip="จำนวนรหัสผู้ใช้ที่ไม่ซ้ำกัน ผู้ใช้คนเดิมจะใช้รหัสเดิมหากใช้เครื่องเดิม">
        <span>ผู้ใช้ที่ไม่ซ้ำ</span>
        <b id="kpi-visitors"><?= (int) $summary['unique_visitors'] ?></b>
      </article>
      <article class="kpi" data-tip="ร้อยละที่ระบบหาคำตอบจากคลังได้ หากต่ำ ควรเพิ่มคีย์เวิร์ดในคลังคำถาม">
        <span>ตอบได้</span>
        <b id="kpi-rate"><?= (int) $summary['match_rate'] ?>%</b>
      </article>
      <article class="kpi" data-tip="ชั่วโมงที่มีคนถามมากที่สุด ใช้วางเวลามีเจ้าหน้าที่ดูแล">
        <span>ช่วงที่ถามมากสุด</span>
        <b id="kpi-peak"><?= sprintf('%02d:00', (int) $summary['peak_hour']) ?></b>
      </article>
    </div>

    <div class="toolbar insights-toolbar">
      <span class="hint">ช่วงเวลาของกราฟ</span>
      <div class="range-btns">
        <button type="button" class="btn ghost range" data-days="7" data-tip="ดูสถิติ 7 วันล่าสุด">7 วัน</button>
        <button type="button" class="btn ghost range is-on" data-days="14" data-tip="ดูสถิติ 14 วันล่าสุด">14 วัน</button>
        <button type="button" class="btn ghost range" data-days="30" data-tip="ดูสถิติ 30 วันล่าสุด">30 วัน</button>
      </div>
      <span class="hint" id="range-hint">กำลังแสดง 14 วันล่าสุด เลื่อนลงเพื่อดูกราฟและประวัติรายคน</span>
    </div>

    <div class="chart-grid">
      <section class="chart-card" data-tip="แท่งสูง = วันนั้นมีคนถามมาก ใช้ดูว่าวันไหนงานถามตอบหนาแน่น">
        <h3>จำนวนคำถามรายวัน</h3>
        <p class="hint">แกนตั้งคือจำนวนครั้งที่ถาม แกนนอนคือวันที่</p>
        <div class="chart-box"><canvas id="chart-day"></canvas></div>
      </section>
      <section class="chart-card" data-tip="ดูว่าคนถามช่วงเช้า บ่าย หรือค่ำ แท่งสูงสุดคือชั่วโมงที่ควรวางเจ้าหน้าที่">
        <h3>ช่วงเวลาที่ถามบ่อย</h3>
        <p class="hint">แกนนอนคือเวลา 00:00–23:00 น. แท่งสูง = ถามบ่อยช่วงนั้น</p>
        <div class="chart-box"><canvas id="chart-hour"></canvas></div>
      </section>
    </div>

    <section class="chart-card" data-tip="อันดับ 1 คือเรื่องที่ควรถูกค้นหาง่ายและมีคำตอบครบที่สุด">
      <h3>คำถามที่ถูกถามบ่อยที่สุด</h3>
      <p class="hint">เรียงจากมากไปน้อย ความยาวแท่งคือจำนวนครั้งที่ถาม เทียบกับคลัง FAQ ที่ระบบจับได้</p>
      <div class="top-layout">
        <div class="chart-box tall"><canvas id="chart-top"></canvas></div>
        <ol class="top-list" id="top-list">
          <?php if (!$summary['top']): ?>
            <li class="empty">ยังไม่มีคำถามในช่วงนี้</li>
          <?php endif; ?>
          <?php foreach ($summary['top'] as $item): ?>
            <li>
              <span class="rank"><?= (int) $item['rank'] ?></span>
              <span class="label"><?= htmlspecialchars($item['label']) ?></span>
              <span class="count"><?= (int) $item['count'] ?> ครั้ง</span>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
    </section>

    <section class="chart-card table-card">
      <h3>ประวัติการถามรายคน</h3>
      <p class="hint">แต่ละแถวคือ 1 ครั้งที่มีผู้ใช้ถาม: ใคร ถามอะไร เมื่อไหร่ และระบบตอบว่าอะไร กดแถวเพื่อดูคำตอบเต็ม</p>
      <div class="table-wrap log-table-wrap">
        <table class="faq-table log-table">
          <thead>
            <tr>
              <th style="width:16%">เมื่อไหร่</th>
              <th style="width:10%">ผู้ใช้</th>
              <th style="width:12%">หมวด</th>
              <th style="width:24%">ถามว่า</th>
              <th style="width:28%">ระบบตอบ</th>
              <th style="width:10%">ผล</th>
            </tr>
          </thead>
          <tbody id="log-body">
            <?php foreach (array_slice($rows, 0, 80) as $row): ?>
              <tr class="faq-row log-row" data-tip="คลิกเพื่อขยายดูคำตอบเต็มของครั้งนี้">
                <td><?= htmlspecialchars(thai_when((string) ($row['time'] ?? ''))) ?></td>
                <td><span class="tag">ผู้ใช้ <?= htmlspecialchars((string) ($row['visitor'] ?? '-')) ?></span></td>
                <td><?= htmlspecialchars((string) ($row['category'] ?: '-')) ?></td>
                <td><div class="cell-q"><?= htmlspecialchars((string) $row['question']) ?></div></td>
                <td><div class="cell-a"><?= htmlspecialchars((string) ($row['answer'] ?? '')) ?></div></td>
                <td><?= !empty($row['matched']) ? '<span class="ok-dot">ตอบได้</span>' : '<span class="no-dot">ไม่พบ</span>' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>

  <div class="coach" id="coach" role="status">
    <span class="coach-ico" aria-hidden="true">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
    </span>
    <span id="coach-text">ชี้ที่กราฟหรือแถวในตาราง เพื่อดูคำอธิบาย กด 7 / 14 / 30 วัน เพื่อเปลี่ยนช่วงสถิติ</span>
  </div>

  <script>
    window.SCIGENIE_INSIGHTS = <?= json_encode(['summary' => $summary, 'rows' => array_slice($rows, 0, 300)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <script src="<?= htmlspecialchars($base) ?>/assets/js/insights.js"></script>
</body>
</html>
