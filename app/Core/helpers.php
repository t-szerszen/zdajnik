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
