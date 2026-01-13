<?php
require('fpdf.php');

class MosaicPDF extends FPDF {
    // Helper to convert Hex to RGB
    function SetFillColorHex($hex) {
        $hex = lstrip($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $this->SetFillColor($r, $g, $b);
    }
}

function lstrip($str, $char) {
    return ltrim($str, $char);
}

function generateMosaicManual($filepath) {
    // 1. Parse the File
    if (!file_exists($filepath)) {
        die("Error: File not found at $filepath");
    }

    $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $bricks = [];
    $max_x = 0;
    $max_y = 0;

    foreach ($lines as $line) {
        // Skip headers or malformed lines
        if (strpos($line, ',') === false) continue;

        // Format: w-h/hexval,rotation,x,y
        // Example: 1-1/354e5a,0,0,0
        $parts = explode(',', $line);

        if (count($parts) < 4) continue;

        // Parse part 0: "1-1/354e5a"
        $meta = explode('/', $parts[0]);
        if (count($meta) < 2) continue;

        $dims = explode('-', $meta[0]); // 1-1
        $w = (int)$dims[0];
        $h = (int)$dims[1];
        $hex = $meta[1];

        $x = (int)$parts[2];
        $y = (int)$parts[3];

        $bricks[] = [
            'w' => $w, 'h' => $h,
            'hex' => $hex,
            'x' => $x, 'y' => $y
        ];

        // Determine canvas size
        if (($x + $w) > $max_x) $max_x = $x + $w;
        if (($y + $h) > $max_y) $max_y = $y + $h;
    }

    // 2. Initialize PDF
    $pdf = new MosaicPDF('L', 'mm', 'A4'); // Landscape A4
    $pdf->SetAutoPageBreak(false);

    // --- PAGE 1: COVER ---
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 24);
    $pdf->Cell(0, 20, 'Mosaic Assembly Guide', 0, 1, 'C');

    $pdf->SetFont('Arial', '', 14);
    $pdf->Cell(0, 10, "Dimensions: $max_x x $max_y studs", 0, 1, 'C');
    $pdf->Cell(0, 10, "Total Parts: " . count($bricks), 0, 1, 'C');

    // Draw Full Preview (Scaled to fit page)
    $pageW = 297;
    $pageH = 210;
    $margin = 20;

    // Calculate scaling
    $availW = $pageW - ($margin * 2);
    $availH = $pageH - 60; // minus header space

    $scaleW = $availW / $max_x;
    $scaleH = $availH / $max_y;
    $scale = min($scaleW, $scaleH); // Keep aspect ratio

    // Center the preview
    $previewX = ($pageW - ($max_x * $scale)) / 2;
    $previewY = 60;

    // Draw Preview Bricks
    foreach ($bricks as $b) {
        $pdf->SetFillColorHex($b['hex']);
        // Draw rect (x, y, w, h, style) 'F' = filled
        $pdf->Rect(
            $previewX + ($b['x'] * $scale),
            $previewY + ($b['y'] * $scale),
            $b['w'] * $scale,
            $b['h'] * $scale,
            'F'
        );
    }

    // --- PAGE 2+: INSTRUCTIONS (16x16 CHUNKS) ---
    $chunkSize = 16;
    $drawSize = 10; // Size of each square in mm on the instruction page

    for ($y_start = 0; $y_start < $max_y; $y_start += $chunkSize) {
        for ($x_start = 0; $x_start < $max_x; $x_start += $chunkSize) {

            $pdf->AddPage();

            // Title
            $pdf->SetFont('Arial', 'B', 16);
            $y_end = min($y_start + $chunkSize, $max_y);
            $x_end = min($x_start + $chunkSize, $max_x);
            $pdf->Cell(0, 15, "Section: Rows $y_start-$y_end, Cols $x_start-$x_end", 0, 1, 'L');

            // Draw Grid
            // Center the 16x16 grid on the page
            $gridWidth = $chunkSize * $drawSize;
            $gridHeight = $chunkSize * $drawSize;

            $startX = ($pageW - $gridWidth) / 2;
            $startY = ($pageH - $gridHeight) / 2;

            // 1. Draw Background Grid Lines (Empty)
            $pdf->SetDrawColor(200, 200, 200); // Light gray grid
            for ($i = 0; $i <= $chunkSize; $i++) {
                // Vertical
                $pdf->Line($startX + ($i*$drawSize), $startY, $startX + ($i*$drawSize), $startY + $gridHeight);
                // Horizontal
                $pdf->Line($startX, $startY + ($i*$drawSize), $startX + $gridWidth, $startY + ($i*$drawSize));
            }

            // 2. Draw Bricks in this section
            $pdf->SetDrawColor(0, 0, 0); // Black borders for bricks

            foreach ($bricks as $b) {
                // Check if brick is inside current view
                if ($b['x'] >= $x_start && $b['x'] < $x_start + $chunkSize &&
                    $b['y'] >= $y_start && $b['y'] < $y_start + $chunkSize) {

                    // Calculate relative position
                    $relX = $b['x'] - $x_start;
                    $relY = $b['y'] - $y_start;

                    $pdf->SetFillColorHex($b['hex']);
                    $pdf->Rect(
                        $startX + ($relX * $drawSize),
                        $startY + ($relY * $drawSize),
                        $b['w'] * $drawSize,
                        $b['h'] * $drawSize,
                        'FD' // Fill and Draw border
                    );
                }
            }
        }
    }

    // Output PDF (D = Download, F = Save to file, I = Inline view)
    $pdf->Output('I', 'Lego_Instructions.pdf');
}