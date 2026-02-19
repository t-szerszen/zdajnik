<?php

namespace App\Models;

use App\Core\Database;
use App\Models\User;
use Exception;

/**
 * Model Użytkownika (User).
 *
 * Klasa ta stanowi jedyny, bezpieczny punkt dostępu do danych w tabeli `users`.
 * Hermetyzuje całą logikę operacji na użytkownikach, od rejestracji i logowania
 * po weryfikację i pobieranie danych. Zwraca obiekty-encje typu `User`,
 * a nie surowe tablice danych, co podnosi jakość i spójność kodu.
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 */
class UserModel
{
  /**
   * Instancja centralnego obiektu do obsługi bazy danych (Singleton).
   * @var Database
   */
  private Database $db;

  /**
   * Konstruktor modelu User.
   *
   * Pobiera instancję połączenia z bazą danych, która będzie używana
   * we wszystkich metodach tego modelu.
   */
  public function __construct()
  {
    $this->db = Database::getInstance();
  }

  /**
   * Sprawdza, czy w bazie danych istnieje użytkownik o podanym adresie e-mail.
   *
   * Używane głównie w procesie walidacji formularza rejestracyjnego, aby
   * zapobiec tworzeniu zduplikowanych kont.
   *
   * @param string $email Adres e-mail do sprawdzenia.
   *
   * @return bool `true`, jeśli e-mail istnieje w bazie, w przeciwnym razie `false`.
   */
  public function checkEmail(string $email): bool
  {
    $sql = 'SELECT id FROM users WHERE email = ?';
    return $this->db->fetch($sql, [$email]) !== false;
  }

  /**
   * Zmienia status weryfikacji użytkownika na "zweryfikowany" (`is_verified = 1`).
   *
   * @param int $userId ID użytkownika do zweryfikowania.
   *
   * @return bool `true` w przypadku sukcesu, `false` w przypadku błędu.
   */
  public function verifyUser(int $userId): bool
  {
    $sql = 'UPDATE users SET is_verified = 1 WHERE id = ?';
    return $this->db->execute($sql, [$userId]);
  }

  /**
   * Bazowe zapytanie uwzględniające Soft Delete.
   */
  private function getActiveUserSql(): string
  {
    return "SELECT * FROM users WHERE deleted_at IS NULL AND is_active = 1";
  }

  public function login(array $data): ?User
  {
    // Zmiana: Szukamy po emailu, ignorantując flagę deleted_at/is_active na tym etapie
    // Pozwoli to na wykrycie użytkownika w okresie karencji

    // Oryginalnie: $sql = $this->getActiveUserSql() . " AND email = ? AND auth_provider = 'local'";
    // Nowe podejście:
    $sql = "SELECT * FROM users WHERE email = ? AND auth_provider = 'local'";
    $user_data = $this->db->fetch($sql, [$data['email']]);

    if ($user_data && password_verify($data['password'], $user_data['password_hash'])) {
      // Sprawdź czy użytkownik jest soft-deleted (karencja)
      if ((int) $user_data['is_active'] === 0 && !empty($user_data['deleted_at'])) {
        // Reaktywuj konto
        $this->reactivateUser($user_data['id']);
        // Odśwież dane (np. is_active=1)
        $user_data['is_active'] = 1;
        $user_data['deleted_at'] = null;
      }

      // Jeśli użytkownik jest nieaktywny z innego powodu (np. ban), 
      // można by tu dodać warunek, ale w tym zadaniu skupiamy się na soft delete.

      unset($user_data['password_hash']);
      return new User($user_data);
    }
    return null;
  }

  public function register(array $data): bool
  {
    $sql = "INSERT INTO users (auth_provider, first_name, last_name, email, password_hash)
            VALUES ('local', ?, ?, ?, ?)";

    return $this->db->execute($sql, [
      $data['first_name'],
      $data['last_name'],
      $data['email'],
      password_hash($data['password'], PASSWORD_DEFAULT)
    ]);
  }

  // Fragment UserModel.php
  public function findOrCreateGoogleUser(string $googleId, string $email, string $firstName, ?string $lastName): ?User
  {
    // Szukamy po googleId lub emailu. NIE używamy getActiveUserSql(), aby obsłużyć reaktywację.
    $sql = "SELECT * FROM users WHERE (google_id = ? OR email = ?) LIMIT 1";
    $userData = $this->db->fetch($sql, [$googleId, $email]);

    if ($userData) {
      // Sprawdź czy konto wymaga reaktywacji
      if ((int) $userData['is_active'] === 0 && !empty($userData['deleted_at'])) {
        $this->reactivateUser($userData['id']);
        $userData['is_active'] = 1;
        $userData['deleted_at'] = null;
      }

      $user = new User($userData);
      // Jeśli znaleziony po emailu, a nie miał google_id - aktualizujemy
      if (!$user->getGoogleId()) {
        $this->db->execute("UPDATE users SET google_id = ?, auth_provider = 'google', is_verified = 1 WHERE id = ?", [$googleId, $user->getId()]);
        return $this->getUserById($user->getId());
      }
      return $user;
    }

    if ($this->registerWithGoogleId($googleId, $email, $firstName, $lastName)) {
      return $this->getUserById($this->db->lastInsertId());
    }
    return null;
  }

  public function registerWithGoogleId(string $googleId, string $email, string $firstName, ?string $lastName): bool
  {
    $sql = "INSERT INTO users (google_id, auth_provider, first_name, last_name, email, is_verified) 
            VALUES (?, 'google', ?, ?, ?, 1)";
    return $this->db->execute($sql, [$googleId, $firstName, $lastName, $email]);
  }

  /**
   * Zwraca ID ostatnio wstawionego wiersza do tabeli `users`.
   *
   * Metoda-proxy, wywoływana bezpośrednio po `register()` w celu uzyskania
   * ID nowego użytkownika na potrzeby procesu weryfikacji e-mail.
   *
   * @return int|null ID użytkownika lub `null` w przypadku błędu.
   */
  public function getLastInsertId(): ?int
  {
    return $this->db->lastInsertId();
  }

  public function getUserById(int $id): ?User
  {
    $sql = $this->getActiveUserSql() . " AND id = ?";
    $data = $this->db->fetch($sql, [$id]);
    return $data ? new User($data) : null;
  }

  /**
   * Pobiera obiekt użytkownika na podstawie jego adresu e-mail.
   *
   * @param string $email Adres e-mail użytkownika.
   *
   * @return User|null Obiekt User lub null, jeśli nie znaleziono.
   */
  public function getUserByEmail(string $email): ?User
  {
    $sql = 'SELECT id, google_id, first_name, last_name, email, is_verified, role FROM users WHERE email = ?';
    $user_data = $this->db->fetch($sql, [$email]);

    return $user_data ? new User($user_data) : null;
  }

  /**
   * Sprawdza, czy podane hasło jest zgodne z hasłem użytkownika w bazie.
   *
   * @param int    $userId   ID użytkownika do sprawdzenia.
   * @param string $password Hasło do weryfikacji.
   *
   * @return bool `true`, jeśli hasło jest poprawne, w przeciwnym razie `false`.
   */
  public function checkPassword(int $userId, string $password): bool
  {
    $sql = 'SELECT password_hash FROM users WHERE id = ?';
    $user_data = $this->db->fetch($sql, [$userId]);

    if ($user_data && password_verify($password, $user_data['password_hash'])) {
      return true;
    }

    return false;
  }

  /**
   * Aktualizuje hasło użytkownika w bazie danych.
   *
   * @param int    $userId      ID użytkownika, któremu zmieniamy hasło.
   * @param string $newPassword Nowe, niezaszyfrowane hasło.
   *
   * @return bool `true` w przypadku sukcesu, `false` w przypadku błędu.
   */
  public function updatePassword(int $userId, string $newPassword): bool
  {
    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $sql = 'UPDATE users SET password_hash = ? WHERE id = ?';

    return $this->db->execute($sql, [$newPasswordHash, $userId]);
  }

  /**
   * Aktualizuje imię i nazwisko użytkownika.
   *
   * @param int    $userId      ID użytkownika.
   * @param string $firstName   Nowe imię.
   * @param string $lastName    Nowe nazwisko.
   *
   * @return bool `true` w przypadku sukcesu.
   */
  public function updateName(int $userId, string $firstName, string $lastName): bool
  {
    $sql = 'UPDATE users SET first_name = ?, last_name = ? WHERE id = ?';
    if ($this->db->execute($sql, [$firstName, $lastName, $userId])) {
      // Po udanej aktualizacji w bazie, zaktualizuj obiekt użytkownika w sesji
      $_SESSION['user'] = $this->getUserById($userId);
      return true;
    }
    return false;
  }

  /**
   * Aktualizuje adres e-mail użytkownika i od razu ustawia konto jako zweryfikowane.
   * Używane po pomyślnym kliknięciu w link potwierdzający zmianę e-maila.
   *
   * @param int    $userId   ID użytkownika.
   * @param string $newEmail Nowy, zweryfikowany adres e-mail.
   *
   * @return bool `true` w przypadku sukcesu.
   */
  public function updateAndVerifyEmail(int $userId, string $newEmail): bool
  {
    // Ustawiamy is_verified na 1, ponieważ nowy e-mail został właśnie zweryfikowany
    $sql = 'UPDATE users SET email = ?, is_verified = 1 WHERE id = ?';
    return $this->db->execute($sql, [$newEmail, $userId]);
  }

  /**
   * Implementacja Soft Delete.
   */
  /**
   * Zgłasza żądanie usunięcia konta (Soft Delete) z 30-dniowym okresem karencji.
   * Przed wykonaniem operacji uruchamia "Smart Cleanup" - anonimizację starych kont.
   *
   * @param int $userId ID użytkownika.
   * @return bool
   */
  public function requestDeletion(int $userId): bool
  {
    // 1. Smart Cleanup: Anonimizuj konta starsze niż 30 dni
    $this->anonymizeOldUsers();

    // 2. Soft Delete aktualnego użytkownika
    $sql = "UPDATE users SET is_active = 0, deleted_at = NOW() WHERE id = ?";
    return $this->db->execute($sql, [$userId]);
  }

  /**
   * Reaktywuje konto użytkownika (anuluje usuwanie).
   *
   * @param int $userId ID użytkownika.
   * @return bool
   */
  public function reactivateUser(int $userId): bool
  {
    $sql = "UPDATE users SET is_active = 1, deleted_at = NULL WHERE id = ?";
    return $this->db->execute($sql, [$userId]);
  }

  /**
   * Wykonuje anonimizację użytkowników, którzy mają deleted_at starszy niż 30 dni.
   *
   * @return int Liczba zanonimizowanych rekordów (opcjonalnie, tutaj void/bool).
   */
  private function anonymizeOldUsers(): void
  {
    // Anonimizacja:
    // - google_id = NULL
    // - email = anon + md5(id) + @examly.pl
    // - first_name = 'Anonimowy'
    // - last_name = 'Użytkownik'
    // - password_hash = '' (lub losowy ciąg, ale pusty/null bezpieczniejszy jeśli kolumna pozwala, tu zakładamy string)
    // - auth_provider = 'local' (dla spójności)

    // Warunek: is_active = 0 AND deleted_at < TERAZ - 30 DNI
    $sql = "UPDATE users 
            SET 
              google_id = NULL,
              email = CONCAT('anon_', MD5(id), '@examly.pl'),
              first_name = 'Anonimowy',
              last_name = 'Użytkownik',
              password_hash = 'ANONYMIZED',
              auth_provider = 'local'
            WHERE is_active = 0 
              AND deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";

    $this->db->execute($sql);
  }
}
