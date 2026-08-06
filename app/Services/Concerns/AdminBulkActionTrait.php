<?php

namespace App\Services\Concerns;

use Throwable;

/**
 * Wykonywanie akcji zbiorczych na wielu rekordach z raportem częściowego sukcesu.
 *
 * Każdy rekord przetwarzany jest we własnej transakcji — pojedynczy błąd
 * nie wycofuje pozostałych operacji z partii, a wynik jasno rozdziela
 * rekordy przetworzone od pominiętych (z powodem).
 *
 * @package App\Services\Concerns
 */
trait AdminBulkActionTrait
{
  /**
   * @param array<int, int|string> $ids
   * @param callable(int):?string $singleAction zwraca null przy sukcesie
   *   lub tekstowy powód pominięcia (transakcja jest wtedy wycofywana);
   *   twarde błędy sygnalizuje wyjątkiem
   * @return array{succeeded: array<int, int>, failed: array<int|string, string>}
   */
  protected function applyBulkAction(array $ids, callable $singleAction): array
  {
    $succeeded = [];
    $failed = [];

    foreach ($ids as $rawId) {
      $id = (int) $rawId;

      if ($id <= 0) {
        $failed[(string) $rawId] = 'Nieprawidłowy identyfikator.';
        continue;
      }

      $this->db->beginTransaction();

      try {
        $reason = $singleAction($id);

        if ($reason === null) {
          $this->db->commit();
          $succeeded[] = $id;
        } else {
          $this->db->rollBack();
          $failed[$id] = $reason;
        }
      } catch (Throwable $e) {
        $this->db->rollBack();
        error_log('[AdminBulkAction] id=' . $id . ': ' . $e->getMessage());
        $failed[$id] = 'Błąd wykonania operacji.';
      }
    }

    return ['succeeded' => $succeeded, 'failed' => $failed];
  }
}
