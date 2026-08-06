<?php

namespace App\Services\Concerns;

/**
 * Wspólny szkielet list panelu admina: sortowanie z białą listą kolumn
 * oraz paginacja (LIMIT/OFFSET + COUNT) w ujednoliconym kształcie odpowiedzi.
 *
 * Filtry domenowe (WHERE) pozostają w poszczególnych serwisach —
 * trait unifikuje wyłącznie szkielet zapytań listujących.
 *
 * @package App\Services\Concerns
 */
trait AdminListQueryTrait
{
  /**
   * Waliduje kolumnę i kierunek sortowania względem białej listy.
   *
   * @param array<string, string> $allowedSort mapa: klucz z requestu => kolumna SQL
   * @return array{0:string,1:string} [kolumna SQL, 'ASC'|'DESC']
   */
  protected function resolveSort(array $allowedSort, string $sortBy, string $defaultColumn, string $sortDir): array
  {
    $column = $allowedSort[$sortBy] ?? $defaultColumn;
    $dir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

    return [$column, $dir];
  }

  /**
   * Wykonuje zapytanie listujące z paginacją oraz zapytanie zliczające.
   *
   * @param string $itemsSql pełne zapytanie (z ORDER BY), bez LIMIT/OFFSET
   * @param string $countSql zapytanie zwracające kolumnę `total`
   * @param array<int, mixed> $params parametry wspólne dla obu zapytań
   * @return array{items: array<int, array<string, mixed>>, total: int, page: int, perPage: int, totalPages: int}
   */
  protected function paginate(string $itemsSql, string $countSql, array $params, int $page, int $perPage): array
  {
    $page = max(1, $page);
    $perPage = max(1, $perPage);
    $offset = ($page - 1) * $perPage;

    $items = $this->db->fetchAll(
      $itemsSql . ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
      $params
    );

    $countResult = $this->db->fetch($countSql, $params);
    $total = (int) ($countResult['total'] ?? 0);

    return [
      'items' => $items,
      'total' => $total,
      'page' => $page,
      'perPage' => $perPage,
      'totalPages' => max(1, (int) ceil($total / $perPage)),
    ];
  }
}
