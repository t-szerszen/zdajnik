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
  session_start();
}

// Utworzenie obiektu Router'a.
(new Router())->handleRequest();