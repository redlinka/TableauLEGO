<?php
// 1. ENABLE ERROR REPORTING
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. CHECK LIBRARY
if (!file_exists('fpdf.php')) {
    die("Error: fpdf.php not found.");
}
require('fpdf.php');

// --- PDF CLASS WITH HEADERS/FOOTERS ---
class MosaicPDF extends FPDF {
    public $titleHeader = "Mosaic Assembly Guide";
    public $subHeader = "";

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

    // Header appears on EVERY page
    function Header() {
        $this->SetFont('Arial', 'B', 18);
        $this->Cell(0, 10, $this->titleHeader, 0, 1, 'C');

        if ($this->subHeader) {
            $this->SetFont('Arial', 'I', 12);
            $this->Cell(0, 6, $this->subHeader, 0, 1, 'C');
        }
        $this->Ln(5);

        // Draw a line separator
        $this->SetDrawColor(200, 200, 200);
        $this->Line(10, $this->GetY(), 287, $this->GetY());
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }
}

function generateMosaicManual($filepath) {
    if (!file_exists($filepath)) {
        die("Error: File not found.");
    }

    // 1. PARSE & ANALYZE
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

        // FIX ROTATION
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

        // Build Legend
        $key = $origW . '-' . $origH . '-' . $hex;
        if (!isset($legend[$key])) {
            $legend[$key] = [
                'w' => $origW, 'h' => $origH, 'hex' => $hex,
                'count' => 0, 'id' => 0
            ];
        }
        $legend[$key]['count']++;
    }

    // Assign IDs
    $idCounter = 1;
    foreach ($legend as $k => $v) {
        $legend[$k]['id'] = $idCounter++;
    }

    foreach ($bricks as &$b) {
        $b['legend_id'] = $legend[$b['type_key']]['id'];
    }
    unset($b);

    // 2. SETUP PDF
    $pdf = new MosaicPDF('L', 'mm', 'A4');
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(false);

    // --- PART 1: PART LIST (BOM) ---
    $pdf->subHeader = "Total Size: $maxX x $maxY studs | Total Parts: " . count($bricks);
    $pdf->AddPage();

    $colWidth = 60;
    $rowHeight = 20;
    $colsPerPage = 4;

    $tableWidth = $colsPerPage * $colWidth;
    $pageWidth = 297;
    $startX = ($pageWidth - $tableWidth) / 2;
    $startY = $pdf->GetY();

    $currentCol = 0;
    $currentX = $startX;
    $currentY = $startY;

    foreach ($legend as $type) {
        if ($currentY + $rowHeight > 180) {
            $pdf->AddPage();
            $currentY = $pdf->GetY();
            $currentX = $startX;
            $currentCol = 0;
        }

        // ID
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetXY($currentX, $currentY);
        $pdf->Cell(12, $rowHeight, "#" . $type['id'], 0, 0, 'C');

        // Visual Icon
        $iconBoxW = 25; $iconBoxH = 16;
        $iconX = $currentX + 12; $iconY = $currentY + 2;

        $scaleW = $iconBoxW / $type['w'];
        $scaleH = $iconBoxH / $type['h'];
        $brickScale = min($scaleW, $scaleH);

        $drawW = $type['w'] * $brickScale;
        $drawH = $type['h'] * $brickScale;
        $drawX = $iconX + ($iconBoxW - $drawW) / 2;
        $drawY = $iconY + ($iconBoxH - $drawH) / 2;

        $pdf->SetFillColorHex($type['hex']);
        $pdf->SetDrawColor(0);
        $pdf->Rect($drawX, $drawY, $drawW, $drawH, 'FD');

        // Text
        $pdf->SetXY($currentX + 40, $currentY);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(20, $rowHeight/2, "Size: {$type['w']}x{$type['h']}", 0, 2, 'L');
        $pdf->Cell(20, $rowHeight/2, "Qty: {$type['count']}", 0, 0, 'L');

        $currentCol++;
        if ($currentCol >= $colsPerPage) {
            $currentCol = 0;
            $currentX = $startX;
            $currentY += $rowHeight;
        } else {
            $currentX += $colWidth;
        }
    }


    // --- PART 2: FULL ASSEMBLY MAP (SINGLE VIEW) ---

    // Available drawing area
    $margin = 10;
    $availW = 297 - (2 * $margin);
    $availH = 210 - 45; // Height minus Header/Footer

    // No loops, just one full view
    $pdf->subHeader = "Full Assembly Map";
    $pdf->AddPage();

    $drawStartY = $pdf->GetY() + 2;

    // Calculate Scale to fit the ENTIRE mosaic ($maxX x $maxY)
    $scaleX = $availW / $maxX;
    $scaleY = ($availH - 5) / $maxY;
    $scale = min($scaleX, $scaleY);

    // Center the map
    $gridDrawW = $maxX * $scale;
    $gridDrawH = $maxY * $scale;
    $offsetX = $margin + ($availW - $gridDrawW) / 2;
    $offsetY = $drawStartY + ($availH - $gridDrawH) / 2;

    // DRAW BRICKS
    foreach ($bricks as $b) {
        // No intersection check needed, we are drawing everything

        $relX = $b['x'] * $scale;
        $relY = $b['y'] * $scale;

        $w = $b['w'] * $scale;
        $h = $b['h'] * $scale;

        // White fill, Black text, Black border
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);

        $pdf->Rect($offsetX + $relX, $offsetY + $relY, $w, $h, 'FD');

        // Draw Number if it fits (Adjusted for Full View)
        // If the box is at least 2mm x 2mm, we try to print something
        if ($w > 2 && $h > 2) {
            $pdf->SetTextColor(0);

            // Font size logic
            $fontSize = min($w, $h) * 0.5;
            if ($fontSize > 10) $fontSize = 10;
            if ($fontSize < 3) $fontSize = 3; // Smallest readable font

            $pdf->SetFont('Arial', 'B', $fontSize);

            // Center Text
            $centerX = $offsetX + $relX + ($w / 2);
            $centerY = $offsetY + $relY + ($h / 2);

            $textW = $pdf->GetStringWidth($b['legend_id']);
            $textH = $fontSize / 2.5;

            // Only draw if text actually fits inside width
            if ($textW < ($w * 0.9)) {
                $pdf->SetXY($centerX - ($textW/2), $centerY - ($textH));
                $pdf->Cell($textW, $textH*2, $b['legend_id'], 0, 0, 'C');
            }
        }
    }

    $pdf->Output('I', 'Mosaic_Full_Manual.pdf');
}

// --- EXECUTION ---
if (isset($_GET['file'])) {
    $filename = basename($_GET['file']);
    // UPDATE YOUR FOLDER HERE
    $folder = __DIR__ . '/users/tilings/';
    $filepath = $folder . $filename;
    generateMosaicManual($filepath);
} else {
    echo "No file specified.";
}
?>