<?php

namespace App;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

class Mailer
{
    public function send(string $to, string $subject, string $html, array $cc = []): array
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = Env::get('SMTP_HOST', '');
            $mail->Port = (int) Env::get('SMTP_PORT', '587');
            $mail->SMTPAuth = true;
            $mail->Username = Env::get('SMTP_USER', '');
            $mail->Password = Env::get('SMTP_PASS', '');
            $mail->SMTPSecure = filter_var(Env::get('SMTP_SECURE', 'false'), FILTER_VALIDATE_BOOLEAN)
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;

            $from = Env::get('EMAIL_FROM', 'Diarias Village <nao-responder@village.einsteinhub.co>');
            if (preg_match('/^(.+)\s<(.+)>$/', $from, $matches)) {
                $mail->setFrom($matches[2], $matches[1]);
            } else {
                $mail->setFrom($from);
            }

            $mail->addAddress($to);
            foreach ($cc as $email) {
                $mail->addCC($email);
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;

            $mail->send();
            return ['ok' => true];
        } catch (MailException $e) {
            error_log('Mailer error: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
