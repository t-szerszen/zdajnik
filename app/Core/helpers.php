<?php
/**
 * Globalne funkcje pomocnicze.
 */

if (!function_exists('url')) {
  /**
   * Generuje pełny, absolutny adres URL na podstawie podanej ścieżki.]
   * 
   * @param string $path Ścieżka wewnątrz aplikacji (np. 'rejestracja' lub 'css/main.css').
   * @return string Pełny adres URL.
   */
  function url(string $path = ''): string
  {
    // Usuwa ukośniki z końca BASE_URL i początku $path, aby uniknąć duplikatów
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
  }
}

if (!function_exists('slugify')) {
  /**
   * Zamienia dowolny tekst na bezpieczny slug (np. do nazw pobieranych plików).
   *
   * Transliteruje polskie znaki, usuwa znaki specjalne i skleja słowa
   * myślnikami, np. "Arkusz INF.03 — czerwiec 2025" => "arkusz-inf-03-czerwiec-2025".
   *
   * @param string $text Tekst wejściowy.
   * @return string Slug (małe litery, cyfry i myślniki).
   */
  function slugify(string $text): string
  {
    $polish = ['ą', 'ć', 'ę', 'ł', 'ń', 'ó', 'ś', 'ź', 'ż', 'Ą', 'Ć', 'Ę', 'Ł', 'Ń', 'Ó', 'Ś', 'Ź', 'Ż'];
    $latin = ['a', 'c', 'e', 'l', 'n', 'o', 's', 'z', 'z', 'a', 'c', 'e', 'l', 'n', 'o', 's', 'z', 'z'];
    $text = str_replace($polish, $latin, $text);
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: 'plik';
  }
}

if (!function_exists('question_difficulty_label')) {
  /**
   * Zwraca etykietę PL dla poziomu trudności pytania (questions.difficulty).
   *
   * @param string|null $difficulty Wartość enum('easy','medium','hard').
   * @return string Etykieta do wyświetlenia lub pusty string dla nieznanej wartości.
   */
  function question_difficulty_label(?string $difficulty): string
  {
    return match ($difficulty) {
      'easy' => 'Łatwe',
      'medium' => 'Średnie',
      'hard' => 'Trudne',
      default => '',
    };
  }
}

if (!function_exists('question_difficulty_badge_class')) {
  /**
   * Zwraca modyfikator klasy .badge dopasowany do poziomu trudności pytania.
   *
   * @param string|null $difficulty Wartość enum('easy','medium','hard').
   * @return string Klasa BEM (np. "badge--success") lub "badge--secondary" jako fallback.
   */
  function question_difficulty_badge_class(?string $difficulty): string
  {
    return match ($difficulty) {
      'easy' => 'badge--success',
      'medium' => 'badge--warning',
      'hard' => 'badge--danger',
      default => 'badge--secondary',
    };
  }
}

if (!function_exists('csrf_token')) {
  function csrf_token(): string
  {
    return (new \App\Services\CsrfService())->token();
  }
}

if (!function_exists('csrf_field')) {
  function csrf_field(): string
  {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
  }
}
