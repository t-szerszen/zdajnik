<?php

namespace App\Services;

/**
 * Serwis ochrony CSRF.
 *
 * Przechowuje pojedynczy token sesyjny i weryfikuje go w akcjach POST.
 *
 * @version 1.0.0
 * @package App\Services
 */
class CsrfService
{
  private const SESSION_KEY = '_csrf_token';

  /**
   * Zwraca token CSRF dla bieżącej sesji.
   */
  public function token(): string
  {
    if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
      $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
    }

    return $_SESSION[self::SESSION_KEY];
  }

  /**
   * Weryfikuje token CSRF.
   */
  public function validate(?string $token): bool
  {
    if (empty($token) || !isset($_SESSION[self::SESSION_KEY])) {
      return false;
    }

    return hash_equals((string) $_SESSION[self::SESSION_KEY], (string) $token);
  }
}
