<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Serwis Poczty E-mail (Mailer).
 *
 * Klasa pełni rolę "Fasady" (Facade) nad biblioteką PHPMailer, upraszczając 
 * wysyłkę wiadomości systemowych (weryfikacje, resety haseł, powiadomienia).
 * Realizuje:
 * - Bezpieczne połączenie z serwerem SMTP przez STARTTLS.
 * - Centralną konfigurację parametrów nadawcy (poświadczenia z $_ENV).
 * - Zarządzanie szablonami wiadomości HTML.
 * - Izolację reszty aplikacji od specyfiki zewnętrznej biblioteki mailowej.
 *
 * @version 1.2.0
 * @author Tobiasz Szerszeń
 * @package App\Services
 */
class Mailer
{
  /** @var PHPMailer Skonfigurowana instancja silnika PHPMailer */
  private PHPMailer $mailer;

  /**
   * Konstruktor serwisu Mailer - Inicjalizuje połączenie SMTP.
   *
   * Metoda wykonuje następujące kroki:
   * 1. Inicjalizacja instancji PHPMailer z obsługą wyjątków.
   * 2. Konfiguracja serwera SMTP (Host, Port, Auth) na podstawie zmiennych środowiskowych.
   * 3. Ustawienie parametrów bezpieczeństwa (TLS) i uwierzytelnienia.
   * 4. Konfiguracja domyślnych nagłówków nadawcy (From) i kodowania (UTF-8).
   *
   * @throws Exception W przypadku krytycznych błędów konfiguracji (brak poświadczeń).
   */
  public function __construct()
  {
    // KROK 1: Inicjalizacja silnika (true = włączenie rzucania wyjątków)
    $this->mailer = new PHPMailer(true);

    // KROK 2: Konfiguracja protokołu SMTP
    $this->mailer->isSMTP();
    $this->mailer->Host = $_ENV['SMTP_HOST'];
    $this->mailer->SMTPAuth = true;
    $this->mailer->Username = $_ENV['SMTP_USER'];

    // Oczyszczenie hasła z ewentualnych spacji (częsty błąd przy kopiowaniu z Google App Passwords)
    $this->mailer->Password = str_replace(' ', '', $_ENV['SMTP_PASS']);

    // KROK 3: Bezpieczeństwo i porty
    $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $this->mailer->Port = 587;

    // KROK 4: Domyślne ustawienia nadawcy i formatowania
    $this->mailer->setFrom($_ENV['SMTP_USER'], 'Examly - Inteligentna Nauka');
    $this->mailer->isHTML(true);
    $this->mailer->CharSet = 'UTF-8';
  }

  /**
   * Wysyła wiadomość e-mail do wskazanego odbiorcy.
   *
   * @param string $to Adres e-mail docelowy.
   * @param string $subject Temat wiadomości.
   * @param string $body Treść wiadomości (obsługuje HTML).
   * @return bool True jeśli wysyłka zakończona sukcesem, false przy błędzie połączenia/SMTP.
   */
  public function send(string $to, string $subject, string $body): bool
  {
    try {
      // Zapobieganie wyciekowi odbiorców przy seryjnym użyciu obiektu
      $this->mailer->clearAllRecipients();

      // Ustawienie parametrów konkretnej przesyłki
      $this->mailer->addAddress($to);
      $this->mailer->Subject = $subject;
      $this->mailer->Body = $body;

      return $this->mailer->send();
    } catch (Exception $e) {
      // Zapisanie błędu w logu systemowym dla celów diagnostycznych
      error_log('[Mailer Error] ' . $this->mailer->ErrorInfo . " | Msg: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Wysyła wiadomość potwierdzającą żądanie usunięcia konta (Soft Delete).
   * 
   * @param string $email
   * @param string $link Link do akcji potwierdzającej (verify-delete-token).
   * @return bool
   */
  public function sendAccountDeletionConfirmationEmail(string $email, string $link): bool
  {
    $subject = 'Potwierdzenie usunięcia Twojego konta - Examly';
    $body = '
      <h1>Witaj!</h1>
      <p>Otrzymaliśmy prośbę o usunięcie Twojego konta w serwisie Examly.</p>
      <p>To działanie jest nieodwracalne po okresie karencji (30 dni). Jeśli chcesz kontynuować, kliknij w przycisk poniżej:</p>
      <p style="text-align: center; margin: 30px 0;">
        <a href="' . $link . '" style="background-color: #d9534f; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">POTWIERDZAM USUNIĘCIE KONTA</a>
      </p>
      <p>Jeśli ta prośba nie została wysłana przez Ciebie, zignoruj tę wiadomość. Twoje dane są bezpieczne.</p>
      <p>Pozdrawiamy,<br>Zespół Examly</p>
    ';

    return $this->send($email, $subject, $body);
  }

  /**
   * Wysyła wiadomość potwierdzającą chęć całkowitego zresetowania postępów nauki.
   * 
   * @param string $email
   * @param string $link Link do akcji potwierdzającej.
   * @param string $examCode Kod kwalifikacji (np. INF.03).
   * @return bool
   */
  public function sendProgressAnonymizationEmail(string $email, string $link, string $examCode): bool
  {
    $subject = 'Potwierdzenie wyzerowania postępu - Examly';
    $body = '
      <h1>Witaj!</h1>
      <p>Otrzymaliśmy prośbę o wyzerowanie Twoich postępów i statystyk dla egzaminu: <strong>' . htmlspecialchars($examCode) . '</strong>.</p>
      <p>Potwierdzenie tej operacji spowoduje zanonimizowanie historii prób i przywrócenie stanu "0 odkrytych pytań".</p>
      <p style="text-align: center; margin: 30px 0;">
        <a href="' . $link . '" style="background-color: #d9534f; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">POTWIERDZAM WYZEROWANIE POSTĘPU</a>
      </p>
      <p>Jeśli to nie Ty zleciłeś tę operację, prosimy o kontakt z administratorem.</p>
      <p>Pozdrawiamy,<br>Zespół Examly</p>
    ';

    return $this->send($email, $subject, $body);
  }
}