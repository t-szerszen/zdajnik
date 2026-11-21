<?php

/**
 * Front controller — punkt wejściowy aplikacji.
 */

// Wczytanie autoloadera z composera.
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Wczytanie pliku konfiguracyjnego.
// config.php teraz będzie widział zmienne ze środowiska (.env),
// bo ładujemy go PO załadowaniu Dotenv.
require_once __DIR__ . '/../config.php';

// helpers.php jest ładowane w config.php

use App\Core\Router;

// Odpalenie sesji.
if (session_status() === PHP_SESSION_NONE) {
  // Flagi ciasteczka sesji:
  // - httponly: JS nie odczyta identyfikatora sesji (ochrona przed kradzieżą przez XSS),
  // - samesite=Lax: ciasteczko nie leci przy żądaniach POST z obcych stron (CSRF),
  //   a nadal działa powrót z Google OAuth (nawigacja GET najwyższego poziomu),
  // - secure: tylko po HTTPS (lokalnie po HTTP flaga byłaby zablokowała sesję).
  $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  ini_set('session.use_strict_mode', '1');

  session_start();
}

// Utworzenie obiektu Router'a.
(new Router())->handleRequest();