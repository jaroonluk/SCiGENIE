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
    'google_client_id' => env_value('GOOGLE_CLIENT_ID'),
    'google_client_secret' => env_value('GOOGLE_CLIENT_SECRET'),
    'db_host' => env_value('SCIGENIE_DB_HOST', '127.0.0.1'),
    'db_port' => (int) (env_value('SCIGENIE_DB_PORT', '3306')),
    'db_name' => env_value('SCIGENIE_DB_NAME', 'scigeniedb'),
    'db_user' => env_value('SCIGENIE_DB_USER', 'root'),
    'db_pass' => env_value('SCIGENIE_DB_PASS'),
    'eoffice_db_host' => env_value('EOFFICE_DB_HOST', '127.0.0.1'),
    'eoffice_db_port' => (int) (env_value('EOFFICE_DB_PORT', '3306')),
    'eoffice_db_name' => env_value('EOFFICE_DB_NAME', 'eoffice'),
    'eoffice_db_user' => env_value('EOFFICE_DB_USER', 'root'),
    'eoffice_db_pass' => env_value('EOFFICE_DB_PASS'),
    // ผู้ดูแลระบบคนแรก เมื่อยังไม่มีรายชื่อใน scigeniedb
    'bootstrap_super_emails' => [
        'jaroonluk@kku.ac.th',
    ],
    // ว่าง = ใช้ origin ของเว็บปัจจุบัน เช่น https://sci.kku.ac.th
    'google_js_origin' => env_value('GOOGLE_JS_ORIGIN'),
    'google_redirect_uri' => env_value('GOOGLE_REDIRECT_URI'),
    'line_channel_secret' => env_value('LINE_CHANNEL_SECRET'),
    'line_channel_access_token' => env_value('LINE_CHANNEL_ACCESS_TOKEN'),
    'line_webhook_url' => env_value('LINE_WEBHOOK_URL'),
    'facebook_page_access_token' => env_value('FACEBOOK_PAGE_ACCESS_TOKEN'),
    'facebook_app_secret' => env_value('FACEBOOK_APP_SECRET'),
    'facebook_verify_token' => env_value('FACEBOOK_VERIFY_TOKEN', 'scigenie-kku-fb-2026'),
    'openai_api_key' => env_value('OPENAI_API_KEY'),
    'openai_model' => env_value('OPENAI_MODEL', 'claude-haiku-4.5'),
    'openai_base_url' => env_value('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    'openai_embedding_model' => env_value('OPENAI_EMBEDDING_MODEL', 'local-hash-v2'),
    'semantic_min_sim' => (float) (env_value('SEMANTIC_MIN_SIM', '0.45')),
    'semantic_bonus_scale' => (float) (env_value('SEMANTIC_BONUS_SCALE', '45')),
    'contact_phone' => '043-009-700',
    'contact_email' => 'sc.inbox@kku.ac.th',
    'no_match_message' => "ขออภัยค่ะ ข้อมูลส่วนนี้ไม่มีในระบบที่ตรวจสอบได้ในขณะนี้\nแนะนำให้ติดต่อเจ้าหน้าที่คณะวิทยาศาสตร์โดยตรง ได้ที่:\n📞 เบอร์โทร: 043-009-700\n• งานบริการการศึกษา 084-600-1149\n• งานบริการวิชาการและวิจัย ต่อ 44523\n• งานสารบรรณ ต่อ 44260\n• ฝ่ายประชาสัมพันธ์ ต่อ 42958\n📧 อีเมล: sc.inbox@kku.ac.th",
];
