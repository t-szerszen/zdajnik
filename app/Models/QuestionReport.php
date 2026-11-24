<?php

namespace App\Models;

use App\Core\Database;

/**
 * Model Zgłoszeń Błędów w Pytaniach (QuestionReport).
 *
 * Przechowuje zgłoszenia wysyłane z kart pytań (tryb jednego pytania, testy,
 * przeglądy wyników, publiczna baza pytań) oraz obsługuje ich listę
 * i zmianę statusu w panelu CMS.
 *
 * @version 1.0.0
 * @author Tobiasz Szerszeń
 * @package App\Models
 */
class QuestionReport
{
  /**
   * Dozwolone kategorie zgłoszeń wraz z etykietami.
   * Klucze muszą odpowiadać ENUM-owi w tabeli oraz liście w QuestionReportDialog.js.
   */
  public const CATEGORIES = [
    'wrong_answer' => 'Zła poprawna odpowiedź',
    'content_error' => 'Błąd w treści pytania lub odpowiedzi',
    'explanation' => 'Błędne lub niejasne wyjaśnienie',
    'display' => 'Problem z obrazkiem lub formatowaniem',
    'other' => 'Inny problem',
  ];

  /** Dozwolone statusy wraz z etykietami. */
  public const STATUSES = [
    'new' => 'Nowe',
    'resolved' => 'Poprawione',
    'rejected' => 'Odrzucone',
  ];

  /** Dozwolone konteksty (miejsce w aplikacji, z którego wysłano zgłoszenie). */
  public const CONTEXTS = [
    'one_question' => 'Jedno pytanie',
    'test' => 'Test w trakcie',
    'test_results' => 'Wyniki testu',
    'review' => 'Przegląd',
    'public' => 'Baza pytań',
  ];

  public const MESSAGE_MAX_LENGTH = 1000;

  private Database $db;

  public function __construct()
  {
    $this->db = Database::getInstance();
  }

  /**
   * Zapisuje nowe zgłoszenie.
   *
   * @return int|null ID utworzonego zgłoszenia lub null przy błędzie.
   */
  public function create(
    int $questionId,
    ?int $questionVersionId,
    ?int $userId,
    string $category,
    ?string $message,
    ?string $context
  ): ?int {
    $sql = "INSERT INTO question_reports (question_id, question_version_id, user_id, category, message, context)
            VALUES (?, ?, ?, ?, ?, ?)";

    if (!$this->db->execute($sql, [$questionId, $questionVersionId, $userId, $category, $message, $context])) {
      return null;
    }

    return $this->db->lastInsertId();
  }

  /**
   * Czy użytkownik ma już otwarte (nierozpatrzone) zgłoszenie do tego pytania.
   */
  public function hasOpenReport(int $userId, int $questionId): bool
  {
    $sql = "SELECT 1 FROM question_reports WHERE user_id = ? AND question_id = ? AND status = 'new' LIMIT 1";
    return $this->db->fetch($sql, [$userId, $questionId]) !== false;
  }

  /**
   * Liczba zgłoszeń oczekujących na rozpatrzenie (licznik w nawigacji panelu).
   */
  public function countNew(): int
  {
    $result = $this->db->fetch("SELECT COUNT(*) AS total FROM question_reports WHERE status = 'new'");
    return (int) ($result['total'] ?? 0);
  }

  /**
   * Lista zgłoszeń z filtrami i paginacją (panel CMS).
   *
   * @param array{status?:string, category?:string, question_id?:string} $filters
   * @return array{items: array, total: int, page: int, perPage: int, totalPages: int}
   */
  public function search(array $filters, int $page, int $perPage): array
  {
    $where = [];
    $params = [];

    if (isset(self::STATUSES[$filters['status'] ?? ''])) {
      $where[] = 'r.status = ?';
      $params[] = $filters['status'];
    }
    if (isset(self::CATEGORIES[$filters['category'] ?? ''])) {
      $where[] = 'r.category = ?';
      $params[] = $filters['category'];
    }
    if (ctype_digit((string) ($filters['question_id'] ?? ''))) {
      $where[] = 'r.question_id = ?';
      $params[] = (int) $filters['question_id'];
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $total = (int) ($this->db->fetch("SELECT COUNT(*) AS total FROM question_reports r $whereSql", $params)['total'] ?? 0);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, $page), $totalPages);
    $offset = ($page - 1) * $perPage;

    // Treść pokazujemy z wersji, którą widział zgłaszający (a gdy ta zniknęła - z aktywnej).
    $sql = "SELECT
              r.*,
              et.code AS exam_code,
              COALESCE(rv.question_text, av.question_text) AS question_text,
              rv.version_num AS reported_version_num,
              av.version_num AS current_version_num,
              u.first_name, u.last_name, u.email,
              rb.first_name AS resolver_first_name, rb.last_name AS resolver_last_name,
              (SELECT COUNT(*) FROM question_reports r2 WHERE r2.question_id = r.question_id AND r2.status = 'new') AS open_for_question
            FROM question_reports r
            JOIN questions q ON q.id = r.question_id
            JOIN exam_types et ON et.id = q.exam_type_id
            LEFT JOIN question_versions rv ON rv.id = r.question_version_id
            LEFT JOIN question_versions av ON av.question_id = r.question_id AND av.is_active = 1 AND av.deleted_at IS NULL
            LEFT JOIN users u ON u.id = r.user_id
            LEFT JOIN users rb ON rb.id = r.resolved_by
            $whereSql
            ORDER BY r.created_at DESC, r.id DESC
            LIMIT $perPage OFFSET $offset";

    return [
      'items' => $this->db->fetchAll($sql, $params),
      'total' => $total,
      'page' => $page,
      'perPage' => $perPage,
      'totalPages' => $totalPages,
    ];
  }

  /**
   * Pobiera pojedyncze zgłoszenie.
   */
  public function findById(int $reportId): array|false
  {
    return $this->db->fetch('SELECT * FROM question_reports WHERE id = ? LIMIT 1', [$reportId]);
  }

  /**
   * Zmienia status zgłoszenia. Powrót do "new" czyści dane rozpatrzenia.
   */
  public function updateStatus(int $reportId, string $status, int $adminUserId): bool
  {
    if (!isset(self::STATUSES[$status])) {
      return false;
    }

    if ($status === 'new') {
      $sql = "UPDATE question_reports SET status = 'new', resolved_by = NULL, resolved_at = NULL WHERE id = ?";
      return $this->db->execute($sql, [$reportId]);
    }

    $sql = 'UPDATE question_reports SET status = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?';
    return $this->db->execute($sql, [$status, $adminUserId, $reportId]);
  }
}
