<?php

/**
 * Plik konfiguracyjny aplikacji.
 * Definiuje globalne stałe używane w całej aplikacji.
 */

// Wczytanie globalnych funkcji pomocniczych.
require_once __DIR__ . '/app/Core/helpers.php';

// 1. Konfiguracja URL i środowiska
// Preferujemy zmienne środowiskowe z pliku .env (dla Produkcji i Dockera)
if (isset($_ENV['BASE_URL'])) {
  define('BASE_URL', $_ENV['BASE_URL']);
} else {
  // Fallback dla środowiska lokalnego bez .env (np. XAMPP bez konfiguracji)
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  define('BASE_URL', 'http://' . $host);
}

// Stała DB_HOST jest używana gdzieniegdzie, ale Database.php korzysta bezpośrednio z $_ENV.
// Dla spójności definiujemy ją, jeśli jest w zmiennych środowiskowych.
if (isset($_ENV['DB_HOST'])) {
  define('DB_HOST', $_ENV['DB_HOST']);
} else {
  define('DB_HOST', 'localhost');
}

// 2. Konfiguracja poczty i kontaktu
// Systemowe wiadomości wychodzą z noreply, a formularz kontaktowy trafia na osobną skrzynkę.
define('SYSTEM_MAIL_FROM_ADDRESS', $_ENV['MAIL_FROM_ADDRESS'] ?? $_ENV['SMTP_USER'] ?? 'noreply@localhost');
define('MAIL_FROM_ADDRESS', SYSTEM_MAIL_FROM_ADDRESS);
define('MAIL_FROM_NAME', $_ENV['MAIL_FROM_NAME'] ?? 'Zdajnik - Inteligentna Nauka');
define('CONTACT_EMAIL', $_ENV['CONTACT_EMAIL'] ?? 'kontakt@localhost');

// Opcjonalnie: zdefiniuj ścieżkę na serwerze do głównego katalogu aplikacji.
// Przydatne do includowania plików w PHP.
define('BASE_PATH', __DIR__);
