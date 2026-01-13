<?php
// 1. ENABLE ERROR REPORTING (Helps debug the 500 error)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. CHECK LIBRARY
if (!file_exists('fpdf.php')) {
    die("Error: 'fpdf.php' is missing. Please download it from fpdf.org and put it in this folder.");
}
require('fpdf.php');

class MosaicPDF extends FPDF {
    function SetFillColorHex($hex) {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $this->SetFillColor($r, $g, $b);
    }
}

function generateMosaicManual($filepath) {
    if (!file_exists($filepath)) {
        die("Error: File not found. Looking for: " . htmlspecialchars($filepath));
    }

    $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $bricks = [];
    $max_x = 0;
    $max_y = 0;

    foreach ($lines as $line) {
        if (strpos($line, ',') === false) continue;
        $parts = explode(',', $line);
        if (count($parts) < 4) continue;

        $meta = explode('/', $parts[0]);
        if (count($meta) < 2) continue;

        $dims = explode('-', $meta[0]);
        $bricks[] = [
            'w' => (int)$dims[0],
            'h' => (int)$dims[1],
            'hex' => $meta[1],
            'x' => (int)$parts[2],
            'y' => (int)$parts[3]
        ];

        if (((int)$parts[2] + (int)$dims[0]) > $max_x) $max_x = (int)$parts[2] + (int)$dims[0];
        if (((int)$parts[3] + (int)$dims[1]) > $max_y) $max_y = (int)$parts[3] + (int)$dims[1];
    }

    // Initialize PDF
    $pdf = new MosaicPDF('L', 'mm', 'A4');
    $pdf->SetAutoPageBreak(false);

    // COVER PAGE
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 24);
    $pdf->Cell(0, 20, 'Mosaic Assembly Guide', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 14);
    $pdf->Cell(0, 10, "Dimensions: $max_x x $max_y studs", 0, 1, 'C');
    $pdf->Cell(0, 10, "Parts: " . count($bricks), 0, 1, 'C');

    // PREVIEW
    $pageW = 297; $pageH = 210; $margin = 20;
    $availW = $pageW - ($margin * 2);
    $availH = $pageH - 60;
    $scale = min($availW / $max_x, $availH / $max_y);
    $previewX = ($pageW - ($max_x * $scale)) / 2;
    $previewY = 60;

    foreach ($bricks as $b) {
        $pdf->SetFillColorHex($b['hex']);
        $pdf->Rect($previewX + ($b['x'] * $scale), $previewY + ($b['y'] * $scale), $b['w'] * $scale, $b['h'] * $scale, 'F');
    }

    // INSTRUCTION PAGES
    $chunkSize = 16;
    $drawSize = 10;

    for ($y_start = 0; $y_start < $max_y; $y_start += $chunkSize) {
        for ($x_start = 0; $x_start < $max_x; $x_start += $chunkSize) {
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->Cell(0, 15, "Rows $y_start-" . min($y_start+$chunkSize, $max_y) . ", Cols $x_start-" . min($x_start+$chunkSize, $max_x), 0, 1, 'L');

            // Draw Grid Lines
            $gridW = $chunkSize * $drawSize;
            $gridH = $chunkSize * $drawSize;
            $startX = ($pageW - $gridW) / 2;
            $startY = ($pageH - $gridH) / 2;

            $pdf->SetDrawColor(200);
            for ($i = 0; $i <= $chunkSize; $i++) {
                $pdf->Line($startX + ($i*$drawSize), $startY, $startX + ($i*$drawSize), $startY + $gridH);
                $pdf->Line($startX, $startY + ($i*$drawSize), $startX + $gridW, $startY + ($i*$drawSize));
            }

            // Draw Bricks
            $pdf->SetDrawColor(0);
            foreach ($bricks as $b) {
                if ($b['x'] >= $x_start && $b['x'] < $x_start + $chunkSize &&
                    $b['y'] >= $y_start && $b['y'] < $y_start + $chunkSize) {
                    $pdf->SetFillColorHex($b['hex']);
                    $pdf->Rect($startX + ($b['x']-$x_start)*$drawSize, $startY + ($b['y']-$y_start)*$drawSize, $b['w']*$drawSize, $b['h']*$drawSize, 'FD');
                }
            }
        }
    }
    $pdf->Output('I', 'Instructions.pdf');
}

// --- LOGIC TO HANDLE THE FILE ---
if (isset($_GET['file'])) {
    $filename = basename($_GET['file']);

    // IMPORTANT: Check where your .txt files are stored!
    // Since your images are in 'users/imgs/', I assume text files might be in 'users/txts/'
    // If they are in the root, remove the 'users/txts/' part.

    $folder = __DIR__ . '/users/tilings/';
    
    $filepath = $folder . $filename;

    generateMosaicManual($filepath);
} else {
    echo "No file specified.";
}
?>