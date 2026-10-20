# -*- coding: utf-8 -*-
"""SCiGENIE Relational Database Design (ER Diagram)."""
from __future__ import annotations

import os
from PIL import Image, ImageDraw, ImageFont

OUT = os.path.join("HR", "SCiGENIE-relational-db-design.png")
W, H = 3600, 2720
BG = (248, 250, 252)
INK = (30, 41, 59)
MUTED = (71, 85, 105)
HEADER_BG = (15, 23, 42)
WHITE = (255, 255, 255)
PK_BG = (254, 249, 195)
FK_BG = (219, 234, 254)
LINE = (51, 65, 85)
ACCENT = (37, 99, 235)

# Table header colors
C_CAT = (14, 116, 144)
C_FAQ = (5, 150, 105)
C_EMB = (124, 58, 237)
C_ADM = (180, 83, 9)
C_AUC = (190, 24, 93)

FONT_PATH = r"C:\Windows\Fonts\cordia.ttc"
FONT_MONO = r"C:\Windows\Fonts\consola.ttf"


def font(size: int, bold: bool = False) -> ImageFont.FreeTypeFont:
    idx = 1 if bold else 0
    try:
        return ImageFont.truetype(FONT_PATH, size, index=idx)
    except Exception:
        return ImageFont.truetype(FONT_PATH, size, index=0)


def mono(size: int) -> ImageFont.FreeTypeFont:
    try:
        return ImageFont.truetype(FONT_MONO, size)
    except Exception:
        return font(size)


def round_box(draw, xy, fill, outline=None, width=3, radius=18):
    draw.rounded_rectangle(xy, radius=radius, fill=fill, outline=outline or fill, width=width)


def text_w(draw, text, fnt):
    b = draw.textbbox((0, 0), text, font=fnt)
    return b[2] - b[0]


def draw_table(draw, x, y, title, header_color, columns, box_w=780):
    """columns: list of (key, name, type) where key in ('PK','FK','PK/FK','UQ','')"""
    row_h = 48
    head_h = 70
    pad = 18
    h = head_h + len(columns) * row_h + 8
    # shadow
    round_box(draw, (x + 6, y + 6, x + box_w + 6, y + h + 6), (226, 232, 240), radius=16)
    # body
    round_box(draw, (x, y, x + box_w, y + h), WHITE, LINE, 3, 16)
    # header
    draw.rounded_rectangle((x, y, x + box_w, y + head_h), radius=16, fill=header_color)
    draw.rectangle((x, y + head_h - 16, x + box_w, y + head_h), fill=header_color)
    draw.text((x + pad, y + 12), title, font=font(42, True), fill=WHITE)

    f_key = mono(22)
    f_name = mono(24)
    f_type = mono(20)
    for i, (key, name, typ) in enumerate(columns):
        cy = y + head_h + i * row_h
        if i % 2 == 0:
            draw.rectangle((x + 2, cy, x + box_w - 2, cy + row_h), fill=(248, 250, 252))
        if key in ("PK", "PK/FK"):
            draw.rectangle((x + 2, cy, x + box_w - 2, cy + row_h), fill=PK_BG)
        elif key == "FK":
            draw.rectangle((x + 2, cy, x + box_w - 2, cy + row_h), fill=FK_BG)

        key_x = x + pad
        if key:
            badge_w = text_w(draw, key, f_key) + 16
            badge_color = (202, 138, 4) if "PK" in key else (37, 99, 235)
            if key == "UQ":
                badge_color = (100, 116, 139)
            round_box(
                draw,
                (key_x, cy + 10, key_x + badge_w, cy + 38),
                badge_color,
                radius=8,
            )
            draw.text((key_x + 8, cy + 10), key, font=f_key, fill=WHITE)
            name_x = key_x + badge_w + 14
        else:
            name_x = key_x + 8

        draw.text((name_x, cy + 10), name, font=f_name, fill=INK)
        tw = text_w(draw, typ, f_type)
        draw.text((x + box_w - pad - tw, cy + 12), typ, font=f_type, fill=MUTED)

    return (x, y, x + box_w, y + h)


def crow_foot(draw, x, y, side="left", many=True, color=LINE):
    """Draw crow's foot or single bar at endpoint."""
    if side == "right":
        # at left edge of right table — foot points left toward line
        if many:
            draw.line((x, y, x + 22, y - 16), fill=color, width=3)
            draw.line((x, y, x + 22, y + 16), fill=color, width=3)
            draw.line((x + 22, y - 16, x + 22, y + 16), fill=color, width=3)
        else:
            draw.line((x + 8, y - 14, x + 8, y + 14), fill=color, width=4)
    else:
        if many:
            draw.line((x, y, x - 22, y - 16), fill=color, width=3)
            draw.line((x, y, x - 22, y + 16), fill=color, width=3)
            draw.line((x - 22, y - 16, x - 22, y + 16), fill=color, width=3)
        else:
            draw.line((x - 8, y - 14, x - 8, y + 14), fill=color, width=4)


def draw_card_icon(draw, x, y, left_many: bool, right_many: bool, color=LINE, scale=1.0):
    """Draw a small horizontal cardinality icon: one/many — one/many. Returns width used."""
    s = scale
    w = int(90 * s)
    # baseline
    draw.line((x, y, x + w, y), fill=color, width=max(2, int(3 * s)))
    # left end
    if left_many:
        draw.line((x, y, x + int(18 * s), y - int(12 * s)), fill=color, width=max(2, int(3 * s)))
        draw.line((x, y, x + int(18 * s), y + int(12 * s)), fill=color, width=max(2, int(3 * s)))
        draw.line(
            (x + int(18 * s), y - int(12 * s), x + int(18 * s), y + int(12 * s)),
            fill=color,
            width=max(2, int(3 * s)),
        )
    else:
        draw.line((x + int(6 * s), y - int(12 * s), x + int(6 * s), y + int(12 * s)), fill=color, width=max(3, int(4 * s)))
    # right end
    if right_many:
        draw.line((x + w, y, x + w - int(18 * s), y - int(12 * s)), fill=color, width=max(2, int(3 * s)))
        draw.line((x + w, y, x + w - int(18 * s), y + int(12 * s)), fill=color, width=max(2, int(3 * s)))
        draw.line(
            (x + w - int(18 * s), y - int(12 * s), x + w - int(18 * s), y + int(12 * s)),
            fill=color,
            width=max(2, int(3 * s)),
        )
    else:
        draw.line(
            (x + w - int(6 * s), y - int(12 * s), x + w - int(6 * s), y + int(12 * s)),
            fill=color,
            width=max(3, int(4 * s)),
        )
    return w


def connect_h(draw, box_a, box_b, ya=None, yb=None, label="", a_many=False, b_many=True):
    """Horizontal relationship between two boxes."""
    ax0, ay0, ax1, ay1 = box_a
    bx0, by0, bx1, by1 = box_b
    ya = ya if ya is not None else (ay0 + ay1) // 2
    yb = yb if yb is not None else (by0 + by1) // 2
    x0, x1 = ax1, bx0
    # line with mid elbow if y differs
    mid = (x0 + x1) // 2
    draw.line((x0, ya, mid, ya), fill=LINE, width=3)
    draw.line((mid, ya, mid, yb), fill=LINE, width=3)
    draw.line((mid, yb, x1, yb), fill=LINE, width=3)
    crow_foot(draw, x0, ya, "left", a_many)
    crow_foot(draw, x1, yb, "right", b_many)
    if label:
        tw = text_w(draw, label, font(28, True))
        lx = mid - tw // 2
        ly = min(ya, yb) - 42
        round_box(draw, (lx - 10, ly - 4, lx + tw + 10, ly + 36), (241, 245, 249), ACCENT, 2, 10)
        draw.text((lx, ly), label, font=font(28, True), fill=ACCENT)


def connect_v(draw, box_a, box_b, label="", a_many=False, b_many=False):
    """Vertical relationship (a above b)."""
    ax0, ay0, ax1, ay1 = box_a
    bx0, by0, bx1, by1 = box_b
    x = (max(ax0, bx0) + min(ax1, bx1)) // 2
    y0, y1 = ay1, by0
    draw.line((x, y0, x, y1), fill=LINE, width=3)
    # top endpoint (one or many)
    if a_many:
        draw.line((x, y0, x - 16, y0 + 22), fill=LINE, width=3)
        draw.line((x, y0, x + 16, y0 + 22), fill=LINE, width=3)
        draw.line((x - 16, y0 + 22, x + 16, y0 + 22), fill=LINE, width=3)
    else:
        draw.line((x - 14, y0 + 10, x + 14, y0 + 10), fill=LINE, width=4)
    # bottom
    if b_many:
        draw.line((x, y1, x - 16, y1 - 22), fill=LINE, width=3)
        draw.line((x, y1, x + 16, y1 - 22), fill=LINE, width=3)
        draw.line((x - 16, y1 - 22, x + 16, y1 - 22), fill=LINE, width=3)
    else:
        draw.line((x - 14, y1 - 10, x + 14, y1 - 10), fill=LINE, width=4)
    if label:
        tw = text_w(draw, label, font(28, True))
        lx = x + 18
        ly = (y0 + y1) // 2 - 18
        round_box(draw, (lx - 8, ly - 4, lx + tw + 8, ly + 36), (241, 245, 249), ACCENT, 2, 10)
        draw.text((lx, ly), label, font=font(28, True), fill=ACCENT)


def main():
    img = Image.new("RGB", (W, H), BG)
    d = ImageDraw.Draw(img)

    # Header
    round_box(d, (40, 30, W - 40, 200), HEADER_BG, radius=24)
    d.text((80, 50), "SCiGENIE — Relational Database Design", font=font(58, True), fill=WHITE)
    d.text(
        (80, 120),
        "Database: scigeniedb  |  MySQL / MariaDB (InnoDB, utf8mb4)  |  คณะวิทยาศาสตร์ มหาวิทยาลัยขอนแก่น",
        font=font(36),
        fill=(148, 163, 184),
    )

    # Legend
    lx, ly = 80, 230
    d.text((lx, ly), "สัญลักษณ์:", font=font(32, True), fill=INK)
    items = [
        (PK_BG, (202, 138, 4), "PK = Primary Key"),
        (FK_BG, (37, 99, 235), "FK = Foreign Key"),
        ((241, 245, 249), (100, 116, 139), "UQ = Unique"),
    ]
    ix = lx + 140
    for fill, outline, label in items:
        round_box(d, (ix, ly + 4, ix + 36, ly + 36), fill, outline, 2, 8)
        d.text((ix + 48, ly + 2), label, font=font(28), fill=MUTED)
        ix += 300

    # Drawn crow's foot legend (ไม่ใช้ตัวอักษรพิเศษ — อ่านไม่ออกในฟอนต์ไทย)
    icon_x = ix + 30
    draw_card_icon(d, icon_x, ly + 20, left_many=False, right_many=True, color=LINE, scale=1.0)
    d.text((icon_x + 105, ly + 2), "= 1 ต่อ หลาย (1:N)", font=font(28), fill=MUTED)
    icon_x2 = icon_x + 340
    draw_card_icon(d, icon_x2, ly + 20, left_many=False, right_many=False, color=LINE, scale=1.0)
    d.text((icon_x2 + 105, ly + 2), "= 1 ต่อ 1", font=font(28), fill=MUTED)
    icon_x3 = icon_x2 + 260
    draw_card_icon(d, icon_x3, ly + 20, left_many=True, right_many=True, color=LINE, scale=1.0)
    d.text((icon_x3 + 105, ly + 2), "= หลาย ต่อ หลาย (M:N)", font=font(28), fill=MUTED)

    # Layout positions
    # Row 1: admin_users ---- admin_user_categories ---- categories
    # Row 2:                                      faqs
    # Row 3:                               faq_embeddings

    box_adm = draw_table(
        d,
        80,
        320,
        "admin_users",
        C_ADM,
        [
            ("PK", "id", "INT UNSIGNED AI"),
            ("UQ", "email", "VARCHAR(191)"),
            ("", "eoffice_username", "VARCHAR(20)"),
            ("", "name", "VARCHAR(191)"),
            ("", "role", "ENUM('super','manager')"),
            ("", "is_active", "TINYINT(1)"),
            ("", "last_login_at", "DATETIME NULL"),
            ("", "created_at", "TIMESTAMP"),
            ("", "updated_at", "TIMESTAMP"),
        ],
        780,
    )

    box_auc = draw_table(
        d,
        1180,
        380,
        "admin_user_categories",
        C_AUC,
        [
            ("PK/FK", "admin_user_id", "INT UNSIGNED"),
            ("PK/FK", "category_id", "INT UNSIGNED"),
        ],
        720,
    )

    box_cat = draw_table(
        d,
        2480,
        320,
        "categories",
        C_CAT,
        [
            ("PK", "id", "INT UNSIGNED AI"),
            ("UQ", "name", "VARCHAR(191)"),
            ("", "short_name", "VARCHAR(80)"),
            ("", "icon", "VARCHAR(40)"),
            ("", "hint", "VARCHAR(191)"),
            ("", "sort_order", "INT"),
            ("", "created_at", "TIMESTAMP"),
            ("", "updated_at", "TIMESTAMP"),
        ],
        780,
    )

    box_faq = draw_table(
        d,
        1800,
        920,
        "faqs",
        C_FAQ,
        [
            ("PK", "id", "INT UNSIGNED AI"),
            ("FK", "category_id", "INT UNSIGNED"),
            ("", "question", "TEXT"),
            ("", "keyword", "VARCHAR(500)"),
            ("", "keyword_back", "VARCHAR(500)"),
            ("", "answer", "TEXT"),
            ("", "created_at", "TIMESTAMP"),
            ("", "updated_at", "TIMESTAMP"),
        ],
        820,
    )

    box_emb = draw_table(
        d,
        1800,
        1580,
        "faq_embeddings",
        C_EMB,
        [
            ("PK/FK", "faq_id", "INT UNSIGNED"),
            ("", "model", "VARCHAR(120)"),
            ("", "vector_json", "MEDIUMTEXT"),
            ("", "content_hash", "VARCHAR(64)"),
            ("", "updated_at", "TIMESTAMP"),
        ],
        820,
    )

    # Relationships
    connect_h(d, box_adm, box_auc, ya=box_adm[1] + 160, yb=box_auc[1] + 95, label="1 : N", a_many=False, b_many=True)
    connect_h(d, box_auc, box_cat, ya=box_auc[1] + 140, yb=box_cat[1] + 160, label="N : 1", a_many=True, b_many=False)

    # categories -> faqs (vertical-ish from bottom of cat to top-right of faq)
    # custom connector from categories bottom to faqs top
    cx = (box_cat[0] + box_cat[2]) // 2
    fx = (box_faq[0] + box_faq[2]) // 2
    cy0 = box_cat[3]
    fy0 = box_faq[1]
    mid_y = (cy0 + fy0) // 2
    d.line((cx, cy0, cx, mid_y), fill=LINE, width=3)
    d.line((cx, mid_y, fx, mid_y), fill=LINE, width=3)
    d.line((fx, mid_y, fx, fy0), fill=LINE, width=3)
    # one at categories
    d.line((cx - 14, cy0 + 10, cx + 14, cy0 + 10), fill=LINE, width=4)
    # many at faqs
    d.line((fx, fy0, fx - 16, fy0 - 22), fill=LINE, width=3)
    d.line((fx, fy0, fx + 16, fy0 - 22), fill=LINE, width=3)
    d.line((fx - 16, fy0 - 22, fx + 16, fy0 - 22), fill=LINE, width=3)
    lbl = "1 : N  (ON DELETE RESTRICT)"
    tw = text_w(d, lbl, font(28, True))
    lx = (cx + fx) // 2 - tw // 2
    round_box(d, (lx - 10, mid_y - 48, lx + tw + 10, mid_y - 8), (241, 245, 249), ACCENT, 2, 10)
    d.text((lx, mid_y - 44), lbl, font=font(28, True), fill=ACCENT)

    connect_v(d, box_faq, box_emb, label="1 : 1  (ON DELETE CASCADE)", a_many=False, b_many=False)

    # M:N note under admin junction
    note1 = "ตารางเชื่อม Many-to-Many (M:N)"
    note2 = "manager ถูกจำกัดสิทธิ์ตามหมวดที่ได้รับมอบหมาย"
    tw1 = text_w(d, note1, font(28, True))
    tw2 = text_w(d, note2, font(26))
    d.text((1180 + (720 - tw1) // 2, box_auc[3] + 16), note1, font=font(28, True), fill=C_AUC)
    d.text((1180 + (720 - tw2) // 2, box_auc[3] + 52), note2, font=font(26), fill=MUTED)

    # External DB note
    ext_y = 2100
    round_box(d, (80, ext_y, 1500, ext_y + 320), WHITE, (148, 163, 184), 3, 16)
    round_box(d, (80, ext_y, 1500, ext_y + 60), (71, 85, 105), radius=16)
    d.rectangle((80, ext_y + 44, 1500, ext_y + 60), fill=(71, 85, 105))
    d.text((110, ext_y + 10), "eoffice.tbluser  (ฐานข้อมูลภายนอก — ไม่ใช่ FK)", font=font(34, True), fill=WHITE)
    d.text((110, ext_y + 85), "เชื่อมโยงเชิงตรรกะกับ admin_users.eoffice_username", font=font(30), fill=MUTED)
    d.text((110, ext_y + 140), "ใช้ตอน Login ด้วย Google / ตรวจสิทธิ์เจ้าหน้าที่", font=font(30), fill=MUTED)
    d.text((110, ext_y + 195), "(อ่านอย่างเดียว — ไม่เก็บใน scigeniedb)", font=font(30), fill=MUTED)
    d.text((110, ext_y + 250), "เส้นประ = ความสัมพันธ์เชิงตรรกะ (ไม่มี Foreign Key)", font=font(28), fill=(100, 116, 139))

    # Dashed logical link from admin_users to external note
    ax = (box_adm[0] + box_adm[2]) // 2
    for yi in range(box_adm[3] + 8, ext_y - 4, 16):
        d.line((ax, yi, ax, min(yi + 8, ext_y - 4)), fill=(148, 163, 184), width=2)
    d.text((ax + 16, (box_adm[3] + ext_y) // 2 - 20), "logical link", font=font(26), fill=(148, 163, 184))

    # Relationship summary box — วาดไอคอนจริง ไม่ใช้ตัวอักษรพิเศษ
    sx, sy = 1680, 2100
    box_h = 400
    round_box(d, (sx, sy, W - 80, sy + box_h), (239, 246, 255), ACCENT, 3, 16)
    d.text((sx + 30, sy + 18), "สรุปความสัมพันธ์ (Cardinality)", font=font(38, True), fill=ACCENT)

    f_left = mono(22)
    f_th = font(30)
    rows = [
        # (left_name, left_many, right_many, right_name, card_label, thai_desc)
        ("categories", False, True, "faqs", "1 : N", "หมวดหนึ่ง มี FAQ ได้หลายรายการ"),
        ("faqs", False, False, "faq_embeddings", "1 : 1", "FAQ หนึ่ง มีเวกเตอร์ค้นหา 1 ชุด"),
        ("admin_users", True, True, "categories", "M : N", "ผ่านตาราง admin_user_categories"),
    ]
    yy = sy + 85
    for left_name, lm, rm, right_name, card, thai in rows:
        # bullet
        d.ellipse((sx + 34, yy + 10, sx + 50, yy + 26), fill=ACCENT)
        # left table name
        x = sx + 66
        d.text((x, yy), left_name, font=f_left, fill=INK)
        x += text_w(d, left_name, f_left) + 16
        # cardinality numbers + drawn icon
        d.text((x, yy), "1" if not lm else "N", font=font(30, True), fill=ACCENT)
        x += 28
        icon_w = draw_card_icon(d, x, yy + 18, left_many=lm, right_many=rm, color=LINE, scale=1.05)
        x += icon_w + 12
        d.text((x, yy), "N" if rm else "1", font=font(30, True), fill=ACCENT)
        x += 28
        d.text((x, yy), right_name, font=f_left, fill=INK)
        x += text_w(d, right_name, f_left) + 20
        # badge card type
        bw = text_w(d, card, mono(18)) + 20
        round_box(d, (x, yy + 2, x + bw, yy + 34), ACCENT, radius=8)
        d.text((x + 10, yy + 4), card, font=mono(18), fill=WHITE)
        x += bw + 18
        d.text((x, yy), thai, font=f_th, fill=MUTED)
        yy += 62

    # Cascade note — แยก 2 บรรทัด ชัดเจน
    yy += 8
    d.ellipse((sx + 34, yy + 10, sx + 50, yy + 26), fill=(180, 83, 9))
    d.text((sx + 66, yy), "Cascade:", font=font(30, True), fill=(180, 83, 9))
    d.text(
        (sx + 180, yy),
        "ลบ FAQ  → ลบ faq_embeddings อัตโนมัติ",
        font=f_th,
        fill=INK,
    )
    yy += 48
    d.ellipse((sx + 34, yy + 10, sx + 50, yy + 26), fill=(180, 83, 9))
    d.text((sx + 66, yy), "Cascade:", font=font(30, True), fill=(180, 83, 9))
    d.text(
        (sx + 180, yy),
        "ลบ admin_users / categories  → ลบแถวใน admin_user_categories",
        font=f_th,
        fill=INK,
    )

    # Footer
    d.text(
        (80, H - 60),
        "สร้างจาก schema จริงใน scigeniedb  |  SCiGENIE FAQ Chatbot System",
        font=font(28),
        fill=MUTED,
    )

    os.makedirs(os.path.dirname(OUT) or ".", exist_ok=True)
    img.save(OUT, "PNG", optimize=True)
    print(f"Saved: {OUT} ({W}x{H})")


if __name__ == "__main__":
    main()
