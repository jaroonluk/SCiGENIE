-- รันในฐานข้อมูลเดิมของ SCiGENIE (ค่า SCIGENIE_DB_NAME)
-- ไม่ต้องสร้างฐานข้อมูลใหม่
-- ใช้เมื่อต้องการให้ Genie ค้นเว็บเหล่านี้หลังจากคลังคำถามไม่มีคำตอบ

CREATE TABLE IF NOT EXISTS web_sources (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(191) NOT NULL,
  url VARCHAR(500) NOT NULL,
  note VARCHAR(255) NOT NULL DEFAULT '',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_web_sources_url (url)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO web_sources (title, url, note, is_active, sort_order) VALUES
('ปฏิทินการศึกษา', 'https://registrar.kku.ac.th/kkustudentpage1/academiccalendar', 'ปฏิทินการศึกษาปริญญาตรีและบัณฑิตศึกษา', 1, 10),
('คู่มือนักศึกษาปริญญาตรี', 'https://heyzine.com/flip-book/5e785aff58.html#page/36', 'คู่มือนักศึกษาระดับปริญญาตรี ปีการศึกษา 2569', 1, 20),
('สำนักบริหารและพัฒนาวิชาการ', 'https://registrar.kku.ac.th/', 'ทะเบียน ลงทะเบียน เอกสารการศึกษา และบริการสำนักบริหารและพัฒนาวิชาการ', 1, 30),
('ระบบทะเบียนมหาวิทยาลัย', 'https://reg.kku.ac.th/', 'ทางเข้าสู่ระบบทะเบียนของมหาวิทยาลัยขอนแก่น', 1, 40),
('เว็บไซต์คณะวิทยาศาสตร์', 'https://sc.kku.ac.th/', 'ข่าว หลักสูตร บริการ และข้อมูลคณะวิทยาศาสตร์ มข.', 1, 50),
('เว็บไซต์มหาวิทยาลัยขอนแก่น', 'https://www.kku.ac.th/', 'ข้อมูลกลางของมหาวิทยาลัยขอนแก่น', 1, 60);
