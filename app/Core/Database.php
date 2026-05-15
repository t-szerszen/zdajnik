<?php

namespace App\Core;

use PDO;
use PDOException;

/**
 * Klasa Bazy Danych (Database Wrapper).
 *
 * Implementuje wzorzec projektowy Singleton, aby zagwarantować istnienie
 * tylko jednej, globalnie dostępnej instancji połączenia z bazą danych (PDO)
 * w całym cyklu życia aplikacji. Klasa zapewnia:
 * - Bezpieczne połączenie z kodowaniem utf8mb4.
 * - Centralną obsługę wyjątków i błędów SQL.
 * - Zestaw metod pomocniczych (fetch, execute) skracających kod SQL.
 * - Obsługę transakcji (begin, commit, rollback).
 *
 * @version 1.3.0
 * @author Tobiasz Szerszeń
 * @package App\Core
 */
class Database
{
  /** @var self|null Przechowuje jedyną instancję klasy (Wzorzec Singleton) */
  private static ?self $instance = null;

  /** @var PDO Przechowuje aktywny obiekt połączenia z bazą danych */
  private PDO $pdo;

  /**
   * Prywatny konstruktor inicjalizujący połączenie PDO.
   *
   * Metoda wykonuje następujące kroki:
   * 1. Pobranie poświadczeń z parametrów środowiskowych ($_ENV).
   * 2. Ustanowienie połączenia DSN z wymuszeniem kodowania UTF-8.
   * 3. Konfiguracja atrybutów PDO (tryb wyjątków, domyślny format tablicy).
   */
  private function __construct()
  {
    // KROK 1: Konfiguracja połączenia
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $dbname = $_ENV['DB_NAME'] ?? '';
    $username = $_ENV['DB_USER'] ?? '';
    $password = $_ENV['DB_PASS'] ?? '';

    try {
      // KROK 2: Inicjalizacja sterownika PDO
      $this->pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);

      // KROK 3: Konfiguracja bezpieczeństwa i wygody
      // ERRMODE_EXCEPTION rzuca wyjątkami przy błędach SQL (bezpieczniejsze niż tryb cichy)
      $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

      // FETCH_ASSOC zwraca wyniki jako tablice asocjacyjne (klucz => wartość)
      $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log('[DB Error] Błąd połączenia: ' . $e->getMessage());
      die('Wystąpił krytyczny błąd połączenia z bazą danych. Prosimy spróbować później.');
    }
  }

  /**
   * Publiczny punkt dostępu do instancji klasy (Singleton).
   *
   * @return self Jedyna dostępna instancja obiektu Database.
   */
  public static function getInstance(): self
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Wykonuje zapytanie i zwraca wszystkie pasujące rekordy.
   *
   * Wykorzystuje Prepared Statements (bezpieczeństwo przed SQL Injection).
   *
   * @param string $sql Zapytanie SQL z placeholderami (np. WHERE id = ?).
   * @param array<mixed> $params Parametry do podstawienia w zapytaniu.
   * @return array Tablica rekordów (lub pusta w razie braku wyników).
   */
  public function fetchAll(string $sql, array $params = []): array
  {
    try {
      $stmt = $this->pdo->prepare($sql);
      $stmt->execute($params);
      return $stmt->fetchAll();
    } catch (PDOException $e) {
      error_log('[DB Error] fetchAll: ' . $e->getMessage() . " | SQL: $sql");
      return [];
    }
  }

  /**
   * Wykonuje zapytanie i zwraca jednowymiarową listę wartości z pierwszej kolumny.
   *
   * @param string $sql Zapytanie SQL.
   * @param array<mixed> $params Parametry do bindowania.
   * @return array Lista wartości (np. [1, 2, 5, 10]).
   */
  public function fetchColumn(string $sql, array $params = []): array
  {
    try {
      $stmt = $this->pdo->prepare($sql);
      $stmt->execute($params);
      return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
      error_log('[DB Error] fetchColumn: ' . $e->getMessage() . " | SQL: $sql");
      return [];
    }
  }

  /**
   * Pobiera pojedynczy wiersz z bazy danych.
   *
   * @param string $sql Zapytanie SQL.
   * @param array<mixed> $params Parametry do bindowania.
   * @return array|false Tablica asocjacyjna wiersza lub false jeśli nie znaleziono rekordu.
   */
  public function fetch(string $sql, array $params = []): array|false
  {
    try {
      $stmt = $this->pdo->prepare($sql);
      $stmt->execute($params);
      return $stmt->fetch();
    } catch (PDOException $e) {
      error_log('[DB Error] fetch: ' . $e->getMessage() . " | SQL: $sql");
      return false;
    }
  }

  /**
   * Wykonuje zapytanie modyfikujące stan bazy (INSERT, UPDATE, DELETE).
   *
   * @param string $sql Zapytanie SQL.
   * @param array<mixed> $params Parametry do bindowania.
   * @return bool True jeśli operacja zakończona sukcesem.
   */
  public function execute(string $sql, array $params = []): bool
  {
    try {
      $stmt = $this->pdo->prepare($sql);
      return $stmt->execute($params);
    } catch (PDOException $e) {
      error_log('[DB Error] execute: ' . $e->getMessage() . " | SQL: $sql");
      return false;
    }
  }

  /**
   * Zwraca identyfikator ostatnio wstawionego wiersza.
   *
   * @return int|null ID rekordu lub null w przypadku błędu.
   */
  public function lastInsertId(): ?int
  {
    try {
      $id = $this->pdo->lastInsertId();
      return $id ? (int) $id : null;
    } catch (PDOException $e) {
      error_log('[DB Error] lastInsertId: ' . $e->getMessage());
      return null;
    }
  }

  /**
   * Rozpoczyna atomową transakcję bazodanową.
   * 
   * @return bool
   */
  public function beginTransaction(): bool
  {
    try {
      return $this->pdo->beginTransaction();
    } catch (PDOException $e) {
      error_log('[DB Error] beginTransaction: ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Zatwierdza zmiany wprowadzone w trakcie transakcji.
   * 
   * @return bool
   */
  public function commit(): bool
  {
    try {
      return $this->pdo->commit();
    } catch (PDOException $e) {
      error_log('[DB Error] commit: ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Wycofuje wszelkie zmiany wprowadzone w trakcie obecnej transakcji.
   * 
   * @return bool
   */
  public function rollBack(): bool
  {
    try {
      return $this->pdo->rollBack();
    } catch (PDOException $e) {
      error_log('[DB Error] rollBack: ' . $e->getMessage());
      return false;
    }
  }
}
