<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$token = trim((string) ($_GET['t'] ?? $_POST['t'] ?? ''));
$userId = MessengerBot::userFromMenuToken($token);
if ($userId === '') {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SCiGENIE</title></head><body style="font-family:sans-serif;padding:32px;text-align:center;color:#5c4314">ลิงก์เมนูหมดอายุแล้ว กรุณากลับไปที่แชทแล้วเปิดใหม่อีกครั้ง</body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = request_json();
    $question = trim((string) ($input['question'] ?? ''));
    $category = trim((string) ($input['category'] ?? ''));
    json_response(MessengerBot::matchFromWeb($userId, $question, $category));
}

$faq = new FaqService();
$categories = $faq->categories();
$current = trim((string) ($_GET['c'] ?? ''));
$known = [];
foreach ($categories as $cat) {
    $known[(string) $cat['name']] = $cat;
}
if ($current !== '' && !isset($known[$current])) {
    $current = '';
}
$base = app_base_path();
$short = $current !== '' ? (string) (($known[$current]['short'] ?? '') !== '' ? $known[$current]['short'] : $current) : '';
$hint = $current !== '' ? (string) ($known[$current]['hint'] ?? '') : '';
$emoji = $current !== '' ? Categories::emojiByName($current) : '💬';
$phone = (string) config('contact_phone', '043-009-700');

function fb_svg_icon(string $name): string
{
    $icons = [
        'info' => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
        'user-plus' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/>',
        'clipboard' => '<rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>',
        'file-pen' => '<path d="M12.5 22H18a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v9.5"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10.4 12.6a2 2 0 1 1 3 3L8 21l-4 1 1-4Z"/>',
        'id-card' => '<rect width="20" height="14" x="2" y="5" rx="2"/><circle cx="8.5" cy="12" r="2"/><path d="M14 10h4"/><path d="M14 14h4"/>',
        'award' => '<circle cx="12" cy="8" r="6"/><path d="M8.2 13.6 7 22l5-3 5 3-1.2-8.4"/>',
        'briefcase' => '<rect width="20" height="14" x="2" y="7" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>',
        'book' => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5z"/><path d="M8 7h8"/><path d="M8 11h6"/>',
        'coins' => '<circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/>',
        'heart' => '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>',
        'flask' => '<path d="M10 2v7.3a1 1 0 0 1-.2.6L6.5 14a6 6 0 1 0 11 0l-3.3-4.1a1 1 0 0 1-.2-.6V2"/><path d="M8.5 2h7"/>',
        'microscope' => '<path d="M6 18h8"/><path d="M3 22h18"/><path d="M14 22a7 7 0 1 0 0-14h-1"/>',
        'newspaper' => '<path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/>',
        'sparkles' => '<path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/>',
        'school' => '<path d="M14 22v-4a2 2 0 0 0-4 0v4"/><path d="m18 10 4 2v8a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-8l4-2"/><path d="m4 6 8-4 8 4"/>',
        'folder' => '<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>',
        'message' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'send' => '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
        'home' => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
    ];
    $path = $icons[$name] ?? $icons['folder'];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg>';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>SCiGENIE ถาม–ตอบ</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&amp;family=Sarabun:wght@400;600&amp;display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/css/fb-menu.css?v=<?= htmlspecialchars(asset_version('assets/css/fb-menu.css')) ?>">
</head>
<body data-token="<?= htmlspecialchars($token) ?>" data-category="<?= htmlspecialchars($current) ?>">
  <div class="app">
    <header class="header">
      <div class="logo"><img src="<?= htmlspecialchars($base) ?>/assets/img/logo.jpg" alt=""></div>
      <div class="brand">
        <b>SCiGENIE</b>
        <small><?= $current !== '' ? 'หมวด ' . htmlspecialchars($short) : 'ถาม–ตอบ คณะวิทยาศาสตร์ มข.' ?></small>
      </div>
      <?php if ($current !== ''): ?>
        <a class="icon-btn" href="menu.php?t=<?= rawurlencode($token) ?>" title="หมวดทั้งหมด"><?= fb_svg_icon('home') ?></a>
      <?php endif; ?>
    </header>

    <main class="main" id="main">
      <section class="hero">
        <div class="hero-ico" aria-hidden="true"><?= $current !== '' ? htmlspecialchars($emoji) : fb_svg_icon('message') ?></div>
        <div>
          <h1><?= $current !== '' ? htmlspecialchars($short) : 'ถามได้เลยค่ะ' ?></h1>
          <p><?= $current !== '' ? htmlspecialchars($hint !== '' ? $hint : 'พิมพ์คำถามที่ช่องด้านล่างได้เลย') : 'เลือกหมวดที่ต้องการ แล้วพิมพ์คำถามได้เลยค่ะ' ?></p>
        </div>
      </section>

      <div id="answer-box" hidden></div>

      <?php if ($current === ''): ?>
        <div class="section-title"><span>📂</span> เลือกหมวด</div>
        <div class="list" id="cat-list">
          <?php foreach ($categories as $cat): ?>
            <?php
              $name = (string) ($cat['name'] ?? '');
              $label = (string) (($cat['short'] ?? '') !== '' ? $cat['short'] : $name);
              $icon = (string) ($cat['icon'] ?? 'folder');
              $count = (int) ($cat['count'] ?? 0);
              $href = 'menu.php?t=' . rawurlencode($token) . '&c=' . rawurlencode($name);
            ?>
            <a class="row" href="<?= htmlspecialchars($href) ?>">
              <span class="badge"><?= fb_svg_icon($icon) ?></span>
              <span class="row-body">
                <b><?= htmlspecialchars($label) ?></b>
                <small><?= $count ?> คำถาม<?= $cat['hint'] !== '' ? ' · ' . htmlspecialchars((string) $cat['hint']) : '' ?></small>
              </span>
              <span class="chev"><?= fb_svg_icon('send') ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="empty">เลือกหมวดแล้ว พิมพ์คำถามที่ช่องด้านล่างได้เลยค่ะ</p>
      <?php endif; ?>
    </main>

    <form class="composer" id="ask-form" autocomplete="off"<?= $current === '' ? ' hidden' : '' ?>>
      <textarea id="ask-input" rows="1" placeholder="พิมพ์คำถาม เช่น ค่าเทอม เลื่อนสอบ ทุนการศึกษา" maxlength="400"<?= $current === '' ? ' disabled' : '' ?>></textarea>
      <button type="submit" class="send" title="ส่งคำถาม"><?= fb_svg_icon('send') ?></button>
    </form>
    <p class="composer-note"<?= $current === '' ? ' hidden' : '' ?>>ตอบจากคลังคำถามคณะวิทยาศาสตร์ · หากไม่พบโทร <?= htmlspecialchars($phone) ?></p>
  </div>
  <script src="<?= htmlspecialchars($base) ?>/assets/js/fb-menu.js?v=<?= htmlspecialchars(asset_version('assets/js/fb-menu.js')) ?>"></script>
</body>
</html>
