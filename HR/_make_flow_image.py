# -*- coding: utf-8 -*-
"""
SCiGENIE system flow — fonts sized for Word/print (~14pt body, ~16pt headings).
Boxes sized to fit text with comfortable padding.
"""
from __future__ import annotations

import os
from PIL import Image, ImageDraw, ImageFont

OUT = os.path.join("HR", "SCiGENIE-system-flow.png")
OUT_TMP = os.path.join("HR", "SCiGENIE-system-flow-v2.png")

# Target: A4-friendly width at 220 DPI → 14pt/16pt look correct in Word
DPI = 220
PAGE_W_IN = 7.6  # usable width when inserted in Word
W = int(PAGE_W_IN * DPI)

# pt → px at DPI
def pt(n: float) -> int:
    return max(10, int(round(n * DPI / 72.0)))

SIZE_14 = pt(14)   # body
SIZE_16 = pt(16)   # headings / box titles
SIZE_18 = pt(18)   # section titles
SIZE_20 = pt(20)   # main title
SIZE_12 = pt(12)   # footnote

BG = (255, 252, 245)
INK = (28, 22, 8)
MUTED = (75, 60, 38)
BROWN = (92, 67, 20)
YELLOW = (244, 196, 48)
GOLD = (212, 160, 23)
CREAM = (255, 248, 231)
WHITE = (255, 255, 255)
GREEN = (46, 125, 50)
GREEN_BG = (232, 245, 233)
ORANGE = (230, 145, 20)
ORANGE_BG = (255, 243, 224)
RED = (183, 28, 28)
RED_BG = (255, 235, 238)
BLUE = (21, 101, 192)
BLUE_BG = (227, 242, 253)
PURPLE = (106, 27, 154)
PURPLE_BG = (243, 229, 245)

FONT_PATH = r"C:\Windows\Fonts\cordia.ttc"
MARGIN = pt(18)
GAP = pt(10)
PAD = pt(10)


def font(size: int, bold: bool = False) -> ImageFont.FreeTypeFont:
    idx = 1 if bold else 0
    try:
        return ImageFont.truetype(FONT_PATH, size, index=idx)
    except Exception:
        return ImageFont.truetype(FONT_PATH, size, index=0)


def round_box(draw, xy, fill, outline=None, width=2, radius=14):
    draw.rounded_rectangle(xy, radius=radius, fill=fill, outline=outline or fill, width=width)


def wrap_text(draw, text, fnt, max_w):
    if not text:
        return [""]
    lines, cur = [], ""
    for ch in text:
        trial = cur + ch
        if draw.textbbox((0, 0), trial, font=fnt)[2] <= max_w:
            cur = trial
        else:
            if cur:
                lines.append(cur)
            cur = ch
    if cur:
        lines.append(cur)
    return lines


def measure_block(draw, text, fnt, max_w, line_gap=4):
    lines = []
    for para in text.split("\n"):
        lines.extend(wrap_text(draw, para, fnt, max_w) if para else [""])
    h = 0
    widths = []
    for line in lines:
        bb = draw.textbbox((0, 0), line or "ก่า", font=fnt)
        widths.append(bb[2] - bb[0])
        h += (bb[3] - bb[1]) + line_gap
    return lines, h, (max(widths) if widths else 0)


def draw_block(draw, x, y, lines, fnt, fill=INK, line_gap=4):
    y_cur = y
    for line in lines:
        draw.text((x, y_cur), line, font=fnt, fill=fill)
        bb = draw.textbbox((0, 0), line or "ก่า", font=fnt)
        y_cur += (bb[3] - bb[1]) + line_gap
    return y_cur


def center_line(draw, x0, x1, y, text, fnt, fill=INK):
    bb = draw.textbbox((0, 0), text, font=fnt)
    tw = bb[2] - bb[0]
    th = bb[3] - bb[1]
    draw.text((x0 + (x1 - x0 - tw) / 2, y), text, font=fnt, fill=fill)
    return th


def arrow_down(draw, x, y0, y1, color=BROWN):
    draw.line((x, y0, x, y1 - 8), fill=color, width=3)
    draw.polygon([(x - 6, y1 - 10), (x + 6, y1 - 10), (x, y1)], fill=color)


def arrow_right(draw, x0, y, x1, color=BROWN):
    draw.line((x0, y, x1 - 8, y), fill=color, width=3)
    draw.polygon([(x1 - 10, y - 5), (x1 - 10, y + 5), (x1, y)], fill=color)


def main():
    # Probe canvas for measuring
    probe = Image.new("RGB", (W, 200), BG)
    d0 = ImageDraw.Draw(probe)

    f14 = font(SIZE_14)
    f14b = font(SIZE_14, True)
    f16 = font(SIZE_16)
    f16b = font(SIZE_16, True)
    f18b = font(SIZE_18, True)
    f20b = font(SIZE_20, True)
    f12 = font(SIZE_12)

    # ---- Build layout bottom-up with measured heights ----
    content_w = W - 2 * MARGIN

    # Precompute section pieces heights
    # Channels
    ch_titles = ["เว็บไซต์", "Facebook", "LINE"]
    ch_subs = [
        "หน้าต่างสนทนาบนเว็บไซต์\nsci.kku.ac.th",
        "Messenger ของเพจคณะ",
        "บัญชีทางการ LINE ของคณะ",
    ]
    ch_gap = GAP
    ch_w = (content_w - 2 * ch_gap) // 3
    ch_inner = ch_w - 2 * PAD
    ch_body_h = 0
    ch_lines = []
    for sub in ch_subs:
        lines, h, _ = measure_block(d0, sub, f14, ch_inner, 3)
        ch_lines.append(lines)
        ch_body_h = max(ch_body_h, h)
    title_h = d0.textbbox((0, 0), "เว็บไซต์", font=f16b)[3] - d0.textbbox((0, 0), "เว็บไซต์", font=f16b)[1]
    ch_box_h = PAD + title_h + pt(6) + ch_body_h + PAD

    # Steps — 2 rows (3 + 2) so 14pt text fits boxes comfortably
    steps = [
        ("1. รับคำถาม", "รับข้อความจากผู้ใช้ และหมวดที่เลือก (ถ้ามี)"),
        ("2. ค้นคลังคำถาม", "หาคำถาม–คำตอบที่ใกล้เคียงจากฐานข้อมูลของคณะ"),
        ("3. ให้คะแนน", "ยิ่งคะแนนสูง ยิ่งคล้ายคำถามในคลังมากขึ้น"),
        ("4. AI ช่วยตรวจ", "AI ช่วยตัดสินว่าคำตอบตรงกับคำถามหรือไม่"),
        ("5. ตอบกลับ", "ส่งคำตอบ หรือแนะนำให้ติดต่อเจ้าหน้าที่"),
    ]
    step_gap = GAP
    step_w_top = (content_w - 2 * step_gap) // 3
    step_w_bot = (content_w - step_gap) // 2
    step_title_h = 0
    step_body_h = 0
    step_data = []
    for i, (t, s) in enumerate(steps):
        sw = step_w_top if i < 3 else step_w_bot
        inner = sw - 2 * PAD
        tl, th, _ = measure_block(d0, t, f16b, inner, 2)
        bl, bh, _ = measure_block(d0, s, f14, inner, 3)
        step_data.append((tl, bl, sw, th, bh))
        step_title_h = max(step_title_h, th)
        step_body_h = max(step_body_h, bh)
    step_box_h = PAD + step_title_h + pt(8) + step_body_h + PAD

    # AI bullets
    ai_bullets = [
        "• AI ทำหน้าที่เป็น “ผู้ช่วยตัดสิน” ว่าคำตอบจากคลังคำถามของคณะตรงกับคำถามของผู้ใช้หรือไม่",
        "• ระบบค้นหาคำตอบจากข้อมูลที่คณะจัดเตรียมไว้ก่อน แล้วจึงให้ AI ตรวจความเหมาะสม",
        "• หากระบบค้นหาได้คะแนนสูงชัดเจน อาจตอบได้แม้ AI จะใช้งานไม่ได้ชั่วคราว",
        "• หาก AI มั่นใจต่ำ หรือไม่พบคำตอบที่ตรง ระบบจะไม่เดา แต่จะแนะนำให้เลือกหมวดหรือติดต่อเจ้าหน้าที่",
    ]
    ai_inner = content_w - 2 * PAD
    ai_title = "บทบาทของ AI (ไม่แต่งคำตอบเองจากจินตนาการ)"
    _, ai_title_h, _ = measure_block(d0, ai_title, f16b, ai_inner, 3)
    ai_lines_all = []
    ai_body_h = 0
    for b in ai_bullets:
        lines, h, _ = measure_block(d0, b, f14, ai_inner, 3)
        ai_lines_all.append(lines)
        ai_body_h += h + pt(4)
    ai_box_h = PAD + ai_title_h + pt(8) + ai_body_h + PAD

    # Score levels
    levels = [
        (
            RED_BG, RED, "ไม่ตอบจากคลัง", "คะแนนต่ำกว่า 12",
            "ยังไม่คล้ายคำถามในคลังเพียงพอ",
            "• หากยังไม่เลือกหมวด ขอให้เลือกหมวดก่อน\n• หากเลือกหมวดแล้ว แนะนำให้ติดต่อเจ้าหน้าที่",
        ),
        (
            ORANGE_BG, ORANGE, "ตอบได้แบบระมัดระวัง", "คะแนน 12 - 21",
            "พอมีเค้า แต่ยังไม่มั่นใจเต็มที่",
            "• ต้องมี AI ยืนยันว่าตรง (มั่นใจปานกลางหรือสูง)\n• หรือมีสัญญาณชัดว่าเป็นหัวข้อที่รู้จัก\n• หากไม่มีหมวดและไม่มั่นใจ ขอให้เลือกหมวด",
        ),
        (
            GREEN_BG, GREEN, "ตอบได้ (มั่นใจปานกลาง–สูง)", "คะแนน 22 ขึ้นไป",
            "ใกล้เคียงคำถามในคลังอย่างชัดเจน",
            "• คะแนนตั้งแต่ 22 ขึ้นไป = มั่นใจระดับปานกลาง พร้อมตอบ\n• คะแนนตั้งแต่ 40 ขึ้นไป = มั่นใจสูง ตอบได้ชัดเจน\n• AI ช่วยยืนยันเพิ่มเติม หากใช้งานได้",
        ),
    ]
    lv_gap = GAP
    lv_w = (content_w - 2 * lv_gap) // 3
    lv_inner = lv_w - 2 * PAD
    lv_h = 0
    lv_prepared = []
    for bg, edge, title, score, meaning, detail in levels:
        t_lines, th, _ = measure_block(d0, title, f16b, lv_inner, 2)
        s_lines, sh, _ = measure_block(d0, score, f16b, lv_inner, 2)
        m_lines, mh, _ = measure_block(d0, meaning, f14b, lv_inner, 3)
        d_lines, dh, _ = measure_block(d0, detail, f14, lv_inner, 3)
        box_h = PAD + th + pt(6) + sh + pt(10) + mh + pt(6) + dh + PAD
        # score chip padding
        box_h += pt(14)
        lv_h = max(lv_h, box_h)
        lv_prepared.append((bg, edge, t_lines, s_lines, m_lines, d_lines, th, sh, mh, dh))

    # AI criteria rows
    ai_rows = [
        ("AI มั่นใจสูงหรือปานกลาง", "รับผลจาก AI ได้ แล้วใช้คำตอบที่ AI เลือก", GREEN),
        ("AI มั่นใจต่ำ หรือไม่เลือกคำตอบ", "ไม่ตอบตาม AI ระบบอาจใช้คะแนนของตนเองหากสูงพอ หรือไม่ตอบ", ORANGE),
        ("AI ใช้งานไม่ได้ชั่วคราว", "ถอยกลับใช้คะแนนค้นหาของระบบ (หากตั้งแต่ 22 ขึ้นไป มักตอบได้)", BLUE),
    ]
    ai2_title = "เกณฑ์ฝั่ง AI (เมื่อระบบเรียก AI มาช่วยตรวจ)"
    _, ai2_th, _ = measure_block(d0, ai2_title, f16b, ai_inner, 3)
    ai2_row_h = 0
    ai2_lines = []
    for label, desc, col in ai_rows:
        line = f"{label}  —  {desc}"
        lines, h, _ = measure_block(d0, line, f14, ai_inner - pt(28), 3)
        ai2_lines.append((lines, col, h))
        ai2_row_h += h + pt(6)
    ai2_box_h = PAD + ai2_th + pt(8) + ai2_row_h + PAD

    # Outcomes
    outs = [
        (GREEN_BG, GREEN, "ได้คำตอบ", "แสดงคำตอบจากคลังคำถามของคณะ\n(ข้อมูลที่เจ้าหน้าที่ดูแล)"),
        (ORANGE_BG, ORANGE, "ขอให้เลือกหมวด", "ยังไม่มั่นใจว่าตรงเรื่องใด\nขอให้เลือกหมวดเพื่อตอบได้ตรงขึ้น"),
        (RED_BG, RED, "แนะนำติดต่อเจ้าหน้าที่", "ไม่พบคำตอบที่มั่นใจในระบบ\nแจ้งเบอร์โทรศัพท์ / อีเมลของคณะ"),
    ]
    out_w = (content_w - 2 * lv_gap) // 3
    out_inner = out_w - 2 * PAD
    out_h = 0
    out_prepared = []
    for bg, edge, t, s in outs:
        tl, th, _ = measure_block(d0, t, f16b, out_inner, 2)
        sl, sh, _ = measure_block(d0, s, f14, out_inner, 3)
        bh = PAD + th + pt(8) + sh + PAD
        out_h = max(out_h, bh)
        out_prepared.append((bg, edge, tl, sl, th, sh))

    # Estimate total height
    y = MARGIN
    y += pt(56)  # header bar
    y += GAP * 2
    y += SIZE_18 + GAP  # sec1 title
    y += ch_box_h + GAP + pt(28) + GAP  # channels + hub
    y += SIZE_18 + GAP
    y += step_box_h + GAP + step_box_h + GAP * 2
    y += SIZE_18 + GAP
    y += ai_box_h + GAP * 2
    y += SIZE_18 + GAP
    y += pt(36) + GAP  # score intro bar
    y += lv_h + GAP
    y += ai2_box_h + GAP * 2
    y += SIZE_18 + GAP
    y += out_h + GAP * 2
    y += pt(70) + GAP  # summary
    y += SIZE_12 + MARGIN

    H = y + MARGIN
    img = Image.new("RGB", (W, H), BG)
    d = ImageDraw.Draw(img)

    # ========== DRAW ==========
    y = MARGIN
    # Header
    hdr_h = pt(58)
    round_box(d, (MARGIN, y, W - MARGIN, y + hdr_h), (45, 34, 12), radius=16)
    center_line(d, MARGIN, W - MARGIN, y + pt(8), "SCiGENIE — แผนภาพการทำงานของระบบตอบคำถาม", f20b, YELLOW)
    center_line(
        d, MARGIN, W - MARGIN, y + pt(32),
        "คณะวิทยาศาสตร์ มหาวิทยาลัยขอนแก่น  |  อธิบายง่ายสำหรับผู้ที่ไม่เชี่ยวชาญคอมพิวเตอร์",
        f14, CREAM,
    )
    y += hdr_h + GAP * 2

    # --- 1 ---
    d.text((MARGIN, y), "1. ผู้ใช้เข้ามาถามผ่าน 3 ช่องทาง", font=f18b, fill=BROWN)
    y += SIZE_18 + GAP
    ch_colors = [(BLUE, BLUE_BG), ((25, 118, 210), (232, 240, 254)), (GREEN, GREEN_BG)]
    for i, (title, lines) in enumerate(zip(ch_titles, ch_lines)):
        x0 = MARGIN + i * (ch_w + ch_gap)
        outline, fill = ch_colors[i]
        round_box(d, (x0, y, x0 + ch_w, y + ch_box_h), fill, outline, 2, 14)
        center_line(d, x0, x0 + ch_w, y + PAD, title, f16b, outline)
        draw_block(d, x0 + PAD, y + PAD + title_h + pt(6), lines, f14, MUTED, 3)
    y += ch_box_h + GAP
    arrow_down(d, W // 2, y, y + pt(16))
    y += pt(18)
    hub_h = pt(28)
    hub_w = pt(220)
    round_box(d, ((W - hub_w) // 2, y, (W + hub_w) // 2, y + hub_h), YELLOW, GOLD, 2, 12)
    center_line(d, (W - hub_w) // 2, (W + hub_w) // 2, y + pt(5), "ส่งเข้าสู่ระบบ SCiGENIE", f16b, INK)
    y += hub_h + GAP
    arrow_down(d, W // 2, y, y + pt(16))
    y += pt(20)

    # --- 2 ---
    d.text((MARGIN, y), "2. ระบบทำงานทีละขั้น (ใช้คลังคำถามชุดเดียวกันทุกช่องทาง)", font=f18b, fill=BROWN)
    y += SIZE_18 + GAP
    # row 1: steps 1-3
    for i in range(3):
        tl, bl, sw, th, bh = step_data[i]
        x0 = MARGIN + i * (step_w_top + step_gap)
        round_box(d, (x0, y, x0 + sw, y + step_box_h), WHITE, GOLD, 2, 14)
        round_box(d, (x0 + 2, y + 2, x0 + sw - 2, y + PAD + step_title_h + pt(4)), CREAM, CREAM, 1, 10)
        draw_block(d, x0 + PAD, y + PAD, tl, f16b, BROWN, 2)
        draw_block(d, x0 + PAD, y + PAD + step_title_h + pt(8), bl, f14, MUTED, 3)
        if i < 2:
            arrow_right(d, x0 + sw + 1, y + step_box_h // 2, x0 + sw + step_gap - 1)
    y += step_box_h + GAP
    arrow_down(d, W // 2, y, y + pt(14))
    y += pt(16)
    # row 2: steps 4-5 (centered pair)
    row2_w = 2 * step_w_bot + step_gap
    row2_x = MARGIN + (content_w - row2_w) // 2
    for j, i in enumerate((3, 4)):
        tl, bl, sw, th, bh = step_data[i]
        x0 = row2_x + j * (step_w_bot + step_gap)
        round_box(d, (x0, y, x0 + step_w_bot, y + step_box_h), WHITE, GOLD, 2, 14)
        round_box(d, (x0 + 2, y + 2, x0 + step_w_bot - 2, y + PAD + step_title_h + pt(4)), CREAM, CREAM, 1, 10)
        draw_block(d, x0 + PAD, y + PAD, tl, f16b, BROWN, 2)
        draw_block(d, x0 + PAD, y + PAD + step_title_h + pt(8), bl, f14, MUTED, 3)
        if j == 0:
            arrow_right(d, x0 + step_w_bot + 1, y + step_box_h // 2, x0 + step_w_bot + step_gap - 1)
    y += step_box_h + GAP
    arrow_down(d, W // 2, y, y + pt(16))
    y += pt(20)

    # --- 3 ---
    d.text((MARGIN, y), "3. AI เข้ามาช่วยตรงไหน?", font=f18b, fill=BROWN)
    y += SIZE_18 + GAP
    round_box(d, (MARGIN, y, W - MARGIN, y + ai_box_h), PURPLE_BG, PURPLE, 2, 14)
    cy = y + PAD
    d.text((MARGIN + PAD, cy), ai_title, font=f16b, fill=PURPLE)
    cy += ai_title_h + pt(8)
    for lines in ai_lines_all:
        cy = draw_block(d, MARGIN + PAD, cy, lines, f14, INK, 3)
        cy += pt(4)
    y += ai_box_h + GAP
    arrow_down(d, W // 2, y, y + pt(16))
    y += pt(20)

    # --- 4 ---
    d.text((MARGIN, y), "4. เกณฑ์คะแนน: ตอนไหนตอบ / ตอนไหนไม่ตอบ", font=f18b, fill=BROWN)
    y += SIZE_18 + GAP
    bar_h = pt(34)
    round_box(d, (MARGIN, y, W - MARGIN, y + bar_h), CREAM, GOLD, 2, 12)
    center_line(
        d, MARGIN, W - MARGIN, y + pt(7),
        "คะแนนความใกล้เคียง = ระบบเทียบคำถามของผู้ใช้กับคำถามในคลัง ยิ่งคะแนนสูงยิ่งคล้ายกัน",
        f14b, BROWN,
    )
    y += bar_h + GAP

    for i, (bg, edge, t_lines, s_lines, m_lines, d_lines, th, sh, mh, dh) in enumerate(lv_prepared):
        x0 = MARGIN + i * (lv_w + lv_gap)
        round_box(d, (x0, y, x0 + lv_w, y + lv_h), bg, edge, 3, 14)
        cy = y + PAD
        # title centered-ish
        draw_block(d, x0 + PAD, cy, t_lines, f16b, edge, 2)
        cy += th + pt(6)
        # score chip
        chip_h = sh + pt(10)
        round_box(d, (x0 + PAD, cy, x0 + lv_w - PAD, cy + chip_h), WHITE, edge, 2, 10)
        draw_block(d, x0 + PAD + pt(4), cy + pt(4), s_lines, f16b, edge, 2)
        cy += chip_h + pt(8)
        draw_block(d, x0 + PAD, cy, m_lines, f14b, INK, 3)
        cy += mh + pt(6)
        draw_block(d, x0 + PAD, cy, d_lines, f14, MUTED, 3)
    y += lv_h + GAP

    round_box(d, (MARGIN, y, W - MARGIN, y + ai2_box_h), WHITE, PURPLE, 2, 14)
    cy = y + PAD
    d.text((MARGIN + PAD, cy), ai2_title, font=f16b, fill=PURPLE)
    cy += ai2_th + pt(8)
    for lines, col, h in ai2_lines:
        d.ellipse((MARGIN + PAD, cy + pt(4), MARGIN + PAD + pt(12), cy + pt(16)), fill=col)
        draw_block(d, MARGIN + PAD + pt(20), cy, lines, f14, INK, 3)
        cy += h + pt(6)
    y += ai2_box_h + GAP
    arrow_down(d, W // 2, y, y + pt(16))
    y += pt(20)

    # --- 5 ---
    d.text((MARGIN, y), "5. ผลลัพธ์ที่ผู้ใช้ได้รับ", font=f18b, fill=BROWN)
    y += SIZE_18 + GAP
    for i, (bg, edge, tl, sl, th, sh) in enumerate(out_prepared):
        x0 = MARGIN + i * (out_w + lv_gap)
        round_box(d, (x0, y, x0 + out_w, y + out_h), bg, edge, 2, 14)
        cy = y + PAD
        draw_block(d, x0 + PAD, cy, tl, f16b, edge, 2)
        cy += th + pt(8)
        draw_block(d, x0 + PAD, cy, sl, f14, MUTED, 3)
    y += out_h + GAP * 2

    # Footer summary
    sum_h = pt(64)
    round_box(d, (MARGIN, y, W - MARGIN, y + sum_h), (45, 34, 12), radius=14)
    s1 = "สรุปสั้น ๆ: ผู้ใช้ถามจากเว็บไซต์ / Facebook / LINE แล้วระบบค้นคลังคำถาม และให้คะแนน"
    s2 = "จากนั้น AI ช่วยตรวจความตรง หากมั่นใจพอจึงตอบ หากไม่มั่นใจจะไม่เดา และแนะนำเจ้าหน้าที่"
    d.text((MARGIN + PAD, y + pt(10),), s1, font=f14, fill=CREAM)
    d.text((MARGIN + PAD, y + pt(34),), s2, font=f14, fill=YELLOW)
    y += sum_h + GAP

    note = "หมายเหตุ: เกณฑ์คะแนนอ้างอิงจากระบบจริง (คะแนนขั้นต่ำ = 12, ระดับมั่นใจที่ 22 และ 40)  |  ไฟล์ประกอบผลงาน SCiGENIE"
    d.text((MARGIN, y), note, font=f12, fill=MUTED)
    y += SIZE_12 + MARGIN

    # Crop to used height
    img = img.crop((0, 0, W, min(H, y + pt(8))))
    os.makedirs("HR", exist_ok=True)
    for target in (OUT, OUT_TMP):
        try:
            img.save(target, "PNG", optimize=True, dpi=(DPI, DPI))
            print("Saved", target, img.size, f"14pt={SIZE_14}px 16pt={SIZE_16}px @{DPI}dpi")
        except OSError as e:
            print("Skip", target, e)


if __name__ == "__main__":
    main()
