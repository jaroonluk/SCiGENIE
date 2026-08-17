<?php

declare(strict_types=1);

return [
    'app_name' => 'SCiGENIE',
    'app_title' => 'ตอบคำถามอัจฉริยะ คณะวิทยาศาสตร์ มข.',
    'faculty' => 'คณะวิทยาศาสตร์ มหาวิทยาลัยขอนแก่น',
    'sheet_id' => '1OrEFXnfDNQawMcLHqwMoO6FqsbLkFjIgeQbWaUSp-9c',
    'sheet_gid' => '0',
    'sheet_url' => 'https://docs.google.com/spreadsheets/d/1OrEFXnfDNQawMcLHqwMoO6FqsbLkFjIgeQbWaUSp-9c/edit?gid=0#gid=0',
    // วาง URL ของ Google Apps Script หลัง Deploy เป็น Web App เพื่อบันทึกลงชีตจริง
    'apps_script_url' => '',
    'cache_ttl' => 600,
    'google_client_id' => getenv('GOOGLE_CLIENT_ID') ?: '',
    'google_client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
    'db_host' => getenv('SCIGENIE_DB_HOST') ?: '127.0.0.1',
    'db_port' => (int) (getenv('SCIGENIE_DB_PORT') ?: 3306),
    'db_name' => getenv('SCIGENIE_DB_NAME') ?: 'scigeniedb',
    'db_user' => getenv('SCIGENIE_DB_USER') ?: 'root',
    'db_pass' => getenv('SCIGENIE_DB_PASS') ?: '',
    'eoffice_db_host' => getenv('EOFFICE_DB_HOST') ?: '127.0.0.1',
    'eoffice_db_port' => (int) (getenv('EOFFICE_DB_PORT') ?: 3306),
    'eoffice_db_name' => getenv('EOFFICE_DB_NAME') ?: 'eoffice',
    'eoffice_db_user' => getenv('EOFFICE_DB_USER') ?: 'root',
    'eoffice_db_pass' => getenv('EOFFICE_DB_PASS') ?: '',
    // ผู้ดูแลระบบคนแรก เมื่อยังไม่มีรายชื่อใน scigeniedb
    'bootstrap_super_emails' => [
        'jaroonluk@kku.ac.th',
    ],
    // ว่าง = ใช้ origin ของเว็บปัจจุบัน เช่น https://sci.kku.ac.th
    'google_js_origin' => getenv('GOOGLE_JS_ORIGIN') ?: '',
    'google_redirect_uri' => getenv('GOOGLE_REDIRECT_URI') ?: '',
    'line_channel_secret' => getenv('LINE_CHANNEL_SECRET') ?: '',
    'line_channel_access_token' => getenv('LINE_CHANNEL_ACCESS_TOKEN') ?: '',
    'line_webhook_url' => getenv('LINE_WEBHOOK_URL') ?: '',
    'contact_phone' => '043-009-700',
    'contact_email' => 'sc.inbox@kku.ac.th',
];
