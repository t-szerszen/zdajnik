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

if (!function_exists('question_excerpt')) {
  /**
   * Skraca treść pytania do jednej linii o zadanej długości.
   *
   * Treści pytań bywają wieloliniowe i zawierają fragmenty kodu, więc przed
   * użyciem w <title>, meta description czy okruszkach trzeba je spłaszczyć
   * do pojedynczej linii i przyciąć. Usuwane są też markdownowe znaczniki
   * pogrubienia (**tekst**), które w bazie występują w części wyjaśnień -
   * w tytule strony wyglądałyby jak literówka.
   *
   * @param string $text Surowa treść pytania.
   * @param int $length Maksymalna długość wyniku (wraz z wielokropkiem).
   * @return string Jednolinijkowy skrót.
   */
  function question_excerpt(string $text, int $length = 150): string
  {
    $clean = strip_tags($text);

    // Wyłącznie sparowane znaczniki - pojedyncza gwiazdka bywa częścią treści (np. SELECT *)
    $clean = (string) preg_replace('/\*\*(.+?)\*\*/u', '$1', $clean);

    $clean = trim((string) preg_replace('/\s+/u', ' ', $clean));

    return mb_strimwidth($clean, 0, $length, '…');
  }
}

if (!function_exists('question_slug')) {
  /**
   * Buduje slug adresu URL na podstawie treści pytania.
   *
   * Nadbudowa nad slugify() - dokłada obcinanie długości na granicy słowa,
   * żeby adres nie kończył się uciętym w połowie wyrazem. Slug nie musi być
   * unikalny: identyfikatorem pytania w adresie pozostaje {id}, a rozjazd
   * slug <-> treść obsługuje przekierowanie 301 na adres kanoniczny.
   *
   * @param string $text Treść pytania.
   * @param int $maxLength Maksymalna długość sluga.
   * @return string Slug (małe litery, cyfry i myślniki).
   */
  function question_slug(string $text, int $maxLength = 70): string
  {
    $slug = slugify(question_excerpt($text, $maxLength * 2));

    if (strlen($slug) > $maxLength) {
      $slug = substr($slug, 0, $maxLength);

      // Obcięcie do ostatniego pełnego słowa (o ile coś z niego zostanie)
      $lastDash = strrpos($slug, '-');
      if ($lastDash !== false && $lastDash > 0) {
        $slug = substr($slug, 0, $lastDash);
      }
    }

    $slug = trim($slug, '-');

    // slugify() zwraca własny fallback ('plik'), tutaj potrzebny jest sensowniejszy
    return ($slug === '' || $slug === 'plik') ? 'pytanie' : $slug;
  }
}

if (!function_exists('exam_code_to_url')) {
  /**
   * Zamienia kod kwalifikacji na segment adresu URL (np. "INF.03" => "inf03").
   *
   * Nie da się tu użyć slugify(), bo zamieniłoby kropkę na myślnik ("inf-03").
   *
   * @param string $code Kod kwalifikacji z bazy.
   * @return string Segment adresu URL.
   */
  function exam_code_to_url(string $code): string
  {
    return strtolower((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
  }
}

if (!function_exists('question_url')) {
  /**
   * Buduje kanoniczny adres publicznej strony pytania.
   *
   * Jedyne miejsce w aplikacji, w którym powstaje adres /pytania/{kod}/{slug}-{id} -
   * używane w listingach, blokach "podobne pytania", tagu canonical, sitemapie
   * oraz w wynikach wyszukiwarki.
   *
   * @param array $question Wiersz z kluczami exam_code, question_text oraz question_id (lub id).
   * @return string Pełny adres URL.
   */
  function question_url(array $question): string
  {
    $questionId = (int) ($question['question_id'] ?? $question['id'] ?? 0);
    $examSegment = exam_code_to_url((string) ($question['exam_code'] ?? ''));
    $slug = question_slug((string) ($question['question_text'] ?? ''));

    return url("pytania/{$examSegment}/{$slug}-{$questionId}");
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
