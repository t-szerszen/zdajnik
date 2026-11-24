<?php

namespace App\Services;

use App\Core\Database;

/**
 * Limit prób dla formularzy publicznych (logowanie, rejestracja, reset hasła, kontakt).
 *
 * Liczniki są w bazie (tabela request_throttle), a nie w sesji - limit w sesji
 * omija się, po prostu nie odsyłając ciasteczka. Klucz to SHA-256 z zakresu
 * i identyfikatora (IP albo e-mail), więc w bazie nie zostają jawne adresy.
 *
 * @version 1.0.0
 * @author Tobiasz Szerszeń
 * @package App\Services
 */
class ThrottleService
{
  private Database $db;

  public function __construct()
  {
    $this->db = Database::getInstance();
  }

  /**
   * Czy w bieżącym oknie wykorzystano już limit prób.
   */
  public function tooManyAttempts(string $scope, string $identifier, int $maxAttempts, int $windowSeconds): bool
  {
    $row = $this->db->fetch(
      "SELECT hits FROM request_throttle
       WHERE throttle_key = ? AND window_start > NOW() - INTERVAL ? SECOND",
      [$this->key($scope, $identifier), $windowSeconds]
    );

    return $row !== false && (int) $row['hits'] >= $maxAttempts;
  }

  /**
   * Zapisuje próbę. Po upływie okna licznik startuje od nowa.
   */
  public function hit(string $scope, string $identifier, int $windowSeconds): void
  {
    $this->db->execute(
      "INSERT INTO request_throttle (throttle_key, hits, window_start)
       VALUES (?, 1, NOW())
       ON DUPLICATE KEY UPDATE
         hits = IF(window_start <= NOW() - INTERVAL ? SECOND, 1, hits + 1),
         window_start = IF(window_start <= NOW() - INTERVAL ? SECOND, NOW(), window_start)",
      [$this->key($scope, $identifier), $windowSeconds, $windowSeconds]
    );

    // Okazjonalne sprzątanie starych liczników (bez osobnego crona).
    if (random_int(1, 100) === 1) {
      $this->db->execute("DELETE FROM request_throttle WHERE window_start < NOW() - INTERVAL 1 DAY");
    }
  }

  /**
   * Kasuje licznik (np. po udanym logowaniu).
   */
  public function clear(string $scope, string $identifier): void
  {
    $this->db->execute("DELETE FROM request_throttle WHERE throttle_key = ?", [$this->key($scope, $identifier)]);
  }

  /**
   * Adres IP klienta (identyfikator dla limitów po IP).
   */
  public static function clientIp(): string
  {
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
  }

  private function key(string $scope, string $identifier): string
  {
    return hash('sha256', $scope . '|' . mb_strtolower(trim($identifier)));
  }
}
