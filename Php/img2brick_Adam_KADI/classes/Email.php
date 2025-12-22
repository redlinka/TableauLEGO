<?php
// classes/Email.php
// PHPMailer integration requires Composer installation

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


require_once __DIR__ . '/../includes/config.php';

class EmailService
{
    private ?PHPMailer $mailer = null;
    private bool $available = false;

    public function __construct()
    {
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;

            $this->mailer = new PHPMailer(true);
            $this->configureMailer();
            $this->available = true;
        } else {
            // if composer not installed we ignore it
            $this->available = false;
        }
    }

    private function configureMailer(): void
{
    $this->mailer->isSMTP();
    $this->mailer->Host       = SMTP_HOST;
    $this->mailer->SMTPAuth   = true;
    $this->mailer->Username   = SMTP_USER;
    $this->mailer->Password   = SMTP_PASS;
    $this->mailer->Port       = SMTP_PORT;

    // Security can be edited
    $secure = defined('SMTP_SECURE') ? SMTP_SECURE : 'tls';
    if ($secure === 'ssl') {
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;   // port 465
    } elseif ($secure === 'tls') {
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // port 587/2525
    } else {
        $this->mailer->SMTPSecure = false; // possible
        $this->mailer->SMTPAutoTLS = false;
    }

    // Disable verbose SMTP debug in production to avoid leaking credentials
    $this->mailer->SMTPDebug  = 0;
    $this->mailer->Debugoutput = 'error_log';

    $this->mailer->setFrom(SMTP_FROM, SMTP_FROM_NAME);
}


    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function send(string $to, string $subject, string $htmlBody, string $textBody = ''): bool
{
    if (!$this->available) {
        error_log("EmailService: not available (vendor/autoload missing?)");
        return false;
    }

    try {
        $this->mailer->clearAllRecipients();
        $this->mailer->addAddress($to);
        $this->mailer->Subject = $subject;
        $this->mailer->isHTML(true);
        $this->mailer->Body = $htmlBody;
        $this->mailer->AltBody = $textBody !== '' ? $textBody : strip_tags($htmlBody);

        return $this->mailer->send();
    } catch (Exception $e) {
        error_log("EmailService send error: " . $e->getMessage());
        return false;
    }
}

}
