<?php

namespace App\Services;

use App\Models\UserModel;

/**
 * Serwis Walidacji (Validator).
 *
 * Klasa dedykowana wyłącznie do weryfikacji poprawności i bezpieczeństwa danych 
 * wejściowych. Izoluje reguły biznesowe od logiki kontrolerów.
 * Oferuje zaawansowane mechanizmy:
 * - Walidacja danych osobowych (Regex Unicode).
 * - Analiza siły haseł (Complexity policy).
 * - Integracja z zewnętrznym API "Have I Been Pwned" (ochrona przed wyciekami).
 * - Weryfikacja unikalności adresów e-mail w bazie danych.
 *
 * @version 1.2.0
 * @author Tobiasz Szerszeń
 * @package App\Services
 */
class Validator
{
  /** @var UserModel Model dostępu do danych użytkownika (do sprawdzania unikalności) */
  private UserModel $userModel;

  /**
   * Inicjalizuje serwis walidacji.
   */
  public function __construct()
  {
    $this->userModel = new UserModel();
  }

  /**
   * Wykonuje kompletną walidację zestawu danych rejestracyjnych.
   *
   * @param array $formData Dane z formularza (first_name, last_name, email, password, confirm_password).
   * @return array Tablica komunikatów o błędach (pusta jeśli dane poprawne).
   */
  public function validateRegistrationData(array $formData): array
  {
    $errors = [];

    // KROK 1: Detekcja błędów w danych osobowych
    $nameErrors = $this->validateNames($formData['first_name'] ?? '', $formData['last_name'] ?? '');
    $errors = array_merge($errors, $nameErrors);

    // KROK 2: Walidacja formatu i unikalności adresu e-mail
    $email = trim($formData['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $errors[] = 'Podano nieprawidłowy format adresu e-mail.';
    } elseif ($this->userModel->checkEmail($email)) {
      $errors[] = 'Konto z podanym adresem e-mail już istnieje w systemie.';
    }

    // KROK 3: Walidacja polityki haseł
    $passwordErrors = $this->validatePasswordStrength(
      $formData['password'] ?? '',
      $formData['confirm_password'] ?? ''
    );
    $errors = array_merge($errors, $passwordErrors);

    return $errors;
  }

  /**
   * Implementuje restrykcyjną politykę siły haseł.
   * 
   * Wymagania: minimum 8 znaków, wielka/mała litera, cyfra, znak specjalny.
   * Dodatkowo sprawdza, czy hasło nie figuruje w publicznych bazach wycieków danych.
   *
   * @param string $password Hasło do sprawdzenia.
   * @param string $confirm Potwierdzenie hasła.
   * @return array
   */
  public function validatePasswordStrength(string $password, string $confirm): array
  {
    $errors = [];

    // KROK: Minimalna długość
    if (mb_strlen($password) < 8) {
      $errors[] = 'Hasło musi posiadać co najmniej 8 znaków.';
    }
    // KROK: Prewencja wycieków danych (Pwned Passwords API)
    elseif ($this->isPasswordPwned($password)) {
      $errors[] = 'To hasło zostało odnalezione w publicznych wyciekach danych. Ze względów bezpieczeństwa wybierz inne.';
    }
    // KROK: Sprawdzenie stopnia złożoności (Entropy)
    else {
      if (!preg_match('/[A-Z]/', $password))
        $errors[] = 'Hasło musi zawierać przynajmniej jedną wielką literę.';
      if (!preg_match('/[a-z]/', $password))
        $errors[] = 'Hasło musi zawierać przynajmniej jedną małą literę.';
      if (!preg_match('/[0-9]/', $password))
        $errors[] = 'Hasło musi zawierać przynajmniej jedną cyfrę.';
      if (!preg_match('/[\W_]/', $password))
        $errors[] = 'Hasło musi zawierać przynajmniej jeden znak specjalny.';
    }

    // KROK: Zgodność obu pól
    if ($password !== $confirm) {
      $errors[] = 'Podane hasła znacznie się od siebie różnią.';
    }

    return $errors;
  }

  /**
   * Weryfikuje zgodność kodów autoryzacyjnych (OTP).
   * 
   * @return array
   */
  public function validateAuthCode(string $otpCode, string $authCode): array
  {
    $errors = [];
    if ($otpCode !== $authCode) {
      $errors[] = 'Podany kod autoryzacji jest błędny lub wygasł.';
    }
    return $errors;
  }

  /**
   * Zaawansowana walidacja imienia i nazwiska z uwzględnieniem znaków narodowych (Unicode).
   * 
   * @param string $firstName
   * @param string $lastName
   * @return array
   */
  public function validateNames(string $firstName, string $lastName): array
  {
    $errors = [];

    // Reguła: Litery, dopuszczalny pojedynczy łącznik lub spacja.
    $nameRegex = '/^[\p{L}]+(?:[\s-][\p{L}]+)*$/u';

    $fields = [
      'Imię' => trim($firstName),
      'Nazwisko' => trim($lastName)
    ];

    foreach ($fields as $label => $value) {
      if (mb_strlen($value) < 2) {
        $errors[] = "{$label} jest za krótkie (minimum 2 znaki).";
      } elseif (mb_strlen($value) > 50) {
        $errors[] = "{$label} jest za długie (maksimum 50 znaków).";
      } elseif (!preg_match($nameRegex, $value)) {
        $errors[] = "{$label} zawiera niedozwolone znaki specjalne.";
      } elseif (!preg_match('/^\p{Lu}/u', $value)) {
        $errors[] = "{$label} musi zaczynać się od wielkiej litery.";
      }
    }

    return $errors;
  }

  /**
   * Waliduje żądanie zmiany adresu e-mail przez zalogowanego użytkownika.
   * 
   * @param string $newEmail
   * @param string $password Hasło (do re-autoryzacji).
   * @param int $userId
   * @return array
   */
  public function validateEmailChange(string $newEmail, string $password, int $userId): array
  {
    $errors = [];

    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
      $errors[] = 'Błędny format nowego adresu e-mail.';
    }

    if ($this->userModel->checkEmail($newEmail)) {
      $errors[] = 'Podany adres e-mail jest już zarezerwowany dla innego użytkownika.';
    }

    if (!$this->userModel->checkPassword($userId, $password)) {
      $errors[] = 'Podane hasło autoryzacyjne jest nieprawidłowe.';
    }

    return $errors;
  }

  /**
   * Sprawdza, czy hasło widnieje w bazie znanych wycieków "Have I Been Pwned".
   * 
   * Wykorzystuje metodę "range search" — do API przesyłanych jest tylko 
   * pierwszych 5 znaków skrótu SHA-1 (K-anonymity), zapewniając pełną prywatność.
   *
   * @param string $password
   * @return bool True jeśli hasło jest skompromitowane.
   */
  private function isPasswordPwned(string $password): bool
  {
    $sha1 = strtoupper(sha1($password));
    $prefix = substr($sha1, 0, 5);
    $suffix = substr($sha1, 5);

    // Zapytanie do bazy HIBP (Range API)
    $ch = curl_init("https://api.pwnedpasswords.com/range/$prefix");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    return $response && str_contains($response, $suffix);
  }
}
