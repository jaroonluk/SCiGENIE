<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireLogin();

$base = app_base_path();
$admin = Auth::user();
$isSuper = Auth::isSuper();
$allowed = Auth::allowedCategories();
$categories = Auth::filterCategories((new SheetService())->categories());
$log = new AskLog();
$rows = Auth::filterAskRows($log->all(4000, true));
$summary = $log->summarize($rows, 14);
$scopeCats = $isSuper ? 'ทุกหมวด' : implode(', ', $allowed ?? []);
$adminRows = $isSuper ? $log->adminLog(200) : [];

function thai_when(string $iso): string
{
    $ts = strtotime($iso) ?: 0;
    if ($ts <= 0) {
        return '-';
    }
    $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    return (int) date('j', $ts) . ' ' . $months[(int) date('n', $ts) - 1] . ' ' . (date('Y', $ts) + 543) . ' เวลา ' . date('H:i', $ts) . ' น.';
}

function add_faq_url(string $category, string $question): string
{
    return 'index.php?add=1&c=' . rawurlencode($category) . '&q=' . rawurlencode($question);
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
  <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/css/admin.css?v=<?= htmlspecialchars(asset_version('assets/css/admin.css')) ?>">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body class="admin-page insights-page">
  <header class="topbar-admin">
    <div class="brand-mini">
      <img src="<?= htmlspecialchars(logo_url()) ?>" alt="SCiGENIE">
      <div>
        <strong>สรุปการถาม–ตอบ</strong>
        <span><?= $isSuper ? 'ผู้ดูแลระบบเห็นข้อมูลทุกหมวด จากคำถามจริงของผู้ใช้' : 'แสดงเฉพาะหมวดที่คุณดูแล จากคำถามจริงของผู้ใช้' ?></span>
      </div>
    </div>
    <nav class="page-tabs" aria-label="หน้าผู้จัดการ">
      <a class="tab" href="index.php">คลังคำถาม</a>
      <a class="tab active" href="insights.php">สรุปการถาม</a>
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
      <a class="btn ghost" href="<?= htmlspecialchars($base) ?>/index.php" target="_blank">ทดลองถาม</a>
      <a class="btn ghost" href="logout.php">ออกจากระบบ</a>
    </nav>
  </header>

  <div class="insights-wrap">
    <div class="banner scope-banner">
      กำลังดูข้อมูลจริงของ <b><?= htmlspecialchars($scopeCats !== '' ? $scopeCats : 'ยังไม่ได้กำหนดหมวด') ?></b>
      <?= $isSuper ? '' : ' — คำถามหมวดอื่นจะไม่แสดงในหน้านี้' ?>
    </div>

    <div class="kpi-grid">
      <article class="kpi" data-tip="จำนวนครั้งที่ผู้ใช้ถามจริงในช่วงที่เลือก ไม่รวมข้อมูลตัวอย่าง">
        <span>คำถามจริง</span>
        <b id="kpi-total"><?= (int) $summary['total'] ?></b>
      </article>
      <article class="kpi is-ok" data-tab-jump="matched" data-tip="ระบบจับคู่คลัง FAQ ได้ กดเพื่อดูรายการ">
        <span>ตอบได้</span>
        <b id="kpi-matched"><?= (int) $summary['matched'] ?></b>
      </article>
      <article class="kpi is-no" data-tab-jump="unmatched" data-tip="ระบบไม่พบคำตอบ ควรเพิ่ม FAQ กดเพื่อดูรายการ">
        <span>ไม่พบคำตอบ</span>
        <b id="kpi-unmatched"><?= (int) $summary['unmatched'] ?></b>
      </article>
      <article class="kpi" data-tip="ร้อยละที่ระบบตอบได้จากคลังคำถาม">
        <span>สัดส่วนตอบได้</span>
        <b id="kpi-rate"><?= (int) $summary['match_rate'] ?>%</b>
      </article>
    </div>

    <div class="toolbar insights-toolbar">
      <span class="hint">ช่วงเวลา</span>
      <div class="range-btns">
        <button type="button" class="btn ghost range" data-days="7">7 วัน</button>
        <button type="button" class="btn ghost range is-on" data-days="14">14 วัน</button>
        <button type="button" class="btn ghost range" data-days="30">30 วัน</button>
      </div>
      <label class="field-inline">
        <span>หมวด</span>
        <select id="filter-cat">
          <option value="">ทุกหมวดที่ดูได้</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['short'] ?: $cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field-inline grow">
        <span>ค้นหา</span>
        <input id="filter-q" type="search" placeholder="พิมพ์คำที่ผู้ใช้ถาม หรือหัวข้อ FAQ">
      </label>
      <span class="hint" id="range-hint">กำลังแสดง 14 วันล่าสุด จากคำถามจริง</span>
    </div>

    <div class="chart-grid">
      <section class="chart-card">
        <h3>จำนวนคำถามรายวัน</h3>
        <p class="hint">นับเฉพาะคำถามจริงในช่วงที่เลือก</p>
        <div class="chart-box"><canvas id="chart-day"></canvas></div>
      </section>
      <section class="chart-card">
        <h3>ช่วงเวลาที่ถามบ่อย</h3>
        <p class="hint">ใช้วางช่วงที่มีเจ้าหน้าที่ดูแล</p>
        <div class="chart-box"><canvas id="chart-hour"></canvas></div>
      </section>
    </div>

    <div class="review-tabs" role="tablist">
      <button type="button" class="review-tab is-on" data-tab="unmatched">ไม่พบคำตอบ <b id="tab-unmatched"><?= (int) $summary['unmatched'] ?></b></button>
      <button type="button" class="review-tab" data-tab="matched">ตอบได้ <b id="tab-matched"><?= (int) $summary['matched'] ?></b></button>
      <?php if ($isSuper): ?>
      <button type="button" class="review-tab" data-tab="history">ประวัติรายครั้ง</button>
      <button type="button" class="review-tab" data-tab="admin-log">ผู้ใช้งานระบบ <b><?= count($adminRows) ?></b></button>
      <?php endif; ?>
    </div>

    <section class="chart-card table-card review-panel" data-panel="unmatched">
      <h3>คำถามที่ยังไม่พบคำตอบ</h3>
      <p class="hint">จัดกลุ่มจากข้อความที่ผู้ใช้พิมพ์จริง เรียงจากถูกถามบ่อยสุด กด «เพิ่มคำตอบ» เพื่อเปิดฟอร์มคลังคำถาม</p>
      <div class="table-wrap log-table-wrap">
        <table class="faq-table log-table">
          <thead>
            <tr>
              <th style="width:8%">ครั้ง</th>
              <th style="width:14%">หมวด</th>
              <th>คำถามที่ผู้ใช้พิมพ์</th>
              <th style="width:18%">ถามล่าสุด</th>
              <th style="width:14%"></th>
            </tr>
          </thead>
          <tbody id="unmatched-body">
            <?php if (!$summary['unmatched_groups']): ?>
              <tr><td colspan="5" class="hint">ยังไม่มีคำถามที่ไม่พบคำตอบในช่วงนี้</td></tr>
            <?php endif; ?>
            <?php foreach ($summary['unmatched_groups'] as $item): ?>
              <tr class="faq-row">
                <td><span class="count-pill"><?= (int) $item['count'] ?></span></td>
                <td><?= htmlspecialchars($item['category'] !== '' ? $item['category'] : '-') ?></td>
                <td>
                  <div class="cell-q"><?= htmlspecialchars($item['question']) ?></div>
                  <?php if ($item['samples'] && $item['samples'] !== [$item['question']]): ?>
                    <div class="cell-samples">เช่น <?= htmlspecialchars(implode(' · ', array_slice($item['samples'], 0, 2))) ?></div>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars(thai_when($item['last_time'])) ?></td>
                <td>
                  <a class="btn" href="<?= htmlspecialchars(add_faq_url($item['category'], $item['question'])) ?>">เพิ่มคำตอบ</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="chart-card table-card review-panel" data-panel="matched" hidden>
      <h3>คำถามที่ระบบตอบได้</h3>
      <p class="hint">จัดกลุ่มตาม FAQ ที่ระบบจับได้ ใช้ตรวจว่าคำตอบตรงกับที่ผู้ใช้ถามหรือไม่</p>
      <div class="table-wrap log-table-wrap">
        <table class="faq-table log-table">
          <thead>
            <tr>
              <th style="width:8%">ครั้ง</th>
              <th style="width:14%">หมวด</th>
              <th>FAQ ที่จับได้</th>
              <th>ตัวอย่างที่ผู้ใช้พิมพ์</th>
              <th style="width:18%">ถามล่าสุด</th>
            </tr>
          </thead>
          <tbody id="matched-body">
            <?php if (!$summary['matched_groups']): ?>
              <tr><td colspan="5" class="hint">ยังไม่มีคำถามที่ตอบได้ในช่วงนี้</td></tr>
            <?php endif; ?>
            <?php foreach ($summary['matched_groups'] as $item): ?>
              <tr class="faq-row">
                <td><span class="count-pill ok"><?= (int) $item['count'] ?></span></td>
                <td><?= htmlspecialchars($item['category'] !== '' ? $item['category'] : '-') ?></td>
                <td><div class="cell-q"><?= htmlspecialchars($item['question']) ?></div></td>
                <td><div class="cell-samples"><?= htmlspecialchars(implode(' · ', array_slice($item['samples'], 0, 3))) ?></div></td>
                <td><?= htmlspecialchars(thai_when($item['last_time'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <?php if ($isSuper): ?>
    <section class="chart-card table-card review-panel" data-panel="history" hidden>
      <h3>ประวัติการถามรายครั้ง</h3>
      <p class="hint">แต่ละแถวคือ 1 ครั้งที่ผู้ใช้ถาม กดแถวเพื่อดูคำตอบที่ระบบส่ง</p>
      <div class="table-wrap log-table-wrap">
        <table class="faq-table log-table">
          <thead>
            <tr>
              <th style="width:16%">เมื่อไหร่</th>
              <th style="width:10%">ผู้ใช้</th>
              <th style="width:14%">หมวด</th>
              <th style="width:24%">ถามว่า</th>
              <th style="width:26%">ระบบตอบ</th>
              <th style="width:10%">ผล</th>
            </tr>
          </thead>
          <tbody id="log-body">
            <?php foreach (array_slice($rows, 0, 80) as $row): ?>
              <tr class="faq-row log-row">
                <td><?= htmlspecialchars(thai_when((string) ($row['time'] ?? ''))) ?></td>
                <td><span class="tag"><?= htmlspecialchars((string) ($row['visitor'] ?? '-')) ?></span></td>
                <td><?= htmlspecialchars((string) ($row['category'] ?: '-')) ?></td>
                <td><div class="cell-q"><?= htmlspecialchars((string) $row['question']) ?></div></td>
                <td><div class="cell-a"><?= htmlspecialchars((string) ($row['answer'] ?? '')) ?></div></td>
                <td><?= !empty($row['matched']) ? '<span class="ok-dot">ตอบได้</span>' : '<span class="no-dot">ไม่พบ</span>' ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
              <tr><td colspan="6" class="hint">ยังไม่มีคำถามจริงในระบบ ลองให้ผู้ใช้ถามจากเว็บ LINE หรือ Facebook</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="chart-card table-card review-panel" data-panel="admin-log" hidden>
      <h3>ประวัติการใช้งานระบบของผู้ดูแล</h3>
      <p class="hint">บันทึกการเข้าสู่ระบบ เพิ่ม แก้ไข ลบคำถาม และจัดการผู้ใช้</p>
      <div class="table-wrap log-table-wrap">
        <table class="faq-table log-table">
          <thead>
            <tr>
              <th style="width:18%">เมื่อไหร่</th>
              <th style="width:18%">ชื่อ</th>
              <th style="width:14%">สิทธิ์</th>
              <th style="width:18%">การกระทำ</th>
              <th style="width:32%">รายละเอียด</th>
            </tr>
          </thead>
          <tbody id="admin-log-body">
            <?php foreach ($adminRows as $arow): ?>
              <?php
                $detail = (array) ($arow['detail'] ?? []);
                $desc = [];
                if (!empty($detail['category'])) { $desc[] = 'หมวด: ' . $detail['category']; }
                if (!empty($detail['question'])) { $desc[] = $detail['question']; }
                if (!empty($detail['target_email'])) { $desc[] = $detail['target_email']; }
                if (!empty($detail['target_name'])) { $desc[] = $detail['target_name']; }
                if (!empty($detail['email']) && ($arow['action'] ?? '') === 'login') { $desc[] = $detail['email']; }
                if (!empty($detail['role'])) { $desc[] = AskLog::roleLabel($detail['role']); }
              ?>
              <tr class="faq-row log-row">
                <td><?= htmlspecialchars(thai_when((string) ($arow['time'] ?? ''))) ?></td>
                <td><?= htmlspecialchars((string) ($arow['admin_name'] ?: $arow['admin_email'] ?? '-')) ?></td>
                <td><span class="tag"><?= htmlspecialchars(AskLog::roleLabel((string) ($arow['admin_role'] ?? ''))) ?></span></td>
                <td><?= htmlspecialchars(AskLog::actionLabel((string) ($arow['action'] ?? ''))) ?></td>
                <td><div class="cell-a"><?= htmlspecialchars(implode(' — ', $desc)) ?></div></td>
              </tr>
            <?php endforeach; ?>
            <?php if ($adminRows === []): ?>
              <tr><td colspan="5" class="hint">ยังไม่มีประวัติการใช้งานระบบ</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php endif; ?>
  </div>

  <div class="coach" id="coach" role="status">
    <span class="coach-ico" aria-hidden="true">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
    </span>
    <span id="coach-text">เริ่มที่แท็บ «ไม่พบคำตอบ» เพื่อดูคำถามที่ควรถูกเพิ่มในคลัง</span>
  </div>

  <script>
    window.SCIGENIE_INSIGHTS = <?= json_encode([
        'summary' => $summary,
        'rows' => $isSuper ? array_slice($rows, 0, 400) : [],
        'base' => $base,
        'isSuper' => $isSuper,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <script src="<?= htmlspecialchars($base) ?>/assets/js/insights.js?v=<?= htmlspecialchars(asset_version('assets/js/insights.js')) ?>"></script>
</body>
</html>
