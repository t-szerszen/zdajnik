<?php

namespace App\Services;

use App\Core\Database;

/**
 * Serwis Zarządzania Tokenami (TokenService).
 *
 * Klasa odpowiada za bezpieczny cykl życia jednorazowych kluczy autoryzacyjnych (Tokenów) 
 * oraz kodów numerycznych (OTP). Obsługuje procesy wymagające zewnętrznej weryfikacji:
 * - Potwierdzanie adresu e-mail (Double Opt-In).
 * - Resetowanie zapomnianego hasła.
 * - Autoryzacja zmiany adresu e-mail.
 * - Potwierdzanie operacji wrażliwych (usuwanie konta, reset postępu).
 *
 * Wszystkie tokeny posiadają czas wygaśnięcia (standardowo 1h) i są automatycznie 
 * czyszczone przy generowaniu nowych kluczy.
 *
 * @version 1.2.0
 * @author Tobiasz Szerszeń
 * @package App\Services
 */
class TokenService
{
  /** @var Database Instancja połączenia z bazą danych */
  private Database $db;

  /**
   * Inicjalizuje serwis tokenów.
   */
  public function __construct()
  {
    $this->db = Database::getInstance();
  }

  /**
   * Generuje nowy, unikalny token i (opcjonalnie) kod numeryczny.
   *
   * Proces generowania:
   * 1. Usunięcie wygasłych tokenów z całej bazy (Smart Cleanup).
   * 2. Wyczyszczenie starych tokenów tego samego typu dla danego użytkownika 
   *    (zapewnienie posiadania tylko jednego aktywnego linku).
   * 3. Wygenerowanie bezpiecznego kryptograficznie ciągu 64 znaków (bin2hex + random_bytes).
   * 4. Wygenerowanie 6-cyfrowego kodu Auth Code (dla resetów haseł).
   * 5. Zapis rekordu z okresem ważności 1 godziny.
   *
   * @param int $userId ID użytkownika.
   * @param string $type Typ tokena (np. 'password_reset', 'email_verify').
   * @param string|null $data Opcjonalne dodatkowe dane zaszyte w tokenie (np. stary e-mail).
   * @return array|false Tablica ['token', 'code'] lub false w razie błędu DB.
   */
  public function generateToken(int $userId, string $type, ?string $data = null): array|false
  {
    // KROK 1: Konserwacja tabeli - usuwanie rekordów przeterminowanych
    $this->db->execute('DELETE FROM user_tokens WHERE expires_at < NOW()');

    // KROK 2: Unieważnienie poprzednich linków tego samego typu dla użytkownika (Safety First)
    $this->deleteTokensForUserByType($userId, $type);

    // KROK 3: Generowanie bezpiecznego identyfikatora (32 bajty = 64 znaki hex)
    $token = bin2hex(random_bytes(32));
    $code = null;

    // KROK 4: Generowanie kodu pomocniczego (Auth Code) dla wybranych procesów
    if ($type === 'password_reset' || $type === 'email_change') {
      $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    // KROK 5: Utrwalenie tokena w bazie danych
    $sql = "INSERT INTO user_tokens (user_id, token, type, token_data, auth_code, expires_at)
            VALUES (:user_id, :token, :type, :token_data, :auth_code, DATE_ADD(NOW(), INTERVAL 1 HOUR))";

    $params = [
      ':user_id' => $userId,
      ':token' => $token,
      ':type' => $type,
      ':token_data' => $data,
      ':auth_code' => $code,
    ];

    if ($this->db->execute($sql, $params)) {
      return ['token' => $token, 'code' => $code];
    }

    return false;
  }

  /**
   * Usuwa wszystkie tokeny określonego typu dla danego użytkownika.
   * 
   * @param int $userId ID użytkownika.
   * @param string $type Typ tokena (np. 'email_verify').
   * @return void
   */
  public function deleteTokensForUserByType(int $userId, string $type): void
  {
    $sql = 'DELETE FROM user_tokens WHERE user_id = ? AND type = ?';
    $this->db->execute($sql, [$userId, $type]);
  }

  /**
   * Pobiera i waliduje rekord tokena z bazy danych.
   *
   * Metoda automatycznie odrzuca tokeny, które przekroczyły swój czas ważności.
   *
   * Podanie $expectedType jest zalecane: bez niego token jednego procesu
   * (np. zmiany e-maila) dałoby się użyć w innym (np. aktywacji konta).
   *
   * @param string|null $token Surowa wartość tokena (np. pobrana z adresu URL).
   * @param string|null $expectedType Wymagany typ tokena (np. 'email_verify').
   * @return array|null Tablica z danymi rekordu lub null w przypadku braku/wygasnięcia/złego typu.
   */
  public function getTokenRecord(?string $token, ?string $expectedType = null): ?array
  {
    if ($token === null || $token === '') {
      return null;
    }

    // Pobieramy tylko tokeny, które nie wygasły
    $sql = 'SELECT * FROM user_tokens WHERE token = ? AND expires_at > NOW()';
    $result = $this->db->fetch($sql, [$token]);

    if (!$result) {
      return null;
    }

    if ($expectedType !== null && $result['type'] !== $expectedType) {
      return null;
    }

    return $result;
  }
}
