<?php
session_start();
global $cnx;
include("./config/cnx.php");
require_once __DIR__ . '/includes/i18n.php';

$status = 'processing';
$message = '';

// Validate input Ensure token presence
if (!isset($_GET['token'])) {
    $status = 'error';
    $message = tr('verify_account.no_token', 'No token provided.');
} else {
    try {
        // Query token Verify validity and expiration
        $stmt = $cnx->prepare("SELECT *, TIMESTAMPDIFF(MINUTE, created_at, NOW()) as age_minutes 
                                   FROM Tokens2FA 
                                   WHERE token = ? AND is_used = 0 
                                   LIMIT 1");
        $stmt->execute([$_GET['token']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            $status = 'error';
            $message = tr('verify_account.invalid_link', 'Invalid or expired verification link.');
        } elseif ((int)$result['age_minutes'] > 10) {
            $status = 'error';
            $message = tr('verify_account.expired', 'This link has expired. Please request a new one.');
        } else {
            // Invalidate token Prevent reuse
            $updateStmt = $cnx->prepare("UPDATE Tokens2FA SET is_used = 1 WHERE token = ?");
            $updateStmt->execute([$_GET['token']]);

            // Activate user Enable account access
            $verifyUserStmt = $cnx->prepare("UPDATE Users SET is_active = 1 WHERE id_user = ?");
            $verifyUserStmt->execute([$result['user_id']]);

            // Clear session Clean up temporary data
            unset($_SESSION['emailToken']);
            unset($_SESSION['email_sent']);
            unset($_SESSION['last_email_sent']);

            $status = 'success';
            $message = tr('verify_account.success_message', 'Your account has been successfully verified!');
        }
    } catch (Exception $e) {
        $status = 'error';
        $message = tr('verify_account.system_error', 'System error. Please try again later.');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(tr('verify_account.page_title', 'Account Verification')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .icon-box { font-size: 3rem; margin-bottom: 1rem; }
        .text-success-custom { color: #198754; }
        .text-danger-custom { color: #dc3545; }
    </style>
</head>
<body class="bg-light d-flex flex-column min-vh-100">

<?php include("./includes/navbar.php"); ?>

<div class="container flex-grow-1 d-flex align-items-center justify-content-center py-5">
    <div class="card shadow-sm border-0 text-center" style="max-width: 500px; width: 100%;">
        <div class="card-body p-5">

            <?php if ($status === 'success'): ?>
                <div class="icon-box text-success-custom">OK</div>
                <h2 class="fw-bold mb-3" data-i18n="verify_account.success_title">Verified!</h2>
                <p class="text-muted mb-4"><?= htmlspecialchars($message) ?></p>
                <a href="connexion.php" class="btn btn-primary btn-lg w-100" data-i18n="verify_account.login_now">Log In Now</a>

            <?php elseif ($status === 'error'): ?>
                <div class="icon-box text-danger-custom">X</div>
                <h2 class="fw-bold mb-3" data-i18n="verify_account.error_title">Verification Failed</h2>
                <p class="text-muted mb-4"><?= htmlspecialchars($message) ?></p>
                <div class="d-grid gap-2">
                    <a href="creation.php" class="btn btn-outline-secondary" data-i18n="verify_account.back_signup">Back to Sign Up</a>
                    <a href="index.php" class="btn btn-link text-decoration-none" data-i18n="verify_account.go_home">Go Home</a>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<?php include("./includes/footer.php"); ?>
</body>
</html>

