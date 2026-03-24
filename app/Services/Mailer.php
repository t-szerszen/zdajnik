<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Klasa Mailer - Usługa do wysyłania wiadomości e-mail.
 *
 * Ta klasa działa jako "fasada" (Facade) lub "adapter" dla biblioteki PHPMailer.
 * Jej celem jest uproszczenie procesu wysyłania e-maili i centralizacja
 * konfiguracji w jednym miejscu. Dzięki temu reszta aplikacji nie musi wiedzieć,
 * jakiej biblioteki używamy – wystarczy wywołać prostą metodę `send()`.
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 */
class Mailer
{
  /**
   * Przechowuje skonfigurowaną instancję obiektu PHPMailer.
   * @var PHPMailer
   */
  private PHPMailer $mailer;

  /**
   * Konstruktor inicjalizujący i konfigurujący PHPMailer do wysyłki przez SMTP.
   *
   * Logika działania:
   * 1. Tworzy nową instancję PHPMailer z włączoną obsługą wyjątków.
   * 2. Konfiguruje wszystkie niezbędne parametry do połączenia z serwerem SMTP
   *    (w tym przypadku Gmail).
   * 3. Ustawia domyślne dane nadawcy oraz format wiadomości na HTML.
   * 4. W przypadku błędu konfiguracji rzuca wyjątek, aby zapobiec
   *    działaniu niepoprawnie skonfigurowanej usługi.
   *
   * @throws Exception Jeśli konfiguracja mailera się nie powiedzie.
   */
  public function __construct()
  {
    // Utwórz instancję PHPMailer; przekazanie `true` włącza wyjątki.
    $this->mailer = new PHPMailer(true);

    // --- Konfiguracja serwera SMTP ---
    // Użyj SMTP do wysyłki.
    $this->mailer->isSMTP();
    // Adres serwera SMTP Gmaila.
    $this->mailer->Host = $_ENV['SMTP_HOST'];
    // Włącz autoryzację SMTP.
    $this->mailer->SMTPAuth = true;
    // E-mail do wysyłania wiadomości.
    $this->mailer->Username = $_ENV['SMTP_USER'];
    // To jest hasło aplikacji Google, a nie hasło do konta.
    // Usuwamy spacje, jeśli użytkownik je wkleił.
    $this->mailer->Password = str_replace(' ', '', $_ENV['SMTP_PASS']);
    // Włącz szyfrowanie TLS.
    $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    // Port TCP do połączenia.
    $this->mailer->Port = 587;

    // --- Domyślne ustawienia wiadomości ---
    // Ustaw domyślnego nadawcę.
    $this->mailer->setFrom($_ENV['SMTP_USER'], 'Examly');
    // Ustaw domyślny format wiadomości na HTML.
    $this->mailer->isHTML(true);
    // Ustaw kodowanie znaków na UTF-8.
    $this->mailer->CharSet = 'UTF-8';
  }

  /**
   * Wysyła wiadomość e-mail do jednego odbiorcy.
   *
   * Metoda opakowuje logikę wysyłki w blok `try-catch`, aby gracefully
   * obsłużyć ewentualne błędy (np. błąd połączenia z serwerem, zły adres)
   * i zwrócić prostą wartość `true`/`false`.
   *
   * @param string $to Adres e-mail odbiorcy.
   * @param string $subject Temat wiadomości.
   * @param string $body Treść wiadomości w formacie HTML.
   *
   * @return bool `true`, jeśli wysyłka się powiodła, `false` w przypadku błędu.
   */
  public function send(string $to, string $subject, string $body): bool
  {
    try {
      // Krok 1: Wyczyść listę odbiorców z poprzednich wysyłek.
      // Kluczowe, jeśli ten sam obiekt Mailer miałby być użyty wielokrotnie.
      $this->mailer->clearAllRecipients();

      // Krok 2: Ustaw dane konkretnej wiadomości.
      $this->mailer->addAddress($to);
      $this->mailer->Subject = $subject;
      $this->mailer->Body = $body;

      // Krok 3: Wyślij e-mail.
      return $this->mailer->send();
    } catch (Exception $e) {
      // W przypadku błędu wysyłki, zapisz go w logu serwera
      // i zwróć `false`, informując, że operacja się nie powiodła.
      error_log('Błąd wysyłki e-maila: ' . $this->mailer->ErrorInfo . " | Exception: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Wysyła e-mail z linkiem potwierdzającym chęć usunięcia konta.
   *
   * @param string $email Adres e-mail użytkownika.
   * @param string $link  Link weryfikacyjny (np. https://examly.pl/potwierdz-usuniecie?token=XYZ).
   *
   * @return bool
   */
  public function sendAccountDeletionConfirmationEmail(string $email, string $link): bool
  {
    $subject = 'Potwierdzenie usunięcia konta - Examly';
    $body = '
      <h1>Witaj!</h1>
      <p>Otrzymaliśmy prośbę o usunięcie Twojego konta w serwisie Examly.</p>
      <p>Decyzja ta jest ważna, dlatego prosimy o jej potwierdzenie. Kliknij w poniższy przycisk, aby trwale rozpocząć procedurę usuwania konta (z 30-dniowym okresem karencji).</p>
      <p style="text-align: center; margin: 20px 0;">
        <a href="' . $link . '" style="background-color: #d9534f; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">POTWIERDZAM USUNIĘCIE KONTA</a>
      </p>
      <p>Jeśli to nie Ty zleciłeś/aś tę operację, zignoruj tę wiadomość - Twoje konto pozostanie bezpieczne.</p>
      <p>Pozdrawiamy,<br>Zespół Examly</p>
    ';

    return $this->send($email, $subject, $body);
  }

  /**
   * Wysyła e-mail z linkiem potwierdzającym w celu wyzerowania postępu edukacyjnego.
   *
   * @param string $email Adres e-mail użytkownika.
   * @param string $link  Link weryfikacyjny.
   * @param string $examCode Kod egzaminu (np. INF.02).
   *
   * @return bool
   */
  public function sendProgressAnonymizationEmail(string $email, string $link, string $examCode): bool
  {
    $subject = 'Potwierdzenie wyzerowania postępu - Examly';
    $body = '
      <h1>Witaj!</h1>
      <p>Otrzymaliśmy prośbę o wyzerowanie Twojego postępu dla egzaminu <strong>' . htmlspecialchars($examCode) . '</strong>.</p>
      <p>Kliknij w poniższy przycisk, aby trwale zanonimizować swoje dotychczasowe próby dla tego egzaminu i całkowicie skasować go z historii konta.</p>
      <p style="text-align: center; margin: 20px 0;">
        <a href="' . $link . '" style="background-color: #d9534f; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">POTWIERDZAM WYZEROWANIE POSTĘPU</a>
      </p>
      <p>Jeśli to nie Ty zleciłeś/aś tę operację, zignoruj tę wiadomość.</p>
      <p>Pozdrawiamy,<br>Zespół Examly</p>
    ';

    return $this->send($email, $subject, $body);
  }
}