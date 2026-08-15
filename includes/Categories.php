<?php

declare(strict_types=1);

class Categories
{
    public const MAP = [
        'ทั่วไป' => ['icon' => 'info', 'short' => 'ทั่วไป', 'hint' => 'ที่อยู่ เบอร์โทร เวลาทำการ'],
        'รับเข้านักศึกษา' => ['icon' => 'user-plus', 'short' => 'รับเข้า', 'hint' => 'TCAS Portfolio พสวท. สควค.'],
        'ลงทะเบียน' => ['icon' => 'clipboard', 'short' => 'ลงทะเบียน', 'hint' => 'หน่วยกิต ค่าธรรมเนียม ผ่อนผัน'],
        'การสอบ' => ['icon' => 'file-pen', 'short' => 'การสอบ', 'hint' => 'เลื่อนสอบ ชุดเข้าสอบ ขาดสอบ'],
        'ทะเบียนประวัติ' => ['icon' => 'id-card', 'short' => 'ทะเบียน', 'hint' => 'ย้ายคณะ เทียบโอน ลาพัก'],
        'การสำเร็จการศึกษา' => ['icon' => 'award', 'short' => 'สำเร็จศึกษา', 'hint' => 'เกรด เกียรตินิยม จบการศึกษา'],
        'สหกิจศึกษา' => ['icon' => 'briefcase', 'short' => 'สหกิจ', 'hint' => 'ฝึกงาน ทุนสหกิจ ต่างประเทศ'],
        'บัณฑิตศึกษา' => ['icon' => 'book', 'short' => 'บัณฑิต', 'hint' => 'วิทยานิพนธ์ ปริญญาโท-เอก'],
        'ทุนอุดหนุนการศึกษา' => ['icon' => 'coins', 'short' => 'ทุนการศึกษา', 'hint' => 'ทุนคณะ ทุนมหาวิทยาลัย'],
        'สวัสดิการนักศึกษา' => ['icon' => 'heart', 'short' => 'สวัสดิการ', 'hint' => 'รักษาพยาบาล สุขภาพจิต'],
        'บริการวิชาการ' => ['icon' => 'flask', 'short' => 'บริการวิชาการ', 'hint' => 'LAB โรงเรียน เสริมทักษะ'],
        'บริการเครื่องมือวิทยาศาสตร์' => ['icon' => 'microscope', 'short' => 'เครื่องมือ', 'hint' => 'ห้องปฏิบัติการ เครื่องมือวิทย์'],
        'วารสารวิทยาศาสตร์ มข.' => ['icon' => 'newspaper', 'short' => 'วารสาร', 'hint' => 'ตีพิมพ์ วารสารคณะ'],
        'สัปดาห์วิทยาศาสตร์' => ['icon' => 'sparkles', 'short' => 'สัปดาห์วิทย์', 'hint' => 'กิจกรรมแข่งขัน รางวัล'],
        'ศูนย์บริหารจัดการห้องเรียนวิทยาศาสตร์ในโรงเรียน (วมว.-มข.)' => ['icon' => 'school', 'short' => 'วมว.-มข.', 'hint' => 'SCIUS หอพัก โควต้า'],
    ];

    public static function meta(string $name): array
    {
        return self::MAP[$name] ?? ['icon' => 'folder', 'short' => $name, 'hint' => ''];
    }

    public static function short(string $name): string
    {
        return self::meta($name)['short'];
    }
}
