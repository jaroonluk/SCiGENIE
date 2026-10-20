Add-Type -AssemblyName System.Drawing

$out = Join-Path $PSScriptRoot 'scigenie-architecture.png'
$w = 1800
$h = 1400
$bmp = New-Object System.Drawing.Bitmap $w, $h
$g = [System.Drawing.Graphics]::FromImage($bmp)
$g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
$g.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::ClearTypeGridFit
$g.Clear([System.Drawing.Color]::FromArgb(255, 255, 253, 246))

function Get-Font([float]$size, [bool]$bold=$false) {
  $style = if ($bold) { [System.Drawing.FontStyle]::Bold } else { [System.Drawing.FontStyle]::Regular }
  foreach ($n in @('TH Sarabun New','Sarabun','Cordia New','Tahoma','Segoe UI')) {
    try { return New-Object System.Drawing.Font $n, $size, $style, ([System.Drawing.GraphicsUnit]::Pixel) } catch {}
  }
  return New-Object System.Drawing.Font 'Arial', $size, $style, ([System.Drawing.GraphicsUnit]::Pixel)
}

$title = Get-Font 40 $true
$hFont = Get-Font 24 $true
$bFont = Get-Font 20 $false
$ink = [System.Drawing.Color]::FromArgb(255,28,22,8)
$brown = [System.Drawing.Color]::FromArgb(255,92,67,20)
$gold = [System.Drawing.Color]::FromArgb(255,212,160,23)
$yellow = [System.Drawing.Color]::FromArgb(255,244,196,48)
$cream = [System.Drawing.Color]::FromArgb(255,255,248,231)
$blue = [System.Drawing.Color]::FromArgb(255,230,242,255)
$green = [System.Drawing.Color]::FromArgb(255,230,245,230)
$purple = [System.Drawing.Color]::FromArgb(255,242,232,255)
$inkB = New-Object System.Drawing.SolidBrush $ink
$brownB = New-Object System.Drawing.SolidBrush $brown
$pen = New-Object System.Drawing.Pen $gold, 3
$arrow = New-Object System.Drawing.Pen $brown, 2.5
$arrow.EndCap = [System.Drawing.Drawing2D.LineCap]::ArrowAnchor
$sf = New-Object System.Drawing.StringFormat
$sf.Alignment = 'Center'; $sf.LineAlignment = 'Center'

function Box($g,$x,$y,$bw,$bh,$fill,$text,$font) {
  $path = New-Object System.Drawing.Drawing2D.GraphicsPath
  $r=16
  $path.AddArc($x,$y,$r,$r,180,90)
  $path.AddArc($x+$bw-$r,$y,$r,$r,270,90)
  $path.AddArc($x+$bw-$r,$y+$bh-$r,$r,$r,0,90)
  $path.AddArc($x,$y+$bh-$r,$r,$r,90,90)
  $path.CloseFigure()
  $br = New-Object System.Drawing.SolidBrush $fill
  $g.FillPath($br,$path); $g.DrawPath($script:pen,$path)
  $g.DrawString($text,$font,$script:inkB,(New-Object System.Drawing.RectangleF $x,$y,$bw,$bh),$script:sf)
  $br.Dispose(); $path.Dispose()
}

$g.DrawString('SCiGENIE High-Level AI Architecture', $title, $inkB, (New-Object System.Drawing.RectangleF 0,30,$w,50), $sf)
$g.DrawString('Faculty of Science, Khon Kaen University', $bFont, $brownB, (New-Object System.Drawing.RectangleF 0,80,$w,30), $sf)

# Channels
Box $g 80 140 360 90 $yellow "Channels`nWeb Widget / LINE OA / Portal" $hFont
Box $g 520 140 360 90 $yellow "Admin Dashboard`nKnowledge / AI / Analytics" $hFont
Box $g 960 140 360 90 $yellow "Auth & Identity`nGoogle / KKU SSO / eOffice" $hFont

$g.DrawLine($arrow, 260, 230, 260, 280)
$g.DrawLine($arrow, 700, 230, 700, 280)
$g.DrawLine($arrow, 1140, 230, 900, 280)

# API Gateway
Box $g 300 280 900 80 $cream "API Gateway / Backend Services (Chat, Documents, Admin, Webhooks)" $hFont
$g.DrawLine($arrow, 750, 360, 750, 410)

# RAG Pipeline
Box $g 120 410 700 280 $blue @"
RAG Pipeline
1. Query Understanding
2. Hybrid Retrieval (Dense + Sparse)
3. Re-ranking / Context Packing
4. LLM Generation + Citations
5. Guardrails & Safety Checks
"@ $bFont

Box $g 900 410 700 280 $purple @"
Knowledge Layer
- Document Ingestion (PDF/DOCX/Web)
- Chunking + Metadata Tagging
- Embedding Model
- Vector Store + Relational DB
- FAQ / Policy / Service Content
"@ $bFont

$g.DrawLine($arrow, 820, 550, 900, 550)
$g.DrawLine($arrow, 470, 690, 470, 760)
$g.DrawLine($arrow, 1250, 690, 1250, 760)

Box $g 120 760 700 180 $green @"
LLM & AI Services
- Chat Completions (Streaming)
- Embedding API / Local Fallback
- Answer Verifier / Evaluator
- Prompt & Guardrail Config
"@ $bFont

Box $g 900 760 700 180 $cream @"
Ops & Insights
- Conversation Logs
- Feedback Loop (Up/Down)
- Analytics Dashboard
- Human Escalation Queue
"@ $bFont

$g.DrawString('Design principle: Knowledge-grounded answers + AI retrieval/verification + human oversight', $bFont, $brownB, (New-Object System.Drawing.RectangleF 0, 1020, $w, 40), $sf)
$g.DrawString('CI Color: Champaka Yellow | Faculty of Science, KKU', $bFont, $brownB, (New-Object System.Drawing.RectangleF 0, 1060, $w, 40), $sf)

# Legend boxes bottom
Box $g 200 1120 400 120 $yellow "User Channels" $hFont
Box $g 700 1120 400 120 $blue "AI / RAG Core" $hFont
Box $g 1200 1120 400 120 $green "LLM Services" $hFont

$bmp.Save($out, [System.Drawing.Imaging.ImageFormat]::Png)
$g.Dispose(); $bmp.Dispose()
Write-Host "saved=$out size=$((Get-Item $out).Length)"
