<?php

namespace App\Services;

use App\Core\Database;
use App\Services\Concerns\AdminBulkActionTrait;
use RuntimeException;

/**
 * Serwis administracyjny Biblioteki Arkuszy.
 *
 * Obsługuje zarządzanie tematami arkuszy (sheet_topics) oraz arkuszami (sheets)
 * w panelu CMS: wyszukiwanie z paginacją, tworzenie, edycję, zmianę statusu
 * oraz bezpieczny upload plików PDF/ZIP (walidacja MIME przez finfo, limity
 * rozmiaru, losowe nazwy plików generowane po stronie serwera).
 *
 * @version 1.0.0
 * @author Tobiasz Szerszeń
 * @package App\Services
 */
class AdminSheetService
{
  use AdminBulkActionTrait;

  /** Maksymalny rozmiar pliku PDF w bajtach (15 MB). */
  private const MAX_PDF_BYTES = 15 * 1024 * 1024;

  /** Maksymalny rozmiar pliku ZIP w bajtach (50 MB). */
  private const MAX_ZIP_BYTES = 50 * 1024 * 1024;

  /** Maksymalny rozmiar miniaturki w bajtach (5 MB). */
  private const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

  /** @var Database Instancja połączenia z bazą danych */
  private Database $db;

  public function __construct()
  {
    $this->db = Database::getInstance();
  }

  // ---------------------------------------------------------------------------
  // KWALIFIKACJE I TEMATY ARKUSZY
  // ---------------------------------------------------------------------------

  /**
   * Pobiera aktywne kwalifikacje (do selectów w formularzach panelu).
   *
   * @return array<int, array<string, mixed>>
   */
  public function getExamTypes(): array
  {
    $sql = "SELECT id, code, name
            FROM exam_types
            WHERE deleted_at IS NULL
            ORDER BY code ASC";
    return $this->db->fetchAll($sql);
  }

  /**
   * Sprawdza, czy kwalifikacja o podanym ID istnieje i nie jest usunięta.
   */
  public function examTypeExists(int $examTypeId): bool
  {
    $sql = "SELECT id FROM exam_types WHERE id = ? AND deleted_at IS NULL LIMIT 1";
    return $this->db->fetch($sql, [$examTypeId]) !== false;
  }

  /**
   * Pobiera wszystkie nieusunięte tematy arkuszy (również nieaktywne),
   * wraz z kodem kwalifikacji, do której należą.
   *
   * @return array<int, array<string, mixed>>
   */
  public function getTopics(): array
  {
    $sql = "SELECT t.id, t.exam_type_id, t.name, t.description, t.display_order, t.is_active,
                   e.code AS exam_code,
                   (SELECT COUNT(*) FROM sheets s WHERE s.sheet_topic_id = t.id AND s.deleted_at IS NULL) AS sheets_count
            FROM sheet_topics t
            JOIN exam_types e ON e.id = t.exam_type_id
            WHERE t.deleted_at IS NULL
            ORDER BY e.code ASC, t.display_order ASC, t.name ASC";
    return $this->db->fetchAll($sql);
  }

  /**
   * Sprawdza, czy temat o podanym ID istnieje i nie jest usunięty.
   */
  public function topicExists(int $topicId): bool
  {
    $sql = "SELECT id FROM sheet_topics WHERE id = ? AND deleted_at IS NULL LIMIT 1";
    return $this->db->fetch($sql, [$topicId]) !== false;
  }

  /**
   * Sprawdza dostępność nazwy tematu w obrębie kwalifikacji.
   */
  public function isTopicNameAvailable(string $name, int $examTypeId, ?int $excludeId = null): bool
  {
    $sql = "SELECT id FROM sheet_topics WHERE name = ? AND exam_type_id = ? AND deleted_at IS NULL";
    $params = [$name, $examTypeId];
    if ($excludeId !== null) {
      $sql .= " AND id <> ?";
      $params[] = $excludeId;
    }
    return $this->db->fetch($sql . " LIMIT 1", $params) === false;
  }

  /**
   * Tworzy nowy temat arkuszy w obrębie kwalifikacji.
   *
   * @return int|false ID nowego tematu lub false przy błędzie.
   */
  public function createTopic(int $examTypeId, string $name, string $description, int $displayOrder): int|false
  {
    $sql = "INSERT INTO sheet_topics (exam_type_id, name, description, display_order) VALUES (?, ?, ?, ?)";
    if (!$this->db->execute($sql, [$examTypeId, $name, $description, $displayOrder])) {
      return false;
    }
    return (int) $this->db->lastInsertId();
  }

  /**
   * Aktualizuje temat arkuszy.
   */
  public function updateTopic(int $topicId, int $examTypeId, string $name, string $description, int $displayOrder, bool $isActive): bool
  {
    $sql = "UPDATE sheet_topics
            SET exam_type_id = ?, name = ?, description = ?, display_order = ?, is_active = ?
            WHERE id = ? AND deleted_at IS NULL";
    return $this->db->execute($sql, [$examTypeId, $name, $description, $displayOrder, (int) $isActive, $topicId]);
  }

  // ---------------------------------------------------------------------------
  // ARKUSZE
  // ---------------------------------------------------------------------------

  /**
   * Wyszukuje arkusze z filtrami i paginacją (na potrzeby listy w CMS).
   *
   * @param array<string, mixed> $filters Klucze: query, topic_id, status.
   * @return array{items: array<int, array<string, mixed>>, total: int, page: int, perPage: int, totalPages: int}
   */
  public function searchSheets(array $filters, int $page, int $perPage): array
  {
    $page = max(1, $page);
    $where = " WHERE s.deleted_at IS NULL";
    $params = [];

    if (!empty($filters['query'])) {
      $query = '%' . $filters['query'] . '%';
      $where .= " AND (s.title LIKE ? OR s.description LIKE ? OR CAST(s.id AS CHAR) = ?)";
      $params[] = $query;
      $params[] = $query;
      $params[] = (string) $filters['query'];
    }

    if (!empty($filters['topic_id']) && ctype_digit((string) $filters['topic_id'])) {
      $where .= " AND s.sheet_topic_id = ?";
      $params[] = (int) $filters['topic_id'];
    }

    if (!empty($filters['exam_id']) && ctype_digit((string) $filters['exam_id'])) {
      $where .= " AND t.exam_type_id = ?";
      $params[] = (int) $filters['exam_id'];
    }

    if (($filters['status'] ?? '') === 'active') {
      $where .= " AND s.is_active = 1";
    } elseif (($filters['status'] ?? '') === 'inactive') {
      $where .= " AND s.is_active = 0";
    }

    $countRow = $this->db->fetch(
      "SELECT COUNT(*) AS total
       FROM sheets s
       JOIN sheet_topics t ON t.id = s.sheet_topic_id" . $where,
      $params
    );
    $total = (int) ($countRow['total'] ?? 0);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $sql = "SELECT s.id, s.title, s.description, s.difficulty, s.pdf_path, s.zip_path,
                   s.thumbnail_path, s.resources_path, s.is_active, s.created_at, s.updated_at,
                   t.name AS topic_name,
                   e.code AS exam_code
            FROM sheets s
            JOIN sheet_topics t ON t.id = s.sheet_topic_id
            JOIN exam_types e ON e.id = t.exam_type_id
            $where
            ORDER BY s.id DESC
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
   * Pobiera arkusz po ID (bez względu na status aktywności).
   *
   * @return array<string, mixed>|false
   */
  public function getSheetById(int $sheetId): array|false
  {
    $sql = "SELECT s.*, t.name AS topic_name, t.exam_type_id,
                   e.code AS exam_code
            FROM sheets s
            JOIN sheet_topics t ON t.id = s.sheet_topic_id
            JOIN exam_types e ON e.id = t.exam_type_id
            WHERE s.id = ? AND s.deleted_at IS NULL
            LIMIT 1";
    return $this->db->fetch($sql, [$sheetId]);
  }

  /**
   * Tworzy nowy arkusz.
   *
   * @param array{sheet_topic_id: int, title: string, description: string, difficulty: int, pdf_path: string, zip_path: ?string, is_active: bool} $data
   * @return int|false ID nowego arkusza lub false przy błędzie.
   */
  public function createSheet(array $data): int|false
  {
    $sql = "INSERT INTO sheets (sheet_topic_id, title, description, difficulty, pdf_path, zip_path, thumbnail_path, resources_path, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $success = $this->db->execute($sql, [
      $data['sheet_topic_id'],
      $data['title'],
      $data['description'],
      $data['difficulty'],
      $data['pdf_path'],
      $data['zip_path'],
      $data['thumbnail_path'],
      $data['resources_path'],
      (int) $data['is_active'],
    ]);

    return $success ? (int) $this->db->lastInsertId() : false;
  }

  /**
   * Aktualizuje arkusz.
   *
   * @param array{sheet_topic_id: int, title: string, description: string, difficulty: int, pdf_path: string, zip_path: ?string, is_active: bool} $data
   */
  public function updateSheet(int $sheetId, array $data): bool
  {
    $sql = "UPDATE sheets
            SET sheet_topic_id = ?, title = ?, description = ?, difficulty = ?, pdf_path = ?, zip_path = ?, thumbnail_path = ?, resources_path = ?, is_active = ?
            WHERE id = ? AND deleted_at IS NULL";
    return $this->db->execute($sql, [
      $data['sheet_topic_id'],
      $data['title'],
      $data['description'],
      $data['difficulty'],
      $data['pdf_path'],
      $data['zip_path'],
      $data['thumbnail_path'],
      $data['resources_path'],
      (int) $data['is_active'],
      $sheetId,
    ]);
  }

  /**
   * Zmienia status aktywności arkusza.
   */
  public function setSheetActive(int $sheetId, bool $isActive): bool
  {
    $sql = "UPDATE sheets SET is_active = ? WHERE id = ? AND deleted_at IS NULL";
    return $this->db->execute($sql, [(int) $isActive, $sheetId]);
  }

  /**
   * Akcja zbiorcza zmiany statusu arkuszy — zmiana i audyt w jednej transakcji per rekord.
   *
   * @param array<int, int> $ids
   * @return array{succeeded: array<int, int>, failed: array<int|string, string>}
   */
  public function bulkSheetAction(array $ids, bool $isActive, int $adminUserId, AdminAuditService $auditService): array
  {
    return $this->applyBulkAction($ids, function (int $sheetId) use ($isActive, $adminUserId, $auditService): ?string {
      $sheet = $this->getSheetById($sheetId);
      if (!$sheet) {
        return 'Arkusz nie istnieje.';
      }

      if ((int) ($sheet['is_active'] ?? 0) === (int) $isActive) {
        return 'Status już ustawiony.';
      }

      if (!$this->setSheetActive($sheetId, $isActive)) {
        throw new RuntimeException('Nie udało się zmienić statusu arkusza.');
      }

      if (!$auditService->logAction(
        $adminUserId,
        $isActive ? 'sheet_activated' : 'sheet_deactivated',
        'sheet',
        $sheetId,
        ['is_active' => (int) $sheet['is_active']],
        ['is_active' => (int) $isActive]
      )) {
        throw new RuntimeException('Nie udało się zapisać wpisu audytowego.');
      }

      return null;
    });
  }

  // ---------------------------------------------------------------------------
  // PLIKI (PDF / ZIP)
  // ---------------------------------------------------------------------------

  /**
   * Obsługuje upload pliku arkusza (PDF, ZIP lub obrazek miniaturki).
   *
   * Nazwa docelowa jest generowana losowo po stronie serwera (brak wpływu
   * użytkownika na ścieżkę zapisu), a typ pliku weryfikowany przez finfo.
   *
   * @param array|null $file Wpis z $_FILES lub null.
   * @param string $kind 'pdf', 'zip', 'resources' (reguły ZIP) lub 'image'.
   * @return array{success: bool, path?: string|null, error?: string|null}
   */
  public function processFileUpload(?array $file, string $kind): array
  {
    if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
      return ['success' => true, 'path' => null];
    }

    if (!isset($file['tmp_name'], $file['name'], $file['size'], $file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
      return ['success' => false, 'error' => 'Wystąpił błąd podczas uploadu pliku.'];
    }

    $maxBytes = match ($kind) {
      'zip', 'resources' => self::MAX_ZIP_BYTES,
      'image' => self::MAX_IMAGE_BYTES,
      default => self::MAX_PDF_BYTES,
    };
    if ((int) $file['size'] > $maxBytes) {
      $maxMb = (int) ($maxBytes / (1024 * 1024));
      return ['success' => false, 'error' => "Plik nie może być większy niż {$maxMb} MB."];
    }

    $tmpName = (string) $file['tmp_name'];
    $mimeType = $this->detectMimeType($tmpName);

    $allowedMimeTypes = match ($kind) {
      'zip', 'resources' => ['application/zip' => 'zip', 'application/x-zip-compressed' => 'zip'],
      'image' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
      default => ['application/pdf' => 'pdf'],
    };

    if (!isset($allowedMimeTypes[$mimeType])) {
      $label = match ($kind) {
        'zip', 'resources' => 'ZIP',
        'image' => 'graficzne (JPG, PNG, WEBP)',
        default => 'PDF',
      };
      return ['success' => false, 'error' => "Dozwolone są wyłącznie pliki $label."];
    }

    $extension = $allowedMimeTypes[$mimeType];
    try {
      $suffix = bin2hex(random_bytes(8));
    } catch (\Throwable $e) {
      $suffix = str_replace('.', '', uniqid('', true));
    }
    $filename = sprintf('sheet_%s_%s.%s', $kind, $suffix, $extension);

    $directory = $this->getStorageDirectory();
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
      return ['success' => false, 'error' => 'Nie udało się przygotować katalogu na pliki.'];
    }

    if (!move_uploaded_file($tmpName, $directory . '/' . $filename)) {
      return ['success' => false, 'error' => 'Nie udało się zapisać pliku na serwerze.'];
    }

    return ['success' => true, 'path' => $filename];
  }

  /**
   * Usuwa plik arkusza z dysku (np. po podmianie na nowy).
   *
   * Akceptuje wyłącznie samą nazwę pliku — separatory katalogów są odrzucane,
   * co eliminuje możliwość path traversal.
   */
  public function deleteStoredFile(?string $filename): void
  {
    if (empty($filename) || $filename !== basename($filename)) {
      return;
    }

    $path = $this->getStorageDirectory() . '/' . $filename;
    if (is_file($path)) {
      unlink($path);
    }
  }

  /**
   * Zwraca absolutną ścieżkę katalogu przechowywania plików arkuszy.
   */
  private function getStorageDirectory(): string
  {
    return dirname(__DIR__, 2) . '/public/files/sheets';
  }

  /**
   * Rozpoznaje MIME pliku.
   */
  private function detectMimeType(string $path): string
  {
    if (!function_exists('finfo_open')) {
      return '';
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) {
      return '';
    }

    $mime = finfo_file($finfo, $path) ?: '';
    finfo_close($finfo);
    return (string) $mime;
  }
}
