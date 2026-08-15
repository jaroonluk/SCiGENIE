<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
$base = app_base_path();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>คณะวิทยาศาสตร์ มหาวิทยาลัยขอนแก่น | SCiGENIE</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&amp;family=Sarabun:wght@400;600&amp;display=swap" rel="stylesheet">
  <link rel="icon" href="<?= htmlspecialchars(logo_url()) ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars($base) ?>/assets/css/site.css">
</head>
<body>
  <div class="topbar">
    <span>Faculty of Science, Khon Kaen University</span>
    <span>สีประจำคณะ: เหลืองจำปา • <a href="<?= htmlspecialchars($base) ?>/admin/login.php">ผู้จัดการระบบ</a></span>
  </div>
  <header class="nav">
    <div class="brand">
      <div class="brand-mark">
        <img src="<?= htmlspecialchars(logo_url()) ?>" alt="SCiGENIE">
      </div>
      <div>คณะวิทยาศาสตร์ มข.<small>มหาวิทยาลัยขอนแก่น</small></div>
    </div>
    <nav class="nav-links">
      <a href="#news">ข่าวสาร</a>
      <a href="#services">บริการนักศึกษา</a>
      <a href="#depts">สาขาวิชา</a>
      <a href="https://sc.kku.ac.th/" target="_blank" rel="noopener">เว็บไซต์หลัก</a>
    </nav>
    <a class="btn" href="#ask" onclick="window.SCiGENIE&&window.SCiGENIE.open();">ถาม SCiGENIE</a>
  </header>

  <section class="hero">
    <div class="hero-wrap">
      <div>
        <p style="letter-spacing:.12em;font-size:12px;color:#ffd54a;margin:0 0 8px;">SCiGENIE • ตอบคำถามอัจฉริยะ</p>
        <h1>ผู้ช่วยคณะวิทยาศาสตร์<br>พร้อมตอบทุกคำถาม</h1>
        <p>เลือกหมวด แล้วพิมพ์คำถามได้เลย ระบบจะจับคีย์เวิร์ดจากคลังข้อมูล Google Sheet ของคณะ แล้วตอบอย่างตรงประเด็น</p>
        <p style="margin-top:18px;"><a class="btn" href="#ask" onclick="window.SCiGENIE&&window.SCiGENIE.open();">เริ่มถามคำถาม</a></p>
      </div>
      <div class="hero-card">
        <b>วิธีใช้งานหน้าต่างช่วยเหลือ</b>
        <ol>
          <li>กดปุ่มสีเหลืองจำปา มุมขวาล่าง</li>
          <li>เลือกหมวด เช่น ลงทะเบียน หรือ รับเข้านักศึกษา</li>
          <li>พิมพ์คำถาม หรือกดคำถามแนะนำ</li>
          <li>ระบบจับคีย์เวิร์ดแล้วตอบจากข้อมูลจริงของคณะ</li>
        </ol>
      </div>
    </div>
  </section>

  <section class="section" id="services">
    <h2>บริการที่สอบถามบ่อย</h2>
    <p class="muted">ข้อมูลอ้างอิงจากคลังคำถามงานบริการการศึกษา คณะวิทยาศาสตร์ มข.</p>
    <div class="grid-3">
      <article class="card">
        <div class="ico">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>
        </div>
        <h3>รับเข้านักศึกษา</h3>
        <p class="muted">TCAS, Portfolio, พสวท., สควค. และโครงการต่าง ๆ</p>
      </article>
      <article class="card">
        <div class="ico">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="8" height="4" x="8" y="2" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/></svg>
        </div>
        <h3>ลงทะเบียนเรียน</h3>
        <p class="muted">หน่วยกิต ค่าธรรมเนียม ผ่อนผัน และการลงทะเบียนซ้ำ</p>
      </article>
      <article class="card">
        <div class="ico">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
        </div>
        <h3>สวัสดิการนักศึกษา</h3>
        <p class="muted">สิทธิรักษาพยาบาล บัตรทอง และช่องทางให้คำปรึกษา</p>
      </article>
    </div>
  </section>

  <section class="section" id="news">
    <h2>ข่าวสารคณะวิทยาศาสตร์</h2>
    <div class="news" style="margin-top:16px;">
      <article>
        <div class="thumb"></div>
        <div class="body">
          <b>KKU Science Week 2026</b>
          <p class="muted">ชูวิทยาศาสตร์อัจฉริยะ สร้างแรงบันดาลใจสู่อนาคตที่ยั่งยืน</p>
        </div>
      </article>
      <article>
        <div class="thumb" style="background:linear-gradient(135deg,#ffd54a,#5c4314);"></div>
        <div class="body">
          <b>LAB โรงเรียน</b>
          <p class="muted">เสริมทักษะวิทยาศาสตร์ให้นักเรียนจากโรงเรียนเครือข่าย</p>
        </div>
      </article>
    </div>
  </section>

  <section class="section" id="depts">
    <h2>9 สาขาวิชา คณะวิทย์ มข.</h2>
    <div class="depts">
      <span>ชีววิทยา</span><span>เคมี</span><span>คณิตศาสตร์</span><span>ฟิสิกส์</span>
      <span>จุลชีววิทยา</span><span>ชีวเคมี</span><span>สถิติ</span>
      <span>วิทยาศาสตร์สิ่งแวดล้อม</span><span>วิทยาศาสตร์บูรณาการ</span>
    </div>
  </section>

  <section class="section" id="ask">
    <div class="card">
      <h2>มีคำถามใช่ไหม?</h2>
      <p class="muted">กดไอคอนสนทนาสีเหลืองจำปามุมขวาล่าง เพื่อเปิด SCiGENIE ผู้ช่วยตอบคำถามอัจฉริยะของคณะวิทยาศาสตร์</p>
    </div>
  </section>

  <footer>
    <div class="foot">
      <div>
        <b>คณะวิทยาศาสตร์ มหาวิทยาลัยขอนแก่น</b>
        <p>123 ม.16 ถ.มิตรภาพ ต.ในเมือง อ.เมือง จ.ขอนแก่น 40002</p>
        <p>โทร 043-009-700 • อีเมล sc.inbox@kku.ac.th</p>
      </div>
      <div>
        <b>ลิงก์ด่วน</b>
        <p><a href="https://sc.kku.ac.th/" target="_blank" rel="noopener">sc.kku.ac.th</a></p>
        <p><a href="https://reg.kku.ac.th" target="_blank" rel="noopener">ระบบทะเบียน</a></p>
      </div>
      <div>
        <b>ผู้จัดการระบบ</b>
        <p><a href="<?= htmlspecialchars($base) ?>/admin/login.php">เพิ่มคำถามเข้าคลัง</a></p>
      </div>
    </div>
  </footer>

  <script src="<?= htmlspecialchars($base) ?>/assets/js/widget.js"></script>
</body>
</html>
