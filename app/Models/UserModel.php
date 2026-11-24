<?php

namespace App\Models;

use App\Core\Database;
use App\Models\User;
use Exception;

/**
 * Model Użytkownika (UserModel).
 *
 * Klasa stanowi centralny punkt dostępu do danych w tabeli `users`. Hermetyzuje 
 * całą logikę biznesową związaną z cyklem życia konta użytkownika:
 * - Autentykacja (logowanie lokalne i przez Google OAuth).
 * - Zarządzanie tożsamością (rejestracja, weryfikacja e-mail, reset hasła).
 * - Aktualizacja profilu (zmiana imienia, nazwiska, adresu e-mail).
 * - Zaawansowane mechanizmy prywatności: Soft Delete z 30-dniową karencją 
 *   oraz automatyczna anonimizacja danych po okresie wygaszenia.
 *
 * Zwraca obiekty-encje typu `User`, zapewniając silne typowanie w warstwie kontrolerów.
 *
 * @version 1.2.0
 * @author Tobiasz Szerszeń
 * @package App\Models
 */
class UserModel
{
  /** @var int Liczba dni na anulowanie usunięcia konta (ponowne zalogowanie) */
  public const DELETION_GRACE_DAYS = 30;

  /** @var Database Instancja połączenia z bazą danych */
  private Database $db;

  /**
   * Inicjalizuje model użytkownika.
   */
  public function __construct()
  {
    $this->db = Database::getInstance();
  }

  // --- METODY DOSTĘPU (READ) ---

  /**
   * Zwraca bazowe zapytanie SQL uwzględniające tylko aktywne (nieusunięte) konta.
   * 
   * @return string
   */
  private function getActiveUserSql(): string
  {
    return "SELECT * FROM users WHERE deleted_at IS NULL AND is_active = 1";
  }

  /**
   * Pobiera użytkownika po jego unikalnym identyfikatorze ID.
   * 
   * @param int $id ID użytkownika.
   * @return User|null Instancja User lub null w razie braku aktywnego rekordu.
   */
  public function getUserById(int $id): ?User
  {
    $sql = $this->getActiveUserSql() . " AND id = ?";
    $data = $this->db->fetch($sql, [$id]);
    return $data ? new User($data) : null;
  }

  /**
   * Pobiera użytkownika na podstawie adresu e-mail.
   * 
   * @param string $email Adres e-mail.
   * @return User|null
   */
  public function getUserByEmail(string $email): ?User
  {
    $sql = 'SELECT id, google_id, first_name, last_name, email, is_verified, role, avatar_path FROM users WHERE email = ?';
    $user_data = $this->db->fetch($sql, [$email]);

    return $user_data ? new User($user_data) : null;
  }

  /**
   * Sprawdza, czy w systemie rezerwacji figuruje już dany adres e-mail.
   * 
   * @param string $email Adres e-mail do sprawdzenia.
   * @return bool True jeśli e-mail zajęty.
   */
  public function checkEmail(string $email): bool
  {
    $sql = 'SELECT id FROM users WHERE email = ?';
    return $this->db->fetch($sql, [$email]) !== false;
  }

  // --- AUTENTYKACJA (AUTH) ---

  /**
   * Przetwarza próbę logowania lokalnego (login + hasło).
   *
   * Metoda wykonuje następujące kroki:
   * 1. Wyszukanie rekordu użytkownika po adresie e-mail (pomijając Soft Delete).
   * 2. Wykonanie bezpiecznej weryfikacji hasła (password_verify).
   * 3. Obsługa reaktywacji konta, jeśli użytkownik zalogował się w okresie karencji (Soft Delete).
   * 4. Zwrócenie zainicjalizowanego obiektu User.
   *
   * @param array $data Dane logowania (email, password).
   * @return User|null Obiekt użytkownika przy sukcesie, null przy błędnych poświadczeniach.
   */
  public function login(array $data): ?User
  {
    // KROK 1: Lokalizacja rekordu (szukamy również wśród kont oznaczonych do usunięcia)
    $sql = "SELECT * FROM users WHERE email = ? AND auth_provider = 'local'";
    $user_data = $this->db->fetch($sql, [$data['email']]);

    // KROK 2: Weryfikacja poświadczeń
    if ($user_data && password_verify($data['password'], $user_data['password_hash'])) {

      // KROK 3: Automatyczna reaktywacja (Anulowanie Soft Delete) - tylko w okresie karencji.
      // Po jego upływie konto jest od razu anonimizowane i nie da się go przywrócić.
      if ((int) $user_data['is_active'] === 0 && !empty($user_data['deleted_at'])) {
        if (!$this->isWithinDeletionGracePeriod($user_data['deleted_at'])) {
          $this->anonymizeUsers([(int) $user_data['id']]);
          return null;
        }

        $this->reactivateUser($user_data['id']);
        $user_data['is_active'] = 1;
        $user_data['deleted_at'] = null;
      }

      unset($user_data['password_hash']);
      return new User($user_data);
    }

    return null;
  }

  /**
   * Obsługuje logowanie/rejestrację przez Google OAuth.
   *
   * Metoda "Find or Create":
   * 1. Próba znalezienia użytkownika po ID Google lub adresie e-mail.
   * 2. Reaktywacja konta w razie potrzeby.
   * 3. Automatyczne powiązanie (Linking) konta lokalnego z Google, jeśli e-mail się zgadza.
   * 4. Utworzenie nowego konta w przypadku pierwszego logowania.
   *
   * @param string $googleId Unikalne ID dostarczone przez Google.
   * @param string $email Adres e-mail z Google.
   * @param string $firstName Imię.
   * @param string|null $lastName Nazwisko.
   * @return User|null
   */
  public function findOrCreateGoogleUser(string $googleId, string $email, string $firstName, ?string $lastName): ?User
  {
    // KROK 1: Sprawdzenie istnienia użytkownika
    $sql = "SELECT * FROM users WHERE (google_id = ? OR email = ?) LIMIT 1";
    $userData = $this->db->fetch($sql, [$googleId, $email]);

    // KROK 2a: Konto usunięte dawniej niż okres karencji - anonimizujemy je
    // (odpina google_id i e-mail), a dalej tworzy się nowe, puste konto.
    if (
      $userData
      && (int) $userData['is_active'] === 0
      && !empty($userData['deleted_at'])
      && !$this->isWithinDeletionGracePeriod($userData['deleted_at'])
    ) {
      $this->anonymizeUsers([(int) $userData['id']]);
      $userData = false;
    }

    if ($userData) {
      // KROK 2b: Obsługa reaktywacji w okresie karencji
      if ((int) $userData['is_active'] === 0 && !empty($userData['deleted_at'])) {
        $this->reactivateUser($userData['id']);
        $userData['is_active'] = 1;
        $userData['deleted_at'] = null;
      }

      $user = new User($userData);

      // KROK 3: Powiązanie konta (jeśli wcześniej logował się tylko lokalnie)
      if (!$user->getGoogleId()) {
        $this->db->execute("UPDATE users SET google_id = ?, auth_provider = 'google', is_verified = 1 WHERE id = ?", [$googleId, $user->getId()]);
        return $this->getUserById($user->getId());
      }
      return $user;
    }

    // KROK 4: Tworzenie nowego konta (Auto-registration)
    if ($this->registerWithGoogleId($googleId, $email, $firstName, $lastName)) {
      return $this->getUserById($this->db->lastInsertId());
    }
    return null;
  }

  // --- ZAPIS (WRITE) ---

  /**
   * Rejestruje nowego użytkownika lokalnego w bazie danych.
   * 
   * @param array $data Dane rejestracyjne (first_name, last_name, email, password).
   * @return bool
   */
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

  /**
   * Tworzy konto użytkownika na podstawie danych z Google.
   * 
   * @param string $googleId
   * @param string $firstName
   * @param string|null $lastName
   * @param string $email
   * @return bool
   */
  public function registerWithGoogleId(string $googleId, string $email, string $firstName, ?string $lastName): bool
  {
    $sql = "INSERT INTO users (google_id, auth_provider, first_name, last_name, email, is_verified) 
            VALUES (?, 'google', ?, ?, ?, 1)";
    return $this->db->execute($sql, [$googleId, $firstName, $lastName, $email]);
  }

  /**
   * Aktualizuje poświadczenia hasła użytkownika.
   * 
   * @param int $userId
   * @param string $newPassword Surowe hasło (zostanie zahashowane).
   * @return bool
   */
  public function updatePassword(int $userId, string $newPassword): bool
  {
    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $sql = 'UPDATE users SET password_hash = ? WHERE id = ?';

    return $this->db->execute($sql, [$newPasswordHash, $userId]);
  }

  /**
   * Zmienia flagę weryfikacji konta na aktywną (is_verified = 1).
   * 
   * @param int $userId
   * @return bool
   */
  public function verifyUser(int $userId): bool
  {
    $sql = 'UPDATE users SET is_verified = 1 WHERE id = ?';
    return $this->db->execute($sql, [$userId]);
  }

  /**
   * Aktualizuje imię i nazwisko profilu użytkownika.
   * 
   * @return bool Status operacji. W przypadku sukcesu aktualizuje również obiekt sesji.
   */
  public function updateName(int $userId, string $firstName, string $lastName): bool
  {
    $sql = 'UPDATE users SET first_name = ?, last_name = ? WHERE id = ?';
    if ($this->db->execute($sql, [$firstName, $lastName, $userId])) {
      // Synchronizacja aktualnego obiektu sesji z nowymi danymi w bazie
      $_SESSION['user'] = $this->getUserById($userId);
      return true;
    }
    return false;
  }

  /**
   * Aktualizuje ścieżkę do awatara użytkownika.
   * 
   * @param int $userId
   * @param string|null $avatarPath Ścieżka do pliku awatara (np. defaults/1.svg) lub null
   * @return bool
   */
  public function updateAvatar(int $userId, ?string $avatarPath): bool
  {
    $sql = 'UPDATE users SET avatar_path = ? WHERE id = ?';
    if ($this->db->execute($sql, [$avatarPath, $userId])) {
      if (isset($_SESSION['user'])) {
        $_SESSION['user'] = $this->getUserById($userId);
      }
      return true;
    }
    return false;
  }

  /**
   * Zmienia adres e-mail i wymusza ponowną (bądź natychmiastową) weryfikację.
   * 
   * @param int $userId
   * @param string $newEmail Nowy adres e-mail.
   * @return bool
   */
  public function updateAndVerifyEmail(int $userId, string $newEmail): bool
  {
    $sql = 'UPDATE users SET email = ?, is_verified = 1 WHERE id = ?';
    return $this->db->execute($sql, [$newEmail, $userId]);
  }

  /**
   * Sprawdza poprawność hasła dla konkretnego ID użytkownika.
   * 
   * @param int $userId
   * @param string $password Hasło do sprawdzenia.
   * @return bool
   */
  public function checkPassword(int $userId, string $password): bool
  {
    $sql = 'SELECT password_hash FROM users WHERE id = ?';
    $user_data = $this->db->fetch($sql, [$userId]);

    return $user_data && password_verify($password, $user_data['password_hash']);
  }

  // --- ZARZĄDZANIE KONTEM I PRYWATNOŚĆ (PRIVACY) ---

  /**
   * Zgłasza żądanie usunięcia konta (Soft Delete).
   *
   * Ustawia flagę `is_active = 0` i zapisuje czas żądania w `deleted_at`. 
   * Uruchamia również automatyczny proces anonimizacji dla rekordów starszych niż 30 dni.
   *
   * @param int $userId ID użytkownika żądającego usunięcia.
   * @return bool
   */
  public function requestDeletion(int $userId): bool
  {
    // KROK: Smart Cleanup - anonimizacja rekordów, którym upłynął termin karencji
    // (główny mechanizm to codzienny cron: scripts/anonymize_deleted_users.php).
    $this->anonymizeExpiredDeletedUsers();

    // KROK: Oznaczenie bieżącego konta jako nieaktywne (Soft Delete)
    $sql = "UPDATE users SET is_active = 0, deleted_at = NOW() WHERE id = ?";
    return $this->db->execute($sql, [$userId]);
  }

  /**
   * Anuluje proces usuwania konta i przywraca mu pełną aktywność.
   * 
   * @param int $userId
   * @return bool
   */
  public function reactivateUser(int $userId): bool
  {
    $sql = "UPDATE users SET is_active = 1, deleted_at = NULL WHERE id = ?";
    return $this->db->execute($sql, [$userId]);
  }

  /**
   * Sprawdza, czy od żądania usunięcia konta nie minął jeszcze okres karencji.
   *
   * @param string $deletedAt Wartość kolumny deleted_at.
   * @return bool
   */
  public function isWithinDeletionGracePeriod(string $deletedAt): bool
  {
    $deletedAtTs = strtotime($deletedAt);
    if ($deletedAtTs === false) {
      return false;
    }

    return $deletedAtTs > strtotime('-' . self::DELETION_GRACE_DAYS . ' days');
  }

  /**
   * Systemowa procedura anonimizacji kont usuniętych dawniej niż okres karencji.
   *
   * Uruchamiana codziennie przez cron (scripts/anonymize_deleted_users.php),
   * dodatkowo przy każdym żądaniu usunięcia konta.
   *
   * @return int Liczba zanonimizowanych kont.
   */
  public function anonymizeExpiredDeletedUsers(): int
  {
    // KROK 1: Identyfikacja kandydatów do wygaszenia (pomijamy już zanonimizowane)
    $sqlFetch = "SELECT id FROM users
                 WHERE is_active = 0
                   AND deleted_at < DATE_SUB(NOW(), INTERVAL " . self::DELETION_GRACE_DAYS . " DAY)
                   AND (password_hash IS NULL OR password_hash <> 'ANONYMIZED')";
    $userIds = array_map('intval', array_column($this->db->fetchAll($sqlFetch), 'id'));

    $this->anonymizeUsers($userIds);

    return count($userIds);
  }

  /**
   * Trwale odcina wskazane konta od danych osobowych:
   * 1. Rozwiązanie powiązań w historii podejść (user_id = NULL w attempts).
   * 2. Całkowite usunięcie postępu nauki (user_progress).
   * 3. Zastąpienie danych w tabeli `users` placeholderami i sumami kontrolnymi MD5.
   *
   * @param int[] $userIds
   * @return void
   */
  private function anonymizeUsers(array $userIds): void
  {
    if (empty($userIds)) {
      return;
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));

    // KROK 2: Anonimizacja historii (odwiązanie statystyk od osoby)
    $sqlAttempts = "UPDATE attempts SET user_id = NULL WHERE user_id IN ($placeholders)";
    $this->db->execute($sqlAttempts, $userIds);

    // Historia odpowiedzi z trybu "jedno pytanie" - tak samo jak podejścia:
    // zostaje do statystyk treści, ale bez powiązania z osobą.
    $sqlSingleAnswers = "UPDATE single_question_answers SET user_id = NULL WHERE user_id IN ($placeholders)";
    $this->db->execute($sqlSingleAnswers, $userIds);

    // KROK 3: Wyczyszczenie szczegółowego progresu (oszczędność miejsca i prywatność)
    $sqlProgress = "DELETE FROM user_progress WHERE user_id IN ($placeholders)";
    $this->db->execute($sqlProgress, $userIds);

    // KROK 4: Zakamuflowanie rekordu użytkownika
    $sqlUserUpdate = "UPDATE users 
            SET 
              google_id = NULL,
              email = CONCAT('anon_', MD5(id), '@zdajnik.pl'),
              first_name = 'Anonimowy',
              last_name = 'Użytkownik',
              password_hash = 'ANONYMIZED',
              auth_provider = 'local',
              avatar_path = NULL
            WHERE id IN ($placeholders)";

    $this->db->execute($sqlUserUpdate, $userIds);
  }
}
