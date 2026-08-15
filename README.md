# SCiGENIE

ผู้ช่วยตอบคำถามอัจฉริยะ คณะวิทยาศาสตร์ มหาวิทยาลัยขอนแก่น โทนสีเหลืองจำปา

ดึงคลังคำถามจาก [Google Sheet](https://docs.google.com/spreadsheets/d/1OrEFXnfDNQawMcLHqwMoO6FqsbLkFjIgeQbWaUSp-9c/edit?gid=0#gid=0) แล้วจับคีย์เวิร์ดตามหมวดที่ผู้ใช้เลือก

## เปิดใช้งานด้วย Docker

```bash
docker compose up --build -d
```

จากนั้นเปิด

- หน้าเว็บและวิดเจ็ตถาม-ตอบ: http://localhost:8080/
- หน้าเพิ่มคำถาม: http://localhost:8080/admin/login.php
  - ผู้ใช้: `admin`
  - รหัสผ่านเริ่มต้น: `SciGenie@2569`

หยุดเครื่องด้วย `docker compose down`

## เปิดใช้งานด้วย XAMPP

วางโฟลเดอร์นี้ใน XAMPP แล้วเปิด Apache จากนั้นเข้า

- หน้าเว็บและวิดเจ็ตถาม-ตอบ: `http://localhost/SCiGENIE/`
- หน้าเพิ่มคำถาม: `http://localhost/SCiGENIE/admin/login.php`
  - ผู้ใช้: `admin`
  - รหัสผ่านเริ่มต้น: `SciGenie@2569`

## บันทึกลง Google Sheet

1. เปิดชีต แล้วไปที่ Extensions → Apps Script
2. วางโค้ดจาก `apps-script/Code.gs` แล้ว Deploy เป็น Web app
3. ใส่ URL ของ Web app ใน `includes/config.php` ที่คีย์ `apps_script_url`

ก่อนเชื่อม Apps Script คำถามใหม่จะถูกเก็บที่ `data/faq-local.json` และยังนำไปใช้ตอบได้ทันที
