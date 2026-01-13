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
        $this->Ln(5); // Space after header

        // Draw a line separator
        $this->SetDrawColor(200, 200, 200);
        $this->Line(10, $this->GetY(), 287, $this->GetY());
        $this->Ln(5); // Margin before content starts
    }

    // Footer with Page Number
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

    // Map IDs to bricks
    foreach ($bricks as &$b) {
        $b['legend_id'] = $legend[$b['type_key']]['id'];
    }
    unset($b);

    // 2. SETUP PDF
    $pdf = new MosaicPDF('L', 'mm', 'A4');
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(false); // We handle breaks manually to avoid breaking drawings

    // --- PART 1: PART LIST (BOM) ---
    // Update Header for this section
    $pdf->subHeader = "Total Size: $maxX x $maxY studs | Total Parts: " . count($bricks);
    $pdf->AddPage();

    // BOM Layout Settings
    $colWidth = 60; // Wider columns for better spacing
    $rowHeight = 20;
    $colsPerPage = 4;

    // Calculate centering: Total table width vs Page Width
    $tableWidth = $colsPerPage * $colWidth;
    $pageWidth = 297; // A4 Landscape
    $startX = ($pageWidth - $tableWidth) / 2;
    $startY = $pdf->GetY();

    $currentCol = 0;
    $currentX = $startX;
    $currentY = $startY;

    foreach ($legend as $type) {
        // Check if we need a new page
        if ($currentY + $rowHeight > 180) {
            $pdf->AddPage();
            $currentY = $pdf->GetY();
            $currentX = $startX;
            $currentCol = 0;
        }

        // 1. Draw The "Invisible Box" Content
        // ID
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetXY($currentX, $currentY);
        $pdf->Cell(12, $rowHeight, "#" . $type['id'], 0, 0, 'C'); // No border

        // 2. Draw The Scaled Brick "Icon"
        // We define a drawing area of 25mm x 16mm inside the cell
        $iconBoxW = 25;
        $iconBoxH = 16;
        $iconX = $currentX + 12;
        $iconY = $currentY + 2; // slight padding top

        // Calculate Scale to fit brick inside icon box
        $scaleW = $iconBoxW / $type['w'];
        $scaleH = $iconBoxH / $type['h'];
        $brickScale = min($scaleW, $scaleH);

        // Center the brick in the icon box
        $drawW = $type['w'] * $brickScale;
        $drawH = $type['h'] * $brickScale;
        $drawX = $iconX + ($iconBoxW - $drawW) / 2;
        $drawY = $iconY + ($iconBoxH - $drawH) / 2;

        $pdf->SetFillColorHex($type['hex']);
        $pdf->SetDrawColor(0); // Black outline
        $pdf->Rect($drawX, $drawY, $drawW, $drawH, 'FD');

        // 3. Text Details
        $pdf->SetXY($currentX + 40, $currentY);
        $pdf->SetFont('Arial', '', 10);
        // MultiCell allows text to wrap if needed, but here we just center vertically manually
        $pdf->Cell(20, $rowHeight/2, "Size: {$type['w']}x{$type['h']}", 0, 2, 'L');
        $pdf->Cell(20, $rowHeight/2, "Qty: {$type['count']}", 0, 0, 'L');

        // Move cursor for next item
        $currentCol++;
        if ($currentCol >= $colsPerPage) {
            $currentCol = 0;
            $currentX = $startX;
            $currentY += $rowHeight;
        } else {
            $currentX += $colWidth;
        }
    }


    // --- PART 2: ASSEMBLY MAPS ---
    $minCellSize = 6; // Minimum mm per stud to be readable

    // Available drawing area
    $margin = 10;
    $availW = 297 - (2 * $margin);
    $availH = 210 - 45; // Height minus Header (approx 35mm) and Footer (10mm)

    $studsPerPageX = floor($availW / $minCellSize);
    $studsPerPageY = floor($availH / $minCellSize);

    for ($yStart = 0; $yStart < $maxY; $yStart += $studsPerPageY) {
        for ($xStart = 0; $xStart < $maxX; $xStart += $studsPerPageX) {

            // Calculate actual range for this page
            $yEnd = min($yStart + $studsPerPageY, $maxY);
            $xEnd = min($xStart + $studsPerPageX, $maxX);

            // Set Dynamic Header for this Page
            $pdf->subHeader = "Section: Cols $xStart-$xEnd | Rows $yStart-$yEnd";
            $pdf->AddPage();

            // Get Start Y (automatically below the header we just added)
            $drawStartY = $pdf->GetY() + 2;

            // Calculate Scale for this specific view
            $sectionW = $xEnd - $xStart;
            $sectionH = $yEnd - $yStart;

            $scaleX = $availW / $sectionW;
            $scaleY = ($availH - 5) / $sectionH; // -5 safety buffer
            $scale = min($scaleX, $scaleY);

            // Center the grid
            $gridDrawW = $sectionW * $scale;
            $gridDrawH = $sectionH * $scale;
            $offsetX = $margin + ($availW - $gridDrawW) / 2;
            $offsetY = $drawStartY + ($availH - $gridDrawH) / 2;

            // DRAW BRICKS
            foreach ($bricks as $b) {
                // View bounds intersection check
                $b_x1 = $b['x']; $b_x2 = $b['x'] + $b['w'];
                $b_y1 = $b['y']; $b_y2 = $b['y'] + $b['h'];

                // If brick touches the view window
                if ($b_x1 < $xEnd && $b_x2 > $xStart && $b_y1 < $yEnd && $b_y2 > $yStart) {

                    // Relative coordinates
                    $relX = ($b['x'] - $xStart) * $scale;
                    $relY = ($b['y'] - $yStart) * $scale;

                    $w = $b['w'] * $scale;
                    $h = $b['h'] * $scale;

                    // Style: White fill, Black text, Black border
                    $pdf->SetFillColor(255, 255, 255);
                    $pdf->SetDrawColor(0, 0, 0);
                    $pdf->SetLineWidth(0.2);

                    $pdf->Rect($offsetX + $relX, $offsetY + $relY, $w, $h, 'FD');

                    // Draw Number if it fits
                    if ($w > 4 && $h > 4) {
                        $pdf->SetTextColor(0);
                        // Dynamic Font Size
                        $fontSize = min($w, $h) * 0.5;
                        if ($fontSize > 10) $fontSize = 10;
                        if ($fontSize < 4) $fontSize = 4; // min readable

                        $pdf->SetFont('Arial', 'B', $fontSize);

                        // Center Text
                        $centerX = $offsetX + $relX + ($w / 2);
                        $centerY = $offsetY + $relY + ($h / 2);

                        // FPDF places text by bottom-left, need to adjust
                        $textW = $pdf->GetStringWidth($b['legend_id']);
                        $textH = $fontSize / 2.5;

                        $pdf->SetXY($centerX - ($textW/2), $centerY - ($textH));
                        $pdf->Cell($textW, $textH*2, $b['legend_id'], 0, 0, 'C');
                    }
                }
            }
        }
    }

    $pdf->Output('I', 'Mosaic_Manual.pdf');
}

// --- EXECUTION ---
if (isset($_GET['file'])) {
    $filename = basename($_GET['file']);
    // CHANGE THIS PATH TO YOUR FOLDER
    $folder = __DIR__ . '/users/tilings/';
    $filepath = $folder . $filename;
    generateMosaicManual($filepath);
} else {
    echo "No file specified.";
}
?>