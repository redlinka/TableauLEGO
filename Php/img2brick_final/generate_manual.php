<?php
// 1. ENABLE ERROR REPORTING
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. CHECK LIBRARY
if (!file_exists('fpdf.php')) {
    die("Error: fpdf.php not found. Please download it from fpdf.org");
}
require('fpdf.php');

class MosaicPDF extends FPDF {
    public $titleHeader = "";
    public $subHeader = "";
    public $isCustomPage = false;

    function SetTextRenderingMode($mode) {
        $this->_out($mode . ' Tr');
    }

    function SetFillColorHex($hex) {
        $hex = ltrim($hex, '#');
        if(strlen($hex) == 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $this->SetFillColor($r, $g, $b);
    }

    function Header() {
        if ($this->isCustomPage) {
            // Minimal Header for the big plan
            $this->SetY(5);
            $this->SetTextColor(0);
            $this->SetFont('Arial', 'B', 16);
            // Center the title on the CURRENT page width
            $this->Cell(0, 10, "Mosaic Plan", 0, 1, 'C');
            return;
        }

        $this->SetDrawColor(0, 102, 204);
        $this->SetLineWidth(1.5);
        $this->Rect(5, 5, 287, 200);

        if (!empty($this->titleHeader)) {
            $this->SetY(10);
            $this->SetTextColor(0);
            $this->SetFont('Arial', 'B', 18);
            $this->Cell(0, 10, $this->titleHeader, 0, 1, 'C');

            if ($this->subHeader) {
                $this->SetFont('Arial', 'I', 12);
                $this->Cell(0, 6, $this->subHeader, 0, 1, 'C');
            }
            $this->Ln(2);

            $this->SetDrawColor(200, 200, 200);
            $this->SetLineWidth(0.2);
            $this->Line(10, $this->GetY(), 287, $this->GetY());
            $this->Ln(5);
        }
    }

    function Footer() {
        // Place footer 15mm from bottom of CURRENT page height
        $this->SetY(-15);
        $this->SetTextColor(0);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }
}

function generateMosaicManual($filepath) {
    if (!file_exists($filepath)) {
        die("Error: File not found.");
    }

    // --- Parse Data ---
    $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $bricks = [];
    $maxX = 0; $maxY = 0;
    $legend = [];

    foreach ($lines as $line) {
        if (strpos($line, ',') === false) continue;
        $parts = explode(',', $line);
        if (count($parts) < 4) continue;
        $meta = explode('/', $parts[0]);
        if (count($meta) < 2) continue;

        $dims = explode('-', $meta[0]);
        $origW = (int)$dims[0];
        $origH = (int)$dims[1];
        $hex   = $meta[1];
        $rot = (int)$parts[1];
        $x   = (int)$parts[2];
        $y   = (int)$parts[3];

        $isVertical = ($rot == 90 || $rot == 270 || $rot == 1 || $rot == 3);
        $finalW = $isVertical ? $origH : $origW;
        $finalH = $isVertical ? $origW : $origH;

        $bricks[] = [
            'w' => $finalW, 'h' => $finalH,
            'hex' => $hex, 'x' => $x, 'y' => $y,
            'type_key' => $origW . '-' . $origH . '-' . $hex
        ];

        if (($x + $finalW) > $maxX) $maxX = $x + $finalW;
        if (($y + $finalH) > $maxY) $maxY = $y + $finalH;

        $key = $origW . '-' . $origH . '-' . $hex;
        if (!isset($legend[$key])) {
            $legend[$key] = [
                'w' => $origW, 'h' => $origH, 'hex' => $hex,
                'count' => 0, 'id' => 0
            ];
        }
        $legend[$key]['count']++;
    }

    $idCounter = 1;
    foreach ($legend as $k => $v) { $legend[$k]['id'] = $idCounter++; }
    foreach ($bricks as &$b) { $b['legend_id'] = $legend[$b['type_key']]['id']; }
    unset($b);

    // --- Setup PDF ---
    $pdf = new MosaicPDF('L', 'mm', 'A4');
    $pdf->SetTitle('Lego Mosaic Guide - ' . basename($filepath), true);
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(false);

    // --- Cover Page ---
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetXY(12, 12);
    $pdf->Cell(50, 10, "Date: " . date("d/m/Y"), 0, 1, 'L');

    // Stylized Titles
    $colors = [[220, 0, 0], [0, 85, 191], [255, 205, 3], [35, 120, 65], [255, 255, 255]];
    $cIdx = 0;

    $pdf->SetTextRenderingMode(2);
    $pdf->SetLineWidth(0.4);
    $pdf->SetDrawColor(0, 0, 0);

    // Title 1
    $title1 = "TableauLEGO";
    $pdf->SetFont('Arial', '', 30);
    $w1 = $pdf->GetStringWidth($title1);
    $x1 = (297 - $w1) / 2;
    $y1 = 80;
    $pdf->SetXY($x1, $y1);
    for ($i = 0; $i < strlen($title1); $i++) {
        $char = $title1[$i];
        if ($char == ' ') { $pdf->Cell($pdf->GetStringWidth(' '), 15, ' ', 0, 0); continue; }
        $rgb = $colors[$cIdx % count($colors)];
        $pdf->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
        $pdf->Cell($pdf->GetStringWidth($char), 15, $char, 0, 0);
        $cIdx++;
    }

    // Title 2
    $title2 = "Mosaic Builder's Guide";
    $pdf->SetFont('Arial', '', 40);
    $w2 = $pdf->GetStringWidth($title2);
    $x2 = (297 - $w2) / 2;
    $y2 = 95;
    $pdf->SetXY($x2, $y2);
    for ($i = 0; $i < strlen($title2); $i++) {
        $char = $title2[$i];
        if ($char == ' ') { $pdf->Cell($pdf->GetStringWidth(' '), 15, ' ', 0, 0); continue; }
        $rgb = $colors[$cIdx % count($colors)];
        $pdf->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
        $pdf->Cell($pdf->GetStringWidth($char), 15, $char, 0, 0);
        $cIdx++;
    }
    $pdf->SetTextRenderingMode(0);

    // --- Part List Page ---
    $pdf->titleHeader = "Part List";
    $pdf->subHeader = "Total Size: $maxX x $maxY studs | Total Parts: " . count($bricks);
    $pdf->AddPage();

    $colWidth = 65; $rowHeight = 22; $colsPerPage = 4;
    $tableWidth = $colsPerPage * $colWidth;
    $startX = (297 - $tableWidth) / 2;
    $startY = $pdf->GetY();
    $currentCol = 0; $currentX = $startX; $currentY = $startY;

    foreach ($legend as $type) {
        if ($currentY + $rowHeight > 180) {
            $pdf->AddPage();
            $currentY = $pdf->GetY();
            $currentX = $startX;
            $currentCol = 0;
        }

        $pdf->SetDrawColor(200, 200, 200);
        $pdf->SetLineWidth(0.2);
        $pdf->Rect($currentX, $currentY, $colWidth, $rowHeight);

        $pdf->SetTextColor(0);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetXY($currentX, $currentY);
        $pdf->Cell(12, $rowHeight, "#" . $type['id'], 0, 0, 'C');

        $iconBoxW = 25; $iconBoxH = 16;
        $iconX = $currentX + 12;
        $iconY = $currentY + ($rowHeight - $iconBoxH) / 2;

        $scaleW = $iconBoxW / $type['w'];
        $scaleH = $iconBoxH / $type['h'];
        $finalScale = min(min($scaleW, $scaleH), 3.5);

        $drawW = $type['w'] * $finalScale;
        $drawH = $type['h'] * $finalScale;
        $drawX = $iconX + ($iconBoxW - $drawW) / 2;
        $drawY = $iconY + ($iconBoxH - $drawH) / 2;

        $pdf->SetFillColorHex($type['hex']);
        $pdf->SetDrawColor(0);
        $pdf->SetLineWidth(0.2);
        $pdf->Rect($drawX, $drawY, $drawW, $drawH, 'FD');

        $pdf->SetXY($currentX + 40, $currentY);
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetXY($currentX + 38, $currentY + 4);
        $pdf->Cell(25, 5, "Size: {$type['w']}x{$type['h']}", 0, 1, 'L');
        $pdf->SetXY($currentX + 38, $currentY + 11);
        $pdf->Cell(25, 5, "Qty: {$type['count']}", 0, 0, 'L');

        $currentCol++;
        if ($currentCol >= $colsPerPage) {
            $currentCol = 0;
            $currentX = $startX; $currentY += $rowHeight;
        } else {
            $currentX += $colWidth;
        }
    }

    // --- Dynamic Assembly Map ---
    $pdf->isCustomPage = true;

    // We force a specific size per stud to ensure it looks good.
    // 5mm per stud is usually a good balance for readability.
    $studSize = 5;

    //Calculate Content Size
    $contentW = $maxX * $studSize;
    $contentH = $maxY * $studSize;

    //Define Margins
    $marginLR = 10;
    $marginTop = 20;
    $marginBot = 20;

    //Calculate Final Page Dimensions
    $finalPageW = $contentW + ($marginLR * 2);
    $finalPageH = $contentH + $marginTop + $marginBot;

    //Create the Page (Orientation based on W vs H)
    $orientation = ($finalPageW > $finalPageH) ? 'L' : 'P';
    $pdf->AddPage($orientation, array($finalPageW, $finalPageH));

    // Draw Content
    // We offset by the top margin and left margin.
    // Since page size is calculated EXACTLY for this content + margins, centering happens automatically.
    $offsetX = $marginLR;
    $offsetY = $marginTop;

    foreach ($bricks as $b) {
        $relX = $b['x'] * $studSize;
        $relY = $b['y'] * $studSize;
        $w = $b['w'] * $studSize;
        $h = $b['h'] * $studSize;

        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);
        $pdf->Rect($offsetX + $relX, $offsetY + $relY, $w, $h, 'FD');

        // Text Logic
        if ($w > 2 && $h > 2) {
            $pdf->SetTextColor(0);
            $fontSize = min($w, $h) * 0.6;
            if ($fontSize > 12) $fontSize = 12;
            if ($fontSize < 2.5) $fontSize = 2.5;

            $pdf->SetFont('Arial', 'B', $fontSize);
            $centerX = $offsetX + $relX + ($w / 2);
            $centerY = $offsetY + $relY + ($h / 2);
            $textW = $pdf->GetStringWidth($b['legend_id']);
            $textH = $fontSize / 2.5;

            if ($textW < ($w * 0.95)) {
                $pdf->SetXY($centerX - ($textW/2), $centerY - ($textH));
                $pdf->Cell($textW, $textH*2, $b['legend_id'], 0, 0, 'C');
            }
        }
    }

    $pdf->Output('I', 'Mosaic_Full_Manual.pdf');
}

// --- Execution ---
if (isset($_GET['file'])) {
    $filename = basename($_GET['file']);
    $folder = __DIR__ . '/users/tilings/';
    $filepath = $folder . $filename;
    generateMosaicManual($filepath);
} else {
    echo "No file specified.";
}
?>