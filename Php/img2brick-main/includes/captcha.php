<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
function captcha_generate(): array {
    $a = random_int(1, 9);
    $b = random_int(1, 9);
    $_SESSION['captcha_answer'] = (string)($a + $b);
    return [$a, $b];
}
function captcha_verify(string $answer, string $honeypot): bool {
    if (!isset($_SESSION['captcha_answer'])) { return false; }
    $ok = hash_equals($_SESSION['captcha_answer'], trim($answer));
    $hp = trim($honeypot ?? '');
    unset($_SESSION['captcha_answer']);
    return $ok && $hp === '';
}
