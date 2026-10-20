Add-Type -AssemblyName System.Drawing

$root = $PSScriptRoot
$out = Join-Path $root 'SCiGENIE-สถาปัตยกรรมโดยสรุป.png'
$pfcReg = New-Object System.Drawing.Text.PrivateFontCollection
$pfcBold = New-Object System.Drawing.Text.PrivateFontCollection
$pfcReg.AddFontFile((Join-Path $root 'fonts\Sarabun-Regular.ttf'))
$pfcBold.AddFontFile((Join-Path $root 'fonts\Sarabun-Bold.ttf'))
$famReg = $pfcReg.Families[0]
$famBold = $pfcBold.Families[0]

function New-F([float]$px, [string]$weight) {
  $family = if ($weight -eq 'bold') { $script:famBold } else { $script:famReg }
  return New-Object System.Drawing.Font $family, $px, ([System.Drawing.FontStyle]::Regular), ([System.Drawing.GraphicsUnit]::Pixel)
}

function Color-Hex([string]$hex) {
  return [System.Drawing.ColorTranslator]::FromHtml($hex)
}

function New-RoundPath([float]$x, [float]$y, [float]$w, [float]$h, [float]$r) {
  $path = New-Object System.Drawing.Drawing2D.GraphicsPath
  $d = $r * 2
  $path.AddArc($x, $y, $d, $d, 180, 90)
  $path.AddArc($x + $w - $d, $y, $d, $d, 270, 90)
  $path.AddArc($x + $w - $d, $y + $h - $d, $d, $d, 0, 90)
  $path.AddArc($x, $y + $h - $d, $d, $d, 90, 90)
  $path.CloseFigure()
  return $path
}

function Draw-Round($g, [float]$x, [float]$y, [float]$w, [float]$h, [float]$r, $fill, $border, [float]$bw) {
  $path = New-RoundPath $x $y $w $h $r
  $brush = New-Object System.Drawing.SolidBrush $fill
  $g.FillPath($brush, $path)
  if ($bw -gt 0) {
    $pen = New-Object System.Drawing.Pen $border, $bw
    $g.DrawPath($pen, $path)
    $pen.Dispose()
  }
  $brush.Dispose()
  $path.Dispose()
}

$sf = New-Object System.Drawing.StringFormat
$sf.Alignment = [System.Drawing.StringAlignment]::Center
$sf.LineAlignment = [System.Drawing.StringAlignment]::Center
$sf.FormatFlags = [System.Drawing.StringFormatFlags]::NoClip

$W = 3840
$H = 1600
$bmp = New-Object System.Drawing.Bitmap $W, $H
$bmp.SetResolution(144, 144)
$g = [System.Drawing.Graphics]::FromImage($bmp)
$g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
$g.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
$g.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::AntiAlias
$g.Clear((Color-Hex '#FFF9F0'))

$ink = Color-Hex '#2C2416'
$brown = Color-Hex '#6B5018'
$gold = Color-Hex '#C4962A'
$goldDeep = Color-Hex '#8C6810'
$inkB = New-Object System.Drawing.SolidBrush $ink
$brownB = New-Object System.Drawing.SolidBrush $brown
$whiteB = New-Object System.Drawing.SolidBrush ([System.Drawing.Color]::White)
$goldB = New-Object System.Drawing.SolidBrush $goldDeep

$fTitle = New-F 78 bold
$fSub = New-F 42 bold
$fFlow = New-F 36 bold
$fFaculty = New-F 30 regular
$fNo = New-F 40 bold
$fLayer = New-F 40 bold
$fRole = New-F 30 bold
$fItem = New-F 34 regular
$fFoot = New-F 36 bold
$fFoot2 = New-F 34 regular

# top gold rule
$rule = New-Object System.Drawing.SolidBrush (Color-Hex '#E2B33A')
$g.FillRectangle($rule, 0, 0, $W, 14)
$rule.Dispose()

$g.DrawString('คณะวิทยาศาสตร์  มหาวิทยาลัยขอนแก่น', $fFaculty, $brownB, (New-Object System.Drawing.RectangleF 0, 36, $W, 46), $sf)
$g.DrawString('สถาปัตยกรรมโดยสรุป', $fTitle, $inkB, (New-Object System.Drawing.RectangleF 0, 86, $W, 96), $sf)
$g.DrawString('ระบบตอบคำถามอัจฉริยะ SCiGENIE', $fSub, $goldB, (New-Object System.Drawing.RectangleF 0, 182, $W, 58), $sf)
$g.DrawString('สถาปัตยกรรมแบบหลายช่องทาง     ·     ใช้คลังความรู้ชุดเดียว', $fFlow, $brownB, (New-Object System.Drawing.RectangleF 80, 248, ($W - 160), 52), $sf)

$flowText = 'ช่องทางรับข้อความ    →    API/แอป    →    กลไกค้นหา+AI    →    ฐานข้อมูล FAQ    →    บันทึกสถิติ'
Draw-Round $g 280 318 3280 78 39 (Color-Hex '#FFF3D2') (Color-Hex '#E2C56A') 2
$g.DrawString($flowText, $fFlow, $inkB, (New-Object System.Drawing.RectangleF 300, 318, 3240, 78), $sf)

$layers = @(
  @{
    no = '1'; layer = 'ชั้นช่องทาง'; role = 'ช่องทางรับข้อความ'
    head = '#F6C445'; pill = '#FFF6DC'; line = '#E2B12A'
    items = @('widget เว็บ', 'LINE webhook', 'Facebook webhook')
  },
  @{
    no = '2'; layer = 'ชั้นแอป'; role = 'API / แอป'
    head = '#F0B56A'; pill = '#FFF1E2'; line = '#D8903A'
    items = @('PHP', 'Matcher', 'LineBot', 'MessengerBot', 'Auth')
  },
  @{
    no = '3'; layer = 'ชั้นปัญญาประดิษฐ์'; role = 'กลไกค้นหา + AI'
    head = '#B7E0E4'; pill = '#E7F6F7'; line = '#3E8E98'
    items = @("ตรวจความเหมาะสม`nของคำตอบจากคลัง", 'ไม่แต่งคำตอบใหม่')
  },
  @{
    no = '4'; layer = 'ชั้นความรู้'; role = 'ฐานข้อมูล FAQ'
    head = '#C9E8D2'; pill = '#E7F6EC'; line = '#2E8A52'
    items = @('MySQL', 'categories', 'faqs', 'faq_embeddings', 'admin_users')
  },
  @{
    no = '5'; layer = 'ชั้นปฏิบัติการ'; role = 'บันทึกสถิติ'
    head = '#DDD0F2'; pill = '#F4EEFB'; line = '#6A4E9A'
    items = @('AskLog', 'session หมวดของผู้ใช้')
  }
)

$cardY = 428
$cardH = 900
$cardW = 640
$gap = 78
$total = $layers.Count * $cardW + ($layers.Count - 1) * $gap
$startX = [float](($W - $total) / 2)

for ($i = 0; $i -lt $layers.Count; $i++) {
  $L = $layers[$i]
  $x = $startX + $i * ($cardW + $gap)

  if ($i -lt $layers.Count - 1) {
    $ax1 = $x + $cardW + 10
    $ax2 = $x + $cardW + $gap - 10
    $ay = $cardY + 108
    $apen = New-Object System.Drawing.Pen (Color-Hex '#C4962A'), 7
    $apen.EndCap = [System.Drawing.Drawing2D.LineCap]::ArrowAnchor
    $apen.StartCap = [System.Drawing.Drawing2D.LineCap]::Round
    $g.DrawLine($apen, $ax1, $ay, $ax2, $ay)
    $apen.Dispose()
  }

  # shadow
  Draw-Round $g ($x + 8) ($cardY + 10) $cardW $cardH 28 (Color-Hex '#E7D7B8') ([System.Drawing.Color]::Transparent) 0
  Draw-Round $g $x $cardY $cardW $cardH 28 ([System.Drawing.Color]::White) (Color-Hex $L.line) 3

  # header band: rounded top, square bottom
  $headH = 196
  $headColor = Color-Hex $L.head
  $path = New-RoundPath $x $cardY $cardW $headH 28
  $hb = New-Object System.Drawing.SolidBrush $headColor
  $g.FillPath($hb, $path)
  $g.FillRectangle($hb, $x, ($cardY + 80), $cardW, ($headH - 80))
  $hb.Dispose()
  $path.Dispose()

  $badge = 54
  $bx = $x + ($cardW - $badge) / 2
  $by = $cardY + 10
  $g.FillEllipse($whiteB, $bx, $by, $badge, $badge)
  $bpen = New-Object System.Drawing.Pen (Color-Hex $L.line), 3
  $g.DrawEllipse($bpen, $bx, $by, $badge, $badge)
  $bpen.Dispose()
  $g.DrawString($L.no, $fNo, $inkB, (New-Object System.Drawing.RectangleF $bx, ($by + 2), $badge, $badge), $sf)
  $g.DrawString($L.layer, $fLayer, $inkB, (New-Object System.Drawing.RectangleF ($x + 16), ($cardY + 68), ($cardW - 32), 72), $sf)
  $g.DrawString($L.role, $fRole, $inkB, (New-Object System.Drawing.RectangleF ($x + 20), ($cardY + 136), ($cardW - 40), 58), $sf)

  $pillH = 108
  $pillGap = 16
  $pillW = $cardW - 56
  $bodyTop = $cardY + $headH
  $py = $bodyTop + 28
  $px = $x + 28

  foreach ($item in $L.items) {
    Draw-Round $g $px $py $pillW $pillH 22 (Color-Hex $L.pill) (Color-Hex $L.line) 2
    $rect = New-Object System.Drawing.RectangleF ($px + 12), $py, ($pillW - 24), $pillH
    $size = $g.MeasureString($item, $fItem, [int]($pillW - 24))
    if ($size.Width -gt ($pillW - 28) -or $size.Height -gt ($pillH - 8)) {
      Write-Output ("TIGHT [$($L.layer)] '$item' w=$([int]$size.Width) h=$([int]$size.Height) box=$([int]($pillW-24))x$pillH")
    }
    $g.DrawString($item, $fItem, $inkB, $rect, $sf)
    $py += $pillH + $pillGap
  }
}

$footY = 1372
Draw-Round $g 180 $footY 3480 170 28 (Color-Hex '#3E3118') ([System.Drawing.Color]::Transparent) 0
$g.DrawString('ทุกช่องทางใช้คลังความรู้ชุดเดียวกัน', $fFoot, $whiteB, (New-Object System.Drawing.RectangleF 200, ($footY + 18), 3440, 64), $sf)
$g.DrawString('ชั้นปัญญาประดิษฐ์ตรวจความเหมาะสมของคำตอบจากคลังเท่านั้น    ไม่แต่งคำตอบใหม่', $fFoot2, (New-Object System.Drawing.SolidBrush (Color-Hex '#F6E2B0')), (New-Object System.Drawing.RectangleF 200, ($footY + 86), 3440, 58), $sf)

$g.Dispose()
$bmp.Save($out, [System.Drawing.Imaging.ImageFormat]::Png)
$bmp.Dispose()
Write-Output "saved $out"
Write-Output ("bytes=" + (Get-Item $out).Length)
