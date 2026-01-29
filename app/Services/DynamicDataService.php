<?php 
namespace App\Services;
use App\Core\Database;

class DynamicDataService 
{
  private Database $db;

  public function __construct()
  {
    $this->db = Database::getInstance();
  }

  public function getExamIdByCode($code): int|null
  {
    $sql = "SELECT id FROM exam_types WHERE code = ? LIMIT 1";
    $result = $this->db->fetch($sql, [$code]);
    return $result ? (int) $result['id'] : null;
  }

  /**
   * Pobiera szczegóły egzaminu na podstawie kodu.
   * Łączy dane z bazy z predefiniowanymi opisami dla lepszego UX.
   */
  public function getExamDetails(string $code): ?array
  {
    $examId = $this->getExamIdByCode($code);
    if (!$examId) return null;

    // Pobierz podstawowe dane z bazy (zakładając, że tabela exam_types może mieć kolumnę name, ale jeśli nie - fallback)
    $sql = "SELECT * FROM exam_types WHERE id = ?";
    $dbExam = $this->db->fetch($sql, [$examId]);

    // Predefiniowane metadane dla znanych kodów (dla bogatych opisów)
    // W przyszłości te dane powinny trafić do bazy danych.
    $metadata = [
      'INF.03' => [
        'formatted_name' => 'EE.09 / INF.03',
        'title_one_question' => 'INF.03 - Jedno pytanie - Inteligentna Powtórka Online | Examly',
        'desc_one_question' => 'Nie masz czasu na cały test? Wylosuj jedno pytanie z bazy egzaminacyjnej INF.03 i sprawdź swoją wiedzę w mniej niż minutę.',
        'title_test' => 'INF.03 - Egzamin próbny - Test Online 40 Pytań | Examly',
        'desc_test' => 'Sprawdź swoją wiedzę przed egzaminem zawodowym INF.03. Rozwiąż pełny test próbny online.',
        'title_personalized' => 'INF.03 - Stwórz swój własny test - Spersonalizowana Nauka | Examly',
        'desc_personalized' => 'Dopasuj naukę do swoich potrzeb. Stwórz spersonalizowany test z kwalifikacji INF.03.'
      ],
      'INF.02' => [
        'formatted_name' => 'EE.08 / INF.02',
        'title_one_question' => 'INF.02 - Jedno pytanie - Szybka powtórka | Examly',
        'desc_one_question' => 'Szybka powtórka z INF.02. Jedno pytanie losowe.',
        'title_test' => 'INF.02 - Egzamin próbny - Test Online | Examly',
        'desc_test' => 'Pełny egzamin próbny INF.02.',
        'title_personalized' => 'INF.02 - Test Spersonalizowany | Examly',
        'desc_personalized' => 'Skonfiguruj swój test z INF.02.'
      ],
      'INF.04' => [
        'formatted_name' => 'E.14 / INF.04',
        'title_one_question' => 'INF.04 - Jedno pytanie - Trening | Examly',
        'desc_one_question' => 'Poćwicz z INF.04. Jedno pytanie losowe.',
        'title_test' => 'INF.04 - Egzamin próbny - Test Online | Examly',
        'desc_test' => 'Pełny egzamin próbny INF.04.',
        'title_personalized' => 'INF.04 - Test Spersonalizowany | Examly',
        'desc_personalized' => 'Skonfiguruj swój test z INF.04.'
      ]
    ];

    $meta = $metadata[$code] ?? [
      'formatted_name' => $code,
      'title_one_question' => "$code - Jedno pytanie | Examly",
      'desc_one_question' => "Losowe pytanie z kwalifikacji $code.",
      'title_test' => "$code - Egzamin Próbny | Examly",
      'desc_test' => "Pełny test próbny z $code.",
      'title_personalized' => "$code - Test Spersonalizowany | Examly",
      'desc_personalized' => "Stwórz własny test z $code."
    ];

    // Scal dane z bazy z metadanymi
    return array_merge($dbExam, $meta);
  }

  public function getCategoriesForExam(int $examId): array
  {
    $sql = "SELECT name, id FROM topics WHERE exam_type_id = ? ORDER BY id ASC";
    return $this->db->fetchAll($sql, [$examId]);
  }
}