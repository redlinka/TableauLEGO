<?php
// This step has been merged into crop_selection.php
// Redirect for backward compatibility
require_once __DIR__ . '/config/session.php';

if (isset($_SESSION['step1_image_id']) && isset($_SESSION['target_width'])) {
    // If dimensions already set, go to next step
    header("Location: downscale_selection.php");
} else {
    // Otherwise go back to crop (which now includes dimension selection)
    header("Location: crop_selection.php");
}
exit;
