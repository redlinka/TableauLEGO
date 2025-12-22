<?php
require dirname(__DIR__) . '/lib/PHPMailer/src/PHPMailer.php';
require dirname(__DIR__) . '/lib/PHPMailer/src/SMTP.php';
require dirname(__DIR__) . '/lib/PHPMailer/src/Exception.php';

class EmailSender {
    /** * @var array
     */
    private $config;

    /**
     * The constructor loads the config once when the object is created
     */
    public function __construct() {
        $this->config = require dirname(__DIR__) . "/config.php";
    }

    /**
     * Sends an email using PHPMailer
     */
    public function send(string $to, string $subject, string $body): bool {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->isSMTP();
            $mail->Host       = $this->config["SMTP_HOST"];
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->config["SMTP_USERNAME"];
            $mail->Password   = $this->config["SMTP_PASSWORD"];
            $mail->SMTPSecure = "tls";
            $mail->Port       = 587;

            $mail->setFrom($this->config["SMTP_FROM"], $this->config["SMTP_FROM_NAME"]);
            $mail->addAddress($to);

            $mail->isHTML();
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();
            return true;
        } catch (PHPMailer\PHPMailer\Exception $e) {
            error_log("PHPMailer Error: " . $e->getMessage());
            return false;
        }
    }
}