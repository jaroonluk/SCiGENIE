# -*- coding: utf-8 -*-
"""แผนภาพ SDLC แบบวนซ้ำได้ (Iterative) — ฟอนต์ Sarabun"""
from __future__ import annotations

import math
import os
from PIL import Image, ImageDraw, ImageFont

OUT = os.path.join("HR", "SCiGENIE-SDLC-iterative.png")
FONT_R = os.path.join("HR", "fonts", "Sarabun-Regular.ttf")
FONT_B = os.path.join("HR", "fonts", "Sarabun-Bold.ttf")

W, H = 1600, 1400
BG = (255, 252, 245)
INK = (28, 22, 8)
MUTED = (90, 75, 45)
BROWN = (92, 67, 20)
YELLOW = (244, 196, 48)
GOLD = (212, 160, 23)
WHITE = (255, 255, 255)
CREAM = (255, 248, 231)
BLUE = (25, 118, 210)
GREEN = (46, 125, 50)
ORANGE = (230, 126, 34)
PURPLE = (123, 31, 162)
TEAL = (0, 121, 107)
RED = (183, 28, 28)


def font(size: int, bold: bool = False) -> ImageFont.FreeTypeFont:
    path = FONT_B if bold else FONT_R
    return ImageFont.truetype(path, size)


def round_box(draw, xy, fill, outline=None, width=3, radius=18):
    draw.rounded_rectangle(xy, radius=radius, fill=fill, outline=outline or fill, width=width)


def center_text(draw, cx, cy, text, fnt, fill=INK):
    bb = draw.textbbox((0, 0), text, font=fnt)
    tw, th = bb[2] - bb[0], bb[3] - bb[1]
    draw.text((cx - tw / 2, cy - th / 2), text, font=fnt, fill=fill)


def wrap_center(draw, cx, y, text, fnt, fill, max_w, gap=4):
    # wrap by char for Thai
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
    yy = y
    for line in lines:
        bb = draw.textbbox((0, 0), line, font=fnt)
        tw, th = bb[2] - bb[0], bb[3] - bb[1]
        draw.text((cx - tw / 2, yy), line, font=fnt, fill=fill)
        yy += th + gap
    return yy


def arrow_curve(draw, points, color=BROWN, width=4):
    draw.line(points, fill=color, width=width)
    # arrow head at end
    x1, y1 = points[-2]
    x2, y2 = points[-1]
    ang = math.atan2(y2 - y1, x2 - x1)
    size = 14
    p1 = (x2 - size * math.cos(ang - 0.4), y2 - size * math.sin(ang - 0.4))
    p2 = (x2 - size * math.cos(ang + 0.4), y2 - size * math.sin(ang + 0.4))
    draw.polygon([points[-1], p1, p2], fill=color)


def main():
    img = Image.new("RGB", (W, H), BG)
    d = ImageDraw.Draw(img)

    # Header
    round_box(d, (40, 30, W - 40, 150), (45, 34, 12), radius=22)
    center_text(d, W // 2, 70, "วงจรชีวิตการพัฒนาระบบ SCiGENIE (SDLC แบบวนซ้ำได้)", font(36, True), YELLOW)
    center_text(d, W // 2, 115, "Iterative SDLC  |  คณะวิทยาศาสตร์ มหาวิทยาลัยขอนแก่น", font(22), CREAM)

    # Center hub
    cx, cy = W // 2, 620
    hub_r = 110
    d.ellipse((cx - hub_r, cy - hub_r, cx + hub_r, cy + hub_r), fill=YELLOW, outline=GOLD, width=4)
    center_text(d, cx, cy - 18, "SCiGENIE", font(28, True), INK)
    center_text(d, cx, cy + 18, "วนซ้ำได้", font(22, True), BROWN)

    # 6 stages around circle
    stages = [
        ("1. วิเคราะห์", "Analysis", "ความต้องการ\nและปัญหา", BLUE, (230, 242, 255)),
        ("2. ออกแบบ", "Design", "โครงสร้างระบบ\nฐานข้อมูล Flow", TEAL, (224, 247, 250)),
        ("3. พัฒนา", "Implementation", "เขียนโค้ด\nเชื่อมช่องทาง", PURPLE, (243, 229, 245)),
        ("4. ทดสอบ", "Testing", "ความถูกต้อง\nข้ามหมวด สิทธิ์", ORANGE, (255, 243, 224)),
        ("5. ติดตั้ง", "Deployment", "ขึ้นเครื่องแม่ข่าย\nเปิดบริการจริง", GREEN, (232, 245, 233)),
        ("6. บำรุงรักษา", "Maintenance", "เติม FAQ\nปรับเกณฑ์คะแนน", RED, (255, 235, 238)),
    ]

    # positions: hexagon around center
    n = len(stages)
    radius = 340
    box_w, box_h = 210, 150
    positions = []
    for i in range(n):
        # start from top (-90 deg)
        ang = -math.pi / 2 + i * (2 * math.pi / n)
        x = cx + radius * math.cos(ang)
        y = cy + radius * math.sin(ang)
        positions.append((x, y, ang))

    # draw arrows between stages (clockwise ring)
    for i in range(n):
        x1, y1, a1 = positions[i]
        x2, y2, a2 = positions[(i + 1) % n]
        # point from edge of box toward next
        # use midpoints on the circle slightly outside boxes
        mid_ang = (a1 + a2) / 2
        if i == n - 1:  # wrap around for last→first
            mid_ang = (a1 + a2 + 2 * math.pi) / 2
        # line from current to next along arc-ish chord outside hub
        # start/end just outside boxes toward next
        dx, dy = x2 - x1, y2 - y1
        length = math.hypot(dx, dy) or 1
        ux, uy = dx / length, dy / length
        # shorten so arrow doesn't enter boxes much
        sx = x1 + ux * (box_w * 0.42)
        sy = y1 + uy * (box_h * 0.35)
        ex = x2 - ux * (box_w * 0.42)
        ey = y2 - uy * (box_h * 0.35)
        arrow_curve(d, [(sx, sy), (ex, ey)], BROWN, 4)

    # draw stage boxes
    for i, ((title, eng, detail, edge, fill), (x, y, ang)) in enumerate(zip(stages, positions)):
        x0, y0 = x - box_w / 2, y - box_h / 2
        round_box(d, (x0, y0, x0 + box_w, y0 + box_h), fill, edge, 3, 16)
        # title band
        round_box(d, (x0 + 3, y0 + 3, x0 + box_w - 3, y0 + 48), WHITE, WHITE, 1, 12)
        center_text(d, x, y0 + 26, title, font(22, True), edge)
        center_text(d, x, y0 + 58, eng, font(14), MUTED)
        wrap_center(d, x, y0 + 78, detail.replace("\n", " "), font(16), INK, box_w - 24, 2)

    # Feedback callout (bottom)
    note_y = 1120
    round_box(d, (80, note_y, W - 80, note_y + 200), CREAM, GOLD, 3, 18)
    center_text(d, W // 2, note_y + 32, "เมื่อไหร่ต้องวนกลับ?", font(26, True), BROWN)
    bullets = [
        "• มีคำถามใหม่ / หมวดใหม่ → กลับไปทดสอบ แล้วปรับปรุงคลังความรู้",
        "• เปลี่ยนเกณฑ์คะแนน หรือคำพ้อง → กลับไปทดสอบความแม่นยำ (Regression)",
        "• พบบั๊กหรือช่องทางตอบไม่ตรงกัน → กลับไปพัฒนา / ทดสอบ แล้วติดตั้งใหม่",
        "ลำดับหลัก: วิเคราะห์ → ออกแบบ → พัฒนา → ทดสอบ → ติดตั้ง → บำรุงรักษา → (วนซ้ำ)",
    ]
    yy = note_y + 58
    for b in bullets:
        bb = d.textbbox((0, 0), b, font=font(18 if not b.startswith("ลำดับ") else 18, b.startswith("ลำดับ")))
        d.text((110, yy), b, font=font(18, b.startswith("ลำดับ")), fill=INK if not b.startswith("ลำดับ") else BROWN)
        yy += 32

    # small footer
    d.text((80, H - 40), "SCiGENIE  |  SDLC Iterative Diagram  |  ฟอนต์ Sarabun", font=font(14), fill=MUTED)

    os.makedirs("HR", exist_ok=True)
    img.save(OUT, "PNG", optimize=True)
    print("Saved", OUT, img.size)


if __name__ == "__main__":
    main()
