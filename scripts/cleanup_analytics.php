<?php

/**
 * Jednorazowe odchudzenie tabeli analytics_events.
 *
 * Usuwa wiersze, których analityka i tak już nie zapisuje ani nie pokazuje:
 * - ruch botów (user-agent pasujący do AnalyticsService::BOT_USER_AGENT_PATTERN),
 * - zdarzenia techniczne: validation_error, entry, question_answered.
 * Na koniec wykonuje OPTIMIZE TABLE, żeby InnoDB faktycznie oddał miejsce.
 *
 * Domyślnie działa w trybie podglądu (tylko liczy). Usuwanie: --apply.
 * Przed uruchomieniem z --apply zrób backup bazy (deploy robi go automatycznie).
 *
 * Sposób uruchomienia:
 *   php scripts/cleanup_analytics.php           # podgląd
 *   php scripts/cleanup_analytics.php --apply   # usunięcie + OPTIMIZE TABLE
 */

if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

Dotenv\Dotenv::createImmutable(__DIR__ . '/../')->load();
require_once __DIR__ . '/../config.php';

use App\Core\Database;
use App\Services\AnalyticsService;

$apply = in_array('--apply', $argv, true);
$db = Database::getInstance();

$condition = "(event_type IN ('validation_error', 'entry', 'question_answered')
               OR (user_agent IS NOT NULL AND LOWER(user_agent) REGEXP ?))";
$params = [AnalyticsService::BOT_USER_AGENT_PATTERN];

$tableSize = static function () use ($db): string {
  $row = $db->fetch(
    "SELECT ROUND((data_length + index_length) / 1024 / 1024, 1) AS mb
     FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name = 'analytics_events'"
  );
  return ($row['mb'] ?? '?') . ' MB';
};

$total = (int) ($db->fetch('SELECT COUNT(*) AS n FROM analytics_events')['n'] ?? 0);
$toDelete = (int) ($db->fetch("SELECT COUNT(*) AS n FROM analytics_events WHERE $condition", $params)['n'] ?? 0);

echo "Wiersze w analytics_events: $total\n";
echo "Do usunięcia (boty + zdarzenia techniczne): $toDelete\n";
echo "Rozmiar tabeli: " . $tableSize() . "\n";

if (!$apply) {
  echo "\nTryb podglądu - nic nie usunięto. Uruchom z --apply, żeby wyczyścić.\n";
  exit(0);
}

// Usuwanie partiami, żeby nie blokować tabeli na długo przy działającej stronie.
$deleted = 0;
do {
  $db->execute("DELETE FROM analytics_events WHERE $condition LIMIT 5000", $params);
  $batch = (int) ($db->fetch('SELECT ROW_COUNT() AS n')['n'] ?? 0);
  $deleted += max(0, $batch);
  echo "Usunięto: $deleted / $toDelete\r";
} while ($batch > 0);

echo "\nOPTIMIZE TABLE (odzyskanie miejsca)...\n";
$db->fetchAll('OPTIMIZE TABLE analytics_events');

echo "Gotowe. Rozmiar tabeli po czyszczeniu: " . $tableSize() . "\n";
