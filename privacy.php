<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$base = app_base_path();
$updated = '5 ตุลาคม 2569';
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>นโยบายความเป็นส่วนตัว | SCiGENIE</title>
  <meta name="description" content="นโยบายความเป็นส่วนตัวของระบบตอบคำถาม SCiGENIE คณะวิทยาศาสตร์ มหาวิทยาลัยขอนแก่น">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&amp;family=Sarabun:wght@400;600&amp;display=swap" rel="stylesheet">
  <link rel="icon" href="assets/img/logo.jpg">
  <link rel="stylesheet" href="assets/css/site.css">
  <style>
    .legal {
      max-width: 820px;
      margin: 0 auto;
      padding: 32px 24px 64px;
    }
    .legal h1 {
      font-size: 1.75rem;
      margin: 0 0 8px;
      color: var(--sc-ink);
    }
    .legal .lead {
      color: var(--sc-muted);
      margin: 0 0 28px;
      line-height: 1.6;
    }
    .legal h2 {
      font-size: 1.15rem;
      margin: 28px 0 10px;
      color: var(--sc-brown);
    }
    .legal p, .legal li {
      line-height: 1.7;
      color: var(--sc-ink);
      font-size: 15px;
    }
    .legal ul { padding-left: 1.25rem; margin: 8px 0 0; }
    .legal li { margin: 6px 0; }
    .legal .meta {
      font-size: 13px;
      color: var(--sc-muted);
      margin-bottom: 24px;
    }
    .legal article {
      background: #fff;
      border: 1px solid var(--sc-line);
      border-radius: 16px;
      padding: 28px 28px 36px;
    }
    .legal .contact a { color: var(--sc-amber); text-decoration: underline; }
  </style>
</head>
<body>
  <div class="topbar">
    <span>Faculty of Science, Khon Kaen University</span>
    <span>อัปเดตล่าสุด: <?= htmlspecialchars($updated) ?></span>
  </div>
  <header class="nav">
    <a class="brand" href="<?= htmlspecialchars($base) ?>/">
      <div class="brand-mark">
        <img src="<?= htmlspecialchars(logo_url()) ?>" alt="SCiGENIE">
      </div>
      <div>SCiGENIE<small>นโยบายความเป็นส่วนตัว</small></div>
    </a>
    <nav class="nav-links">
      <a href="<?= htmlspecialchars($base) ?>/">หน้าหลัก</a>
      <a href="https://sc.kku.ac.th/" target="_blank" rel="noopener">เว็บไซต์คณะ</a>
    </nav>
  </header>

  <main class="legal">
    <article>
      <h1>นโยบายความเป็นส่วนตัว</h1>
      <p class="lead">
        นโยบายนี้ใช้กับบริการตอบคำถามอัจฉริยะ <b>SCiGENIE</b>
        ของคณะวิทยาศาสตร์ มหาวิทยาลัยขอนแก่น ซึ่งให้บริการผ่านเว็บไซต์ Facebook Messenger และ LINE
      </p>
      <p class="meta">มีผลบังคับใช้ตั้งแต่วันที่ <?= htmlspecialchars($updated) ?></p>

      <h2>1. ผู้ควบคุมข้อมูล</h2>
      <p>
        คณะวิทยาศาสตร์ มหาวิทยาลัยขอนแก่น<br>
        123 หมู่ 16 ถนนมิตรภาพ ตำบลในเมือง อำเภอเมือง จังหวัดขอนแก่น 40002<br>
        อีเมล: <a href="mailto:sc.inbox@kku.ac.th">sc.inbox@kku.ac.th</a> · โทร 043-009-700
      </p>

      <h2>2. ข้อมูลที่เก็บรวบรวม</h2>
      <p>ระบบอาจเก็บข้อมูลเท่าที่จำเป็นต่อการให้บริการ ดังนี้</p>
      <ul>
        <li>ข้อความคำถามและคำตอบที่เกี่ยวข้องกับการสอบถามผ่านระบบ</li>
        <li>หมวดคำถามที่ผู้ใช้เลือก (ถ้ามี)</li>
        <li>รหัสผู้ใช้จากช่องทางที่ใช้งาน เช่น Facebook Page-Scoped ID หรือ LINE User ID ซึ่งไม่ใช่ชื่อบัญชีสาธารณะโดยตรง</li>
        <li>ข้อมูลการใช้งานในระดับสรุป เช่น วันเวลาที่สอบถาม และช่องทางที่ใช้ (เว็บ / Facebook / LINE)</li>
        <li>สำหรับผู้ดูแลระบบ: อีเมลบัญชี Google ที่ใช้เข้าสู่ระบบจัดการคลังคำถาม</li>
      </ul>
      <p>ระบบไม่ได้มีวัตถุประสงค์ในการเก็บข้อมูลส่วนบุคคลที่มีความอ่อนไหว เช่น เลขบัตรประชาชน หรือข้อมูลสุขภาพ โดยไม่จำเป็นต่อการตอบคำถาม</p>

      <h2>3. วัตถุประสงค์ในการใช้ข้อมูล</h2>
      <ul>
        <li>ตอบคำถามจากคลังข้อมูล FAQ ของคณะวิทยาศาสตร์</li>
        <li>จดจำหมวดคำถามชั่วคราวระหว่างการสนทนา เพื่อให้ตอบได้ตรงขึ้น</li>
        <li>ปรับปรุงคุณภาพการตอบคำถาม และวิเคราะห์สถิติการใช้งานในภาพรวม</li>
        <li>ดูแลความมั่นคงปลอดภัยของระบบ และป้องกันการใช้งานในทางที่มิชอบ</li>
      </ul>

      <h2>4. การเปิดเผยข้อมูลแก่บุคคลที่สาม</h2>
      <p>
        คณะฯ ไม่ขายหรือให้เช่าข้อมูลส่วนบุคคลของผู้ใช้
        อาจมีการส่งข้อมูลที่จำเป็นไปยังผู้ให้บริการโครงสร้างพื้นฐานหรือผู้ให้บริการ AI
        ที่ใช้ในการประมวลผลคำถามภายใต้การควบคุมของคณะฯ
        รวมถึงแพลตฟอร์ม Meta (Facebook Messenger) และ LINE ตามเงื่อนไขของแต่ละแพลตฟอร์ม
      </p>

      <h2>5. การเก็บรักษาข้อมูล</h2>
      <p>
        บันทึกคำถามและการใช้งานจะถูกเก็บไว้เท่าที่จำเป็นต่อการให้บริการ ปรับปรุงระบบ
        และการตรวจสอบภายใน เมื่อหมดความจำเป็น ข้อมูลจะถูกลบหรือทำให้ระบุตัวตนไม่ได้ตามขั้นตอนของคณะฯ
      </p>

      <h2>6. สิทธิของเจ้าของข้อมูล</h2>
      <p>ท่านสามารถขอเข้าถึง แก้ไข หรือลบข้อมูลที่เกี่ยวข้องกับการใช้งาน SCiGENIE ได้ โดยติดต่อช่องทางในข้อ 8</p>

      <h2>7. การคุ้มครองข้อมูล</h2>
      <p>
        คณะฯ ใช้มาตรการทางเทคนิคและองค์กรที่เหมาะสม เช่น การจำกัดสิทธิ์เข้าถึงระบบของผู้ดูแล
        และการสื่อสารผ่านช่องทาง HTTPS เพื่อลดความเสี่ยงต่อการเข้าถึงข้อมูลโดยไม่ได้รับอนุญาต
      </p>

      <h2>8. ช่องทางติดต่อ</h2>
      <p class="contact">
        หากมีข้อสงสัยเกี่ยวกับนโยบายนี้ หรือต้องการใช้สิทธิเกี่ยวกับข้อมูลส่วนบุคคล กรุณาติดต่อ<br>
        อีเมล: <a href="mailto:sc.inbox@kku.ac.th">sc.inbox@kku.ac.th</a><br>
        โทร: 043-009-700<br>
        เว็บไซต์คณะ: <a href="https://sc.kku.ac.th/" target="_blank" rel="noopener">https://sc.kku.ac.th/</a>
      </p>

      <h2>9. การเปลี่ยนแปลงนโยบาย</h2>
      <p>
        คณะฯ อาจปรับปรุงนโยบายนี้เป็นครั้งคราว โดยจะเผยแพร่ฉบับล่าสุดที่หน้านี้
        การใช้งานบริการต่อไปหลังจากมีการอัปเดต ถือว่าท่านรับทราบนโยบายฉบับที่มีผลใช้บังคับ
      </p>
    </article>
  </main>

  <footer>
    <div class="foot">
      <div>
        <b>คณะวิทยาศาสตร์ มหาวิทยาลัยขอนแก่น</b>
        <p>SCiGENIE · ระบบตอบคำถามอัจฉริยะ</p>
      </div>
      <div>
        <b>ลิงก์</b>
        <p><a href="<?= htmlspecialchars($base) ?>/">กลับหน้าหลัก</a></p>
        <p><a href="<?= htmlspecialchars($base) ?>/privacy.php">นโยบายความเป็นส่วนตัว</a></p>
      </div>
    </div>
  </footer>
</body>
</html>
