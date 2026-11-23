<?php

/**
 * Anonimizacja kont usuniętych dawniej niż okres karencji (UserModel::DELETION_GRACE_DAYS).
 *
 * Użytkownik, który usunie konto, ma 30 dni na jego przywrócenie przez ponowne
 * zalogowanie. Po tym czasie dane osobowe muszą zniknąć niezależnie od tego, czy
 * ktokolwiek inny akurat usuwa konto - dlatego skrypt jest uruchamiany codziennie
 * przez cron (rejestrowany automatycznie przez deploy.php).
 *
 * Sposób uruchomienia: php scripts/anonymize_deleted_users.php
 */

if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

Dotenv\Dotenv::createImmutable(__DIR__ . '/../')->load();
require_once __DIR__ . '/../config.php';

$count = (new App\Models\UserModel())->anonymizeExpiredDeletedUsers();

echo '[' . date('Y-m-d H:i:s') . '] Zanonimizowano kont: ' . $count . PHP_EOL;
