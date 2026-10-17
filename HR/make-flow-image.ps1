Add-Type -AssemblyName System.Drawing

$out = Join-Path $PSScriptRoot 'scigenie-ai-flow.png'
$w = 1600
$h = 2200
$bmp = New-Object System.Drawing.Bitmap $w, $h
$g = [System.Drawing.Graphics]::FromImage($bmp)
$g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
$g.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::ClearTypeGridFit
$g.Clear([System.Drawing.Color]::FromArgb(255, 255, 253, 246))

function Get-ThaiFont([float]$size, [bool]$bold = $false) {
  $style = if ($bold) { [System.Drawing.FontStyle]::Bold } else { [System.Drawing.FontStyle]::Regular }
  foreach ($name in @('TH Sarabun New', 'Sarabun', 'Cordia New', 'Tahoma', 'Segoe UI')) {
    try {
      return New-Object System.Drawing.Font $name, $size, $style, ([System.Drawing.GraphicsUnit]::Pixel)
    } catch {}
  }
  return New-Object System.Drawing.Font 'Arial', $size, $style, ([System.Drawing.GraphicsUnit]::Pixel)
}

$titleFont = Get-ThaiFont 42 $true
$boxFont = Get-ThaiFont 26 $true
$smallFont = Get-ThaiFont 22 $false
$noteFont = Get-ThaiFont 20 $false

$ink = [System.Drawing.Color]::FromArgb(255, 28, 22, 8)
$brown = [System.Drawing.Color]::FromArgb(255, 92, 67, 20)
$gold = [System.Drawing.Color]::FromArgb(255, 212, 160, 23)
$yellow = [System.Drawing.Color]::FromArgb(255, 244, 196, 48)
$cream = [System.Drawing.Color]::FromArgb(255, 255, 248, 231)
$white = [System.Drawing.Color]::White
$green = [System.Drawing.Color]::FromArgb(255, 210, 235, 210)
$orange = [System.Drawing.Color]::FromArgb(255, 255, 230, 210)

$inkBrush = New-Object System.Drawing.SolidBrush $ink
$brownBrush = New-Object System.Drawing.SolidBrush $brown
$goldPen = New-Object System.Drawing.Pen $gold, 3
$arrowPen = New-Object System.Drawing.Pen $brown, 3
$arrowPen.EndCap = [System.Drawing.Drawing2D.LineCap]::ArrowAnchor
$sfCenter = New-Object System.Drawing.StringFormat
$sfCenter.Alignment = [System.Drawing.StringAlignment]::Center
$sfCenter.LineAlignment = [System.Drawing.StringAlignment]::Center

function Draw-RoundBox($g, $x, $y, $bw, $bh, $fill, $text, $font) {
  $path = New-Object System.Drawing.Drawing2D.GraphicsPath
  $r = 18
  $path.AddArc($x, $y, $r, $r, 180, 90)
  $path.AddArc($x + $bw - $r, $y, $r, $r, 270, 90)
  $path.AddArc($x + $bw - $r, $y + $bh - $r, $r, $r, 0, 90)
  $path.AddArc($x, $y + $bh - $r, $r, $r, 90, 90)
  $path.CloseFigure()
  $brush = New-Object System.Drawing.SolidBrush $fill
  $g.FillPath($brush, $path)
  $g.DrawPath($script:goldPen, $path)
  $rect = New-Object System.Drawing.RectangleF $x, $y, $bw, $bh
  $g.DrawString($text, $font, $script:inkBrush, $rect, $script:sfCenter)
  $brush.Dispose()
  $path.Dispose()
}

function Draw-Arrow($g, $x1, $y1, $x2, $y2) {
  $g.DrawLine($script:arrowPen, $x1, $y1, $x2, $y2)
}

$g.DrawString('แผนภาพการทำงานระบบ SCiGENIE', $titleFont, $inkBrush, (New-Object System.Drawing.RectangleF 0, 40, $w, 50), $sfCenter)
$g.DrawString('ตั้งแต่ผู้ใช้ถามจนถึงการตอบด้วย AI', $smallFont, $brownBrush, (New-Object System.Drawing.RectangleF 0, 90, $w, 36), $sfCenter)

$cx = 800
$boxW = 920
$left = $cx - [int]($boxW / 2)

Draw-RoundBox $g $left 150 $boxW 90 $yellow @"
1. ผู้ใช้เลือกหมวด และพิมพ์คำถาม
ไม่จำเป็นต้องพิมพ์ตรงกับคลัง 100%
"@ $boxFont
Draw-Arrow $g $cx 240 $cx 285

Draw-RoundBox $g $left 285 $boxW 90 $cream @"
2. ระบบรับคำถาม และค้นในคลัง FAQ
ของหมวดที่เลือก
"@ $boxFont
Draw-Arrow $g $cx 375 $cx 420

Draw-RoundBox $g $left 420 $boxW 110 $white @"
3. จับคู่ด้วยคีย์เวิร์ด + คำพ้องภาษาไทย
Keyword Matching / Synonym Mapping
หาคำตอบที่ใกล้เคียงจากคำสำคัญ
"@ $boxFont
Draw-Arrow $g $cx 530 $cx 575

Draw-RoundBox $g $left 575 $boxW 120 $cream @"
4. หากคะแนนยังไม่ชัดเจน ใช้ AI เชิงความหมาย
Text Embedding + Semantic Similarity
เปรียบเทียบความหมาย ไม่ใช่แค่ข้อความตรงกัน
"@ $boxFont
Draw-Arrow $g $cx 695 $cx 740

Draw-RoundBox $g $left 740 $boxW 110 $white @"
5. คัดเลือกผู้สมัครคำตอบที่ดีที่สุด
Top Candidates จากคลัง FAQ
"@ $boxFont
Draw-Arrow $g $cx 850 $cx 895

Draw-RoundBox $g $left 895 $boxW 130 $cream @"
6. AI ตรวจสอบความถูกต้องของคำตอบ
Large Language Model (FAQ Verifier)
ตรวจว่าคำตอบตอบเจตนาคำถามได้จริงหรือไม่
"@ $boxFont
Draw-Arrow $g $cx 1025 $cx 1070

Draw-RoundBox $g ($cx - 260) 1070 520 100 $yellow '7. ผ่านการตรวจสอบหรือไม่?' $boxFont
Draw-Arrow $g 540 1170 320 1240
Draw-Arrow $g 1060 1170 1280 1240

Draw-RoundBox $g 80 1240 480 120 $green @"
8ก. ตอบคำถามจากคลัง FAQ
พร้อมคำตอบที่ตรงเจตนา
"@ $boxFont
Draw-RoundBox $g 1040 1240 480 120 $orange @"
8ข. แจ้งว่ายังไม่พบคำตอบ
แนะนำติดต่อเจ้าหน้าที่คณะ
"@ $boxFont

Draw-Arrow $g $cx 1360 $cx 1410
Draw-RoundBox $g $left 1410 $boxW 100 $cream @"
9. บันทึกประวัติการถาม-ตอบ
เพื่อสรุปสถิติ และปรับปรุงคลังคำถาม
"@ $boxFont

$g.DrawString('เทคนิคหลักที่ใช้', $boxFont, $inkBrush, 80, 1560)
$lines = @(
  [string]([char]0x2022) + ' Keyword Matching / Synonym Mapping : จับคำสำคัญและคำพ้องภาษาไทย',
  [string]([char]0x2022) + ' Text Embedding / Semantic Similarity : เข้าใจความหมายใกล้เคียงแม้ข้อความไม่ตรงกัน',
  [string]([char]0x2022) + ' Large Language Model (AI Verifier) : ตรวจสอบว่าคำตอบที่เลือกตอบคำถามได้จริง',
  [string]([char]0x2022) + ' Knowledge Base (FAQ) : คำตอบมาจากข้อมูลที่คณะจัดทำ ไม่สร้างคำตอบมั่ว',
  'ผลลัพธ์ : ผู้ใช้พิมพ์คำถามที่สื่อความหมายเดียวกัน ระบบก็ตอบได้อย่างฉลาดและตรงประเด็น'
)
$y = 1610
foreach ($line in $lines) {
  $g.DrawString($line, $noteFont, $brownBrush, 80, $y)
  $y += 40
}

$g.DrawString('คณะวิทยาศาสตร์ มหาวิทยาลัยขอนแก่น | SCiGENIE', $smallFont, $brownBrush, (New-Object System.Drawing.RectangleF 0, 2100, $w, 40), $sfCenter)

$bmp.Save($out, [System.Drawing.Imaging.ImageFormat]::Png)
$g.Dispose()
$bmp.Dispose()
Write-Host "saved=$out"
Write-Host "size=$((Get-Item -LiteralPath $out).Length)"
