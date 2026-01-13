<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!file_exists('fpdf.php')) {
    die("Error: fpdf.php not found.");
}
require('fpdf.php');

// --- HELPER CLASSES ---

class MosaicPDF extends FPDF {
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

    // Check if text color needs to be black or white based on background
    function SetTextColorForBackground($hex) {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        // Luminance formula
        $luma = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
        if ($luma < 128) $this->SetTextColor(255, 255, 255);
        else $this->SetTextColor(0, 0, 0);
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

    // To track unique types for the Legend (Shape + Color)
    // Key format: "W-H-HEX"
    $legend = [];

    foreach ($lines as $line) {
        if (strpos($line, ',') === false) continue;

        // Format: w-h/hexval,rotation,x,y
        $parts = explode(',', $line);
        if (count($parts) < 4) continue;

        $meta = explode('/', $parts[0]); // ["1-1", "354e5a"]
        if (count($meta) < 2) continue;

        $dims = explode('-', $meta[0]);
        $origW = (int)$dims[0];
        $origH = (int)$dims[1];
        $hex   = $meta[1];

        $rot = (int)$parts[1];
        $x   = (int)$parts[2];
        $y   = (int)$parts[3];

        // --- FIX ROTATION ---
        // If rotated 90 or 270 degrees, swap W and H
        // Assuming rotation is in degrees (0, 90, 180...) or steps.
        // If your file uses 0,1,2,3 for 0,90,180,270:
        $isVertical = ($rot == 90 || $rot == 270 || $rot == 1 || $rot == 3);

        $finalW = $isVertical ? $origH : $origW;
        $finalH = $isVertical ? $origW : $origH;

        // Store Brick
        $bricks[] = [
            'w' => $finalW,
            'h' => $finalH,
            'hex' => $hex,
            'x' => $x,
            'y' => $y,
            'type_key' => $origW . '-' . $origH . '-' . $hex // Use original shape for grouping logic
        ];

        // Update Bounds
        if (($x + $finalW) > $maxX) $maxX = $x + $finalW;
        if (($y + $finalH) > $maxY) $maxY = $y + $finalH;

        // Update Legend Counts
        $key = $origW . '-' . $origH . '-' . $hex;
        if (!isset($legend[$key])) {
            $legend[$key] = [
                'w' => $origW, 'h' => $origH, 'hex' => $hex,
                'count' => 0, 'id' => 0
            ];
        }
        $legend[$key]['count']++;
    }

    // Assign IDs to Legend Items (1, 2, 3...)
    $idCounter = 1;
    foreach ($legend as $k => $v) {
        $legend[$k]['id'] = $idCounter++;
    }

    // Map IDs back to individual bricks for easy lookup
    foreach ($bricks as &$b) {
        $b['legend_id'] = $legend[$b['type_key']]['id'];
    }
    unset($b); // Break reference

    // 2. SETUP PDF
    $pdf = new MosaicPDF('L', 'mm', 'A4'); // Landscape
    $pdf->SetAutoPageBreak(false);
    $pdf->SetTitle('Lego Mosaic Instructions');

    // --- PART 1: LEGEND / BOM ---
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 20);
    $pdf->Cell(0, 15, "Part List & Key", 0, 1, 'C');

    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, "Total Size: $maxX x $maxY studs | Total Parts: " . count($bricks), 0, 1, 'C');
    $pdf->Ln(5);

    // Draw Table of Parts
    $colWidth = 45;
    $rowHeight = 15;
    $startX = 10;
    $yPos = $pdf->GetY();

    foreach ($legend as $type) {
        // Check page break
        if ($yPos > 180) {
            $pdf->AddPage();
            $yPos = 20;
            $pdf->SetFont('Arial', 'B', 20);
            $pdf->Cell(0, 15, "Part List (Cont.)", 0, 1, 'C');
            $pdf->Ln(5);
        }

        // Draw ID Number
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->SetXY($startX, $yPos);
        $pdf->Cell(15, $rowHeight, "#" . $type['id'], 1, 0, 'C');

        // Draw Visual Brick Representation
        $pdf->SetXY($startX + 15, $yPos);
        // We draw a small rectangle representing the color
        $pdf->SetFillColorHex($type['hex']);
        $pdf->Rect($startX + 17, $yPos + 3, 15, $rowHeight - 6, 'FD');
        $pdf->Cell(20, $rowHeight, "", 1, 0); // Placeholder box

        // Text Description
        $pdf->SetXY($startX + 35, $yPos);
        $pdf->SetFont('Arial', '', 10);
        $text = "{$type['w']}x{$type['h']} - Qty: {$type['count']}";
        $pdf->Cell(40, $rowHeight, $text, 1, 0, 'L');

        // Move to next column or row
        $startX += 80; // Column shift
        if ($startX > 200) { // If too far right, new row
            $startX = 10;
            $yPos += $rowHeight;
        }
    }

    // --- PART 2: THE ASSEMBLY MAP ---
    // We need to ensure the cells are big enough to read the numbers.
    // Minimum cell size in mm to be readable:
    $minCellSize = 7;

    // Page dimensions available
    $pageW = 297;
    $pageH = 210;
    $margin = 10;
    $drawAreaW = $pageW - (2 * $margin);
    $drawAreaH = $pageH - (2 * $margin);

    // Calculate how many studs fit on one page
    $studsPerPageX = floor($drawAreaW / $minCellSize);
    $studsPerPageY = floor($drawAreaH / $minCellSize);

    // Loop through "Chunks" (Quadrants/Sections)
    for ($yStart = 0; $yStart < $maxY; $yStart += $studsPerPageY) {
        for ($xStart = 0; $xStart < $maxX; $xStart += $studsPerPageX) {

            $pdf->AddPage();

            // Calculate end points for this page
            $yEnd = min($yStart + $studsPerPageY, $maxY);
            $xEnd = min($xStart + $studsPerPageX, $maxX);

            // Header
            $pdf->SetFont('Arial', '', 10);
            $pdf->SetXY(5, 5);
            $pdf->Cell(0, 10, "Section: X ($xStart - $xEnd) | Y ($yStart - $yEnd)", 0, 0, 'L');

            // Calculate exact Scale to maximize this specific page usage
            $sectionW = $xEnd - $xStart;
            $sectionH = $yEnd - $yStart;

            $scaleX = $drawAreaW / $sectionW;
            $scaleY = $drawAreaH / $sectionH;
            $scale = min($scaleX, $scaleY); // Fit to page

            // Center content
            $drawX = $margin + ($drawAreaW - ($sectionW * $scale)) / 2;
            $drawY = $margin + ($drawAreaH - ($sectionH * $scale)) / 2;

            // Draw Bricks
            foreach ($bricks as $b) {
                // Logic: Does this brick overlap with the current view window?
                // Simple check: is the top-left corner inside?
                // Better check: intersection.

                // Brick bounds
                $b_x1 = $b['x'];
                $b_x2 = $b['x'] + $b['w'];
                $b_y1 = $b['y'];
                $b_y2 = $b['y'] + $b['h'];

                // View bounds
                $v_x1 = $xStart; $v_x2 = $xEnd;
                $v_y1 = $yStart; $v_y2 = $yEnd;

                // Check intersection
                if ($b_x1 < $v_x2 && $b_x2 > $v_x1 && $b_y1 < $v_y2 && $b_y2 > $v_y1) {

                    // It is visible (at least partially)
                    // Calculate relative coords
                    $relX = ($b['x'] - $xStart) * $scale;
                    $relY = ($b['y'] - $yStart) * $scale;

                    $w = $b['w'] * $scale;
                    $h = $b['h'] * $scale;

                    // Draw White Box with Outline
                    $pdf->SetFillColor(255, 255, 255);
                    $pdf->SetDrawColor(0, 0, 0);
                    $pdf->SetLineWidth(0.2);

                    // We use a clipping trick or simple math to ensure we don't draw outside bounds?
                    // FPDF doesn't support clipping natively easily.
                    // For this manual, usually bricks are perfectly aligned to the grid split,
                    // or we accept partial bricks on edges.

                    $pdf->Rect($drawX + $relX, $drawY + $relY, $w, $h, 'FD');

                    // Draw ID Number
                    // Only draw text if the box is big enough
                    if ($w > 4 && $h > 4) {
                        $pdf->SetTextColor(0, 0, 0);
                        // Font size depends on box size
                        $fontSize = min($w, $h) * 0.6;
                        if ($fontSize > 12) $fontSize = 12;
                        $pdf->SetFont('Arial', 'B', $fontSize);

                        // Center text
                        $pdf->SetXY($drawX + $relX, $drawY + $relY + ($h/2) - ($fontSize/2.5)); // Approx v-center
                        $pdf->Cell($w, $fontSize/2, $b['legend_id'], 0, 0, 'C');
                    }
                }
            }
        }
    }

    $pdf->Output('I', 'Assembly_Manual.pdf');
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