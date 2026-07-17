<?php

namespace App\Services;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Centralna wysyłka i szablonowanie wiadomości e-mail aplikacji.
 */
class Mailer
{
  private PHPMailer $mailer;

  public function __construct()
  {
    $this->mailer = new PHPMailer(true);

    $this->mailer->isSMTP();
    $this->mailer->Host = $_ENV['SMTP_HOST'];
    $this->mailer->SMTPAuth = true;
    $this->mailer->Username = $_ENV['SMTP_USER'];
    $this->mailer->Password = str_replace(' ', '', $_ENV['SMTP_PASS']);

    $encryption = $_ENV['SMTP_ENCRYPTION'] ?? 'tls';
    $this->mailer->SMTPSecure = $encryption === 'ssl'
      ? PHPMailer::ENCRYPTION_SMTPS
      : PHPMailer::ENCRYPTION_STARTTLS;
    $this->mailer->Port = (int) ($_ENV['SMTP_PORT'] ?? ($encryption === 'ssl' ? 465 : 587));

    $this->mailer->setFrom(SYSTEM_MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
    $this->mailer->isHTML(true);
    $this->mailer->CharSet = 'UTF-8';
  }

  public function send(string $to, string $subject, string $body): bool
  {
    $html = $this->renderEmail([
      'eyebrow' => 'Wiadomość systemowa',
      'title' => $subject,
      'body' => $body,
    ]);

    return $this->deliver($to, $subject, $html, $this->toPlainText($body));
  }

  public function sendWithReplyTo(
    string $to,
    string $subject,
    string $body,
    ?string $replyTo = null,
    ?string $replyName = null
  ): bool {
    $html = $this->renderEmail([
      'eyebrow' => 'Wiadomość',
      'title' => $subject,
      'body' => $body,
      'variant' => 'admin',
    ]);

    return $this->deliver($to, $subject, $html, $this->toPlainText($body), $replyTo, $replyName);
  }

  public function sendAccountVerificationEmail(string $email, string $name, string $link): bool
  {
    $body = $this->paragraph('Cześć ' . $this->e($name) . ',')
      . $this->paragraph('Rejestracja w Zdajnik przebiegła pomyślnie. Potwierdź adres e-mail, żeby aktywować konto i zacząć korzystać z platformy.')
      . $this->actionButton('Aktywuj konto', $link)
      . $this->notice('Link aktywacyjny jest ważny przez 24 godziny. Jeśli to nie Ty zakładałeś konto, możesz zignorować tę wiadomość.');

    $html = $this->renderEmail([
      'eyebrow' => 'Aktywacja konta',
      'title' => 'Potwierdź adres e-mail',
      'body' => $body,
      'summary' => 'Aktywuj konto w Zdajnik i zabezpiecz dostęp do swoich postępów.',
      'primaryLink' => $link,
    ]);

    return $this->deliver(
      $email,
      'Aktywuj swoje konto w Zdajnik',
      $html,
      "Cześć $name,\n\nRejestracja w Zdajnik przebiegła pomyślnie. Aktywuj konto pod adresem:\n$link\n\nLink jest ważny przez 24 godziny."
    );
  }

  public function sendPasswordResetEmail(string $email, string $link): bool
  {
    $body = $this->paragraph('Otrzymano prośbę o zresetowanie hasła do Twojego konta.')
      . $this->actionButton('Ustaw nowe hasło', $link)
      . $this->notice('Na stronie resetowania zostaniesz poproszony o kod bezpieczeństwa widoczny w aplikacji. Jeśli to nie Ty zleciłeś reset, zignoruj tę wiadomość.');

    $html = $this->renderEmail([
      'eyebrow' => 'Bezpieczeństwo konta',
      'title' => 'Reset hasła',
      'body' => $body,
      'summary' => 'Użyj linku, aby ustawić nowe hasło do konta Zdajnik.',
      'primaryLink' => $link,
    ]);

    return $this->deliver(
      $email,
      'Reset hasła w serwisie Zdajnik',
      $html,
      "Otrzymano prośbę o zresetowanie hasła.\n\nUstaw nowe hasło pod adresem:\n$link\n\nJeśli to nie Ty zleciłeś reset, zignoruj tę wiadomość."
    );
  }

  public function sendEmailChangeVerificationEmail(string $email, string $code): bool
  {
    $body = $this->paragraph('Użyj poniższego kodu, aby potwierdzić zmianę adresu e-mail w ustawieniach konta.')
      . $this->verificationCode($code)
      . $this->notice('Kod przepisz w formularzu, który otworzył się po zgłoszeniu zmiany e-maila. Jeśli to nie Ty zleciłeś zmianę, nie przekazuj nikomu tego kodu.');

    $html = $this->renderEmail([
      'eyebrow' => 'Zmiana e-maila',
      'title' => 'Kod weryfikacyjny',
      'body' => $body,
      'summary' => 'Potwierdź nowy adres e-mail dla konta Zdajnik.',
    ]);

    return $this->deliver(
      $email,
      'Weryfikacja zmiany e-mail - Zdajnik',
      $html,
      "Twój kod weryfikacyjny zmiany e-maila: $code\n\nJeśli to nie Ty zleciłeś zmianę, zignoruj tę wiadomość."
    );
  }

  public function sendAccountDeletionConfirmationEmail(string $email, string $link): bool
  {
    $body = $this->paragraph('Otrzymano prośbę o usunięcie Twojego konta w Zdajnik.')
      . $this->dangerNotice('Po potwierdzeniu konto zostanie dezaktywowane. Masz 30 dni na anulowanie operacji przez ponowne zalogowanie.')
      . $this->actionButton('Potwierdzam usunięcie konta', $link, 'danger')
      . $this->notice('Jeśli ta prośba nie została wysłana przez Ciebie, zignoruj tę wiadomość. Twoje konto pozostanie aktywne.');

    $html = $this->renderEmail([
      'eyebrow' => 'Operacja na koncie',
      'title' => 'Potwierdź usunięcie konta',
      'body' => $body,
      'summary' => 'Wymagane jest potwierdzenie e-mail, zanim rozpocznie się usuwanie konta.',
      'primaryLink' => $link,
      'variant' => 'danger',
    ]);

    return $this->deliver(
      $email,
      'Potwierdzenie usunięcia Twojego konta - Zdajnik',
      $html,
      "Otrzymano prośbę o usunięcie konta.\n\nPotwierdź operację pod adresem:\n$link\n\nJeśli to nie Ty, zignoruj tę wiadomość."
    );
  }

  public function sendProgressAnonymizationEmail(string $email, string $link, string $examCode): bool
  {
    $body = $this->paragraph('Otrzymano prośbę o wyzerowanie postępów i statystyk dla egzaminu:')
      . $this->dataRows(['Egzamin' => $examCode])
      . $this->dangerNotice('Potwierdzenie operacji zanonimizuje historię prób i przywróci stan „0 odkrytych pytań” dla wybranego egzaminu.')
      . $this->actionButton('Potwierdzam wyzerowanie postępu', $link, 'danger')
      . $this->notice('Jeśli to nie Ty zleciłeś tę operację, nie klikaj linku i skontaktuj się z administracją.');

    $html = $this->renderEmail([
      'eyebrow' => 'Postępy nauki',
      'title' => 'Potwierdź wyzerowanie postępu',
      'body' => $body,
      'summary' => 'Ta operacja dotyczy wyłącznie wybranego egzaminu.',
      'primaryLink' => $link,
      'variant' => 'danger',
    ]);

    return $this->deliver(
      $email,
      'Potwierdzenie wyzerowania postępu - Zdajnik',
      $html,
      "Otrzymano prośbę o wyzerowanie postępu dla egzaminu: $examCode.\n\nPotwierdź operację pod adresem:\n$link"
    );
  }

  /**
   * @param array{name: string, email: string, subject: string, message: string} $formData
   */
  public function sendContactNotificationEmail(array $formData): bool
  {
    $subject = 'Kontakt Zdajnik: ' . str_replace(["\r", "\n"], ' ', $formData['subject']);
    $safeMessage = nl2br($this->e($formData['message']));

    $body = $this->dataRows([
      'Imię / nazwa' => $formData['name'],
      'E-mail' => $formData['email'],
      'Temat' => $formData['subject'],
    ])
      . '<div style="height: 18px; line-height: 18px;">&nbsp;</div>'
      . '<div style="font-size: 12px; font-weight: 800; letter-spacing: .08em; color: #52645f; text-transform: uppercase; margin-bottom: 8px;">Wiadomość</div>'
      . '<div style="font-size: 15px; line-height: 1.7; color: #0d1b3d; background: #f7faf9; border: 1px solid #d8e3dd; border-radius: 14px; padding: 18px;">' . $safeMessage . '</div>';

    $html = $this->renderEmail([
      'eyebrow' => 'Formularz kontaktowy',
      'title' => 'Nowa wiadomość z formularza',
      'body' => $body,
      'summary' => 'Wiadomość przesłana przez publiczny formularz kontaktowy Zdajnik.',
      'variant' => 'admin',
    ]);

    return $this->deliver(
      CONTACT_EMAIL,
      $subject,
      $html,
      "Nowa wiadomość z formularza kontaktowego Zdajnik\n\n"
        . "Imię / nazwa: {$formData['name']}\n"
        . "E-mail: {$formData['email']}\n"
        . "Temat: {$formData['subject']}\n\n"
        . $formData['message'],
      $formData['email'],
      $formData['name']
    );
  }

  private function deliver(
    string $to,
    string $subject,
    string $html,
    string $plainText,
    ?string $replyTo = null,
    ?string $replyName = null
  ): bool {
    try {
      $this->mailer->clearAllRecipients();
      $this->mailer->clearReplyTos();

      $this->mailer->addAddress($to);

      if (!empty($replyTo) && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $this->mailer->addReplyTo($replyTo, $replyName ?: $replyTo);
      }

      $this->mailer->Subject = $subject;
      $this->mailer->Body = $html;
      $this->mailer->AltBody = $plainText;

      return $this->mailer->send();
    } catch (Exception $e) {
      error_log('[Mailer Error] ' . $this->mailer->ErrorInfo . ' | Msg: ' . $e->getMessage());
      return false;
    }
  }

  /**
   * @param array{eyebrow: string, title: string, body: string, summary?: string, primaryLink?: string, variant?: string} $data
   */
  private function renderEmail(array $data): string
  {
    $variant = $data['variant'] ?? 'default';
    $accent = $variant === 'danger' ? '#dc2626' : '#10b981';
    $accentDark = $variant === 'danger' ? '#b91c1c' : '#059669';
    $surfaceTint = $variant === 'danger' ? '#fef2f2' : '#eefbf5';
    $safeTitle = $this->e($data['title']);
    $safeEyebrow = $this->e($data['eyebrow']);
    $summary = $this->e($data['summary'] ?? 'Wiadomość z aplikacji Zdajnik.');
    $preheader = '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . $summary . '</div>';
    $body = $data['body'];
    $fallbackBlock = '';

    if (!empty($data['primaryLink'])) {
      $safePrimaryLink = $this->e($data['primaryLink']);
      $fallbackBlock = '<div style="padding:18px 28px 24px;border-top:1px solid #d8e3dd;background:#fbfdfc;">
                <p style="margin:0 0 10px;color:#52645f;font-size:12px;line-height:1.6;">Jeśli przycisk nie działa, skopiuj ten adres do przeglądarki:</p>
                <p style="margin:0;color:' . $accentDark . ';font-size:12px;line-height:1.5;word-break:break-all;"><a href="' . $safePrimaryLink . '" style="color:' . $accentDark . ';text-decoration:none;">' . $safePrimaryLink . '</a></p>
              </div>';
    }

    $year = date('Y');

    return '<!doctype html>
<html lang="pl">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>' . $safeTitle . '</title>
</head>
<body style="margin:0;padding:0;background:#f7faf9;color:#0d1b3d;font-family:Arial,Helvetica,sans-serif;-webkit-font-smoothing:antialiased;">
  ' . $preheader . '
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;background:#f7faf9;">
    <tr>
      <td align="center" style="padding:32px 16px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%;max-width:640px;">
          <tr>
            <td style="padding:0 0 16px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                <tr>
                  <td style="font-size:24px;font-weight:900;letter-spacing:-0.02em;color:#0d1b3d;">Zdajnik</td>
                  <td align="right" style="font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:' . $accent . ';">' . $safeEyebrow . '</td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="background:#ffffff;border:1px solid #d8e3dd;border-radius:22px;box-shadow:0 18px 42px rgba(13,27,61,.08);overflow:hidden;">
              <div style="background:' . $surfaceTint . ';border-bottom:1px solid #d8e3dd;padding:26px 28px;">
                <div style="width:44px;height:5px;border-radius:999px;background:' . $accent . ';margin-bottom:18px;">&nbsp;</div>
                <h1 style="margin:0;color:#0d1b3d;font-size:28px;line-height:1.15;font-weight:900;">' . $safeTitle . '</h1>
                <p style="margin:10px 0 0;color:#52645f;font-size:15px;line-height:1.6;">' . $summary . '</p>
              </div>
              <div style="padding:28px;color:#0d1b3d;font-size:15px;line-height:1.7;">
                ' . $body . '
              </div>
              ' . $fallbackBlock . '
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:20px 14px 0;color:#52645f;font-size:12px;line-height:1.6;">
              <div style="font-weight:700;color:#0d1b3d;">Zdajnik - inteligentna nauka do egzaminów zawodowych</div>
              <div>&copy; ' . $year . ' Zdajnik. Wiadomość wysłana automatycznie.</div>
              <div><a href="' . $this->e(BASE_URL) . '" style="color:' . $accentDark . ';text-decoration:none;">' . $this->e(BASE_URL) . '</a> · <a href="mailto:' . $this->e(CONTACT_EMAIL) . '" style="color:' . $accentDark . ';text-decoration:none;">' . $this->e(CONTACT_EMAIL) . '</a></div>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
  }

  private function paragraph(string $text): string
  {
    return '<p style="margin:0 0 16px;color:#0d1b3d;font-size:15px;line-height:1.7;">' . $text . '</p>';
  }

  private function actionButton(string $label, string $url, string $variant = 'primary'): string
  {
    $background = $variant === 'danger' ? '#dc2626' : '#10b981';
    $safeLabel = $this->e($label);
    $safeUrl = $this->e($url);

    return '<table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:24px 0 22px;">
      <tr>
        <td style="border-radius:999px;background:' . $background . ';">
          <a href="' . $safeUrl . '" style="display:inline-block;padding:14px 24px;color:#ffffff;font-size:15px;font-weight:800;text-decoration:none;border-radius:999px;">' . $safeLabel . '</a>
        </td>
      </tr>
    </table>';
  }

  private function verificationCode(string $code): string
  {
    return '<div style="margin:22px 0;padding:18px 20px;background:#0d1b3d;border-radius:16px;color:#ffffff;text-align:center;">
      <div style="font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#a7f3d0;margin-bottom:8px;">Kod weryfikacyjny</div>
      <div style="font-size:34px;line-height:1;font-weight:900;letter-spacing:.18em;">' . $this->e($code) . '</div>
    </div>';
  }

  private function notice(string $text): string
  {
    return '<div style="margin:20px 0 0;padding:14px 16px;background:#eefbf5;border:1px solid #b7ead5;border-radius:14px;color:#0d1b3d;font-size:13px;line-height:1.6;">' . $this->e($text) . '</div>';
  }

  private function dangerNotice(string $text): string
  {
    return '<div style="margin:18px 0;padding:14px 16px;background:#fef2f2;border:1px solid #fecaca;border-radius:14px;color:#7f1d1d;font-size:13px;line-height:1.6;font-weight:700;">' . $this->e($text) . '</div>';
  }

  /**
   * @param array<string, string> $rows
   */
  private function dataRows(array $rows): string
  {
    $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:18px 0;border:1px solid #d8e3dd;border-radius:14px;overflow:hidden;">';

    foreach ($rows as $label => $value) {
      $html .= '<tr>
        <td style="width:38%;padding:12px 14px;background:#f7faf9;border-bottom:1px solid #d8e3dd;color:#52645f;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;">' . $this->e($label) . '</td>
        <td style="padding:12px 14px;border-bottom:1px solid #d8e3dd;color:#0d1b3d;font-size:14px;font-weight:700;word-break:break-word;">' . $this->e($value) . '</td>
      </tr>';
    }

    return $html . '</table>';
  }

  private function toPlainText(string $html): string
  {
    $text = preg_replace('/<br\\s*\\/?>/i', "\n", $html) ?? $html;
    $text = preg_replace('/<\\/p>/i', "\n\n", $text) ?? $text;
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
    $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

    return trim($text);
  }

  private function e(string $value): string
  {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
  }
}
