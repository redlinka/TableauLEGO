<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/classes/Email.php';

require_csrf_token();

if (
    !isset($_SESSION['2fa_pending_user_id'], $_SESSION['2fa_pending_email'])
) {
    header('Location: login.php');
    exit;
}

// Security : no resend before expiration
if (isset($_SESSION['2fa_expires_at']) && time() < $_SESSION['2fa_expires_at']) {
    header('Location: verify_2fa.php');
    exit;
}

// New code
$code = (string)random_int(100000, 999999);

$_SESSION['2fa_code']       = $code;
$_SESSION['2fa_expires_at'] = time() + 60;
$_SESSION['2fa_attempts']   = 0;

// Mail sender
$emailService = new EmailService();
if ($emailService->isAvailable()) {
    $to = $_SESSION['2fa_pending_email'];
    $subject = t('2fa_subject');

    $body = t('2fa_body');
    $body = str_replace(['{{code}}', '{{minutes}}'], [$code, '1'], $body);

    if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $emailService->send($to, $subject, $body);
    }
}
log_event((int)$_SESSION['2fa_pending_user_id'], '2fa', 'resend', '2FA code resent');

header('Location: verify_2fa.php');
exit;
