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

if (!function_exists('question_markdown_converter')) {
  /**
   * Zwraca współdzieloną instancję konwertera markdownu dla treści pytań.
   *
   * Konfiguracja jest serwerowym odpowiednikiem tego, co po stronie klienta robi
   * marked.js z opcjami { gfm: true, breaks: true } (zob. UiHandler.js /
   * TestRunner.js) - dzięki temu to samo pytanie wygląda tak samo w trybie
   * interaktywnego quizu i na statycznej stronie publicznej:
   * - GithubFlavoredMarkdownExtension odpowiada opcji gfm: true,
   * - soft_break => "<br />\n" odpowiada opcji breaks: true (pojedyncza nowa
   *   linia daje <br>), więc widoki nie potrzebują już nl2br().
   *
   * Bezpieczeństwo zastępuje tu DOMPurify z wersji klienckiej: html_input
   * ustawione na 'escape' powoduje, że surowy HTML z bazy nigdy nie trafia do
   * wyjścia jako znaczniki (tylko jako tekst), a allow_unsafe_links wycina
   * adresy typu javascript:. Konwerter nie emituje więc HTML-a pochodzącego
   * z treści - osobny sanitizer (HTMLPurifier) byłby nadmiarowy.
   *
   * @return \League\CommonMark\MarkdownConverter Konwerter gotowy do użycia.
   */
  function question_markdown_converter(): \League\CommonMark\MarkdownConverter
  {
    // Budowa środowiska jest kosztowna, a w jednym żądaniu renderujemy wiele
    // pytań i odpowiedzi - stąd pojedyncza instancja na cykl życia żądania.
    static $converter = null;

    if ($converter === null) {
      $environment = new \League\CommonMark\Environment\Environment([
        'html_input' => 'escape',
        'allow_unsafe_links' => false,
        'renderer' => ['soft_break' => "<br />\n"],
      ]);
      $environment->addExtension(new \League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension());
      $environment->addExtension(new \League\CommonMark\Extension\GithubFlavoredMarkdownExtension());

      $converter = new \League\CommonMark\MarkdownConverter($environment);
    }

    return $converter;
  }
}

if (!function_exists('render_question_markdown')) {
  /**
   * Renderuje treść pytania, odpowiedzi lub wyjaśnienia jako bezpieczny HTML.
   *
   * Treści w bazie (question_versions.question_text, question_versions.explanation,
   * answers.answer_text) są pisane w markdownie - najczęściej **pogrubienie**,
   * fragmenty kodu w apostrofach oraz bloki kodu, rzadziej listy. Wynik jest
   * gotowy do wypisania bez htmlspecialchars() i bez nl2br() - obydwa kroki
   * realizuje już konwerter (zob. question_markdown_converter()).
   *
   * @param string $text Surowa treść w markdownie.
   * @return string Bezpieczny HTML.
   */
  function render_question_markdown(string $text): string
  {
    if (trim($text) === '') {
      return '';
    }

    return question_markdown_converter()->convert($text)->getContent();
  }
}

if (!function_exists('render_question_markdown_heading')) {
  /**
   * Renderuje treść pytania z podziałem na część nagłówkową i resztę bloków.
   *
   * Potrzebne tam, gdzie treść pytania trafia do <h1> (publiczna strona pytania).
   * Nagłówek przyjmuje wyłącznie treść liniową, a część pytań zawiera bloki kodu
   * albo listy - wrzucenie ich do <h1> dałoby niepoprawny HTML na stronie, której
   * jedynym celem jest dobra indeksacja. Pierwszy akapit trafia więc do nagłówka
   * (bez otaczającego <p>), a pozostałe bloki renderowane są pod nim.
   *
   * @param string $text Surowa treść pytania w markdownie.
   * @return array{heading: string, rest: string} Treść nagłówka i dalszych bloków.
   */
  function render_question_markdown_heading(string $text): array
  {
    $html = render_question_markdown($text);

    // Najczęstszy przypadek: treść zaczyna się od zwykłego akapitu.
    if (preg_match('/^\s*<p>(.*?)<\/p>\s*/s', $html, $matches) === 1) {
      return [
        'heading' => $matches[1],
        'rest' => trim(substr($html, strlen($matches[0]))),
      ];
    }

    // Pytania zaczynające się od bloku (np. od razu blok kodu) nie mają wiodącego
    // akapitu - zamiast zostawiać nagłówek pusty (co zmuszało widok do wrzucenia
    // tam surowego, obciętego podglądu CAŁEJ treści łącznie z kodem - patrz np.
    // pytanie #2747), szukamy pierwszego akapitu GDZIEKOLWIEK w treści (zwykle
    // zdania z poleceniem, które następuje po bloku kodu) i przenosimy właśnie
    // jego do nagłówka, usuwając to konkretne wystąpienie z reszty, żeby się nie
    // powielało.
    if (preg_match('/<p>(.*?)<\/p>/s', $html, $matches, PREG_OFFSET_CAPTURE) === 1) {
      $fullMatch = $matches[0][0];
      $offset = $matches[0][1];
      $rest = trim(substr($html, 0, $offset) . substr($html, $offset + strlen($fullMatch)));

      return ['heading' => $matches[1][0], 'rest' => $rest];
    }

    // Treść w całości blokowa (np. sam kod/lista, bez żadnego zdania) - nie da
    // się z niej wydobyć sensownego nagłówka, widok podstawia wtedy zwykły skrót.
    return ['heading' => '', 'rest' => $html];
  }
}

if (!function_exists('question_plain_text')) {
  /**
   * Spłaszcza treść pytania w markdownie do czystego tekstu.
   *
   * Potrzebne wszędzie tam, gdzie HTML byłby błędem: <title>, meta description,
   * dane strukturalne JSON-LD, okruszki. Tekst jest najpierw renderowany tym
   * samym konwerterem co widoki, a dopiero potem odzierany ze znaczników -
   * dzięki temu składnia markdownu znika w całości (nie tylko **pogrubienie**),
   * a fragmenty kodu przetrwają. Naiwne strip_tags() na surowym markdownie
   * kasowałoby treść odpowiedzi takich jak `<html>`, bo wyglądają one dla
   * niego jak znacznik HTML.
   *
   * @param string $text Surowa treść w markdownie.
   * @return string Jednolinijkowy czysty tekst.
   */
  function question_plain_text(string $text): string
  {
    $html = render_question_markdown($text);

    // Znaczniki blokowe i <br> muszą zostawić po sobie odstęp, inaczej
    // strip_tags() skleiłby sąsiednie linie w jedno słowo.
    $html = (string) preg_replace('/<(br|\/p|\/li|\/h[1-6]|\/pre|\/tr|\/td|\/th)\b[^>]*>/i', ' ', $html);

    $plain = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return trim((string) preg_replace('/\s+/u', ' ', $plain));
  }
}

if (!function_exists('question_excerpt')) {
  /**
   * Skraca treść pytania do jednej linii o zadanej długości.
   *
   * Treści pytań bywają wieloliniowe i zawierają markdown oraz fragmenty kodu,
   * więc przed użyciem w <title>, meta description czy okruszkach trzeba je
   * spłaszczyć do pojedynczej linii i przyciąć. Samo usuwanie składni markdownu
   * realizuje question_plain_text().
   *
   * @param string $text Surowa treść pytania.
   * @param int $length Maksymalna długość wyniku (wraz z wielokropkiem).
   * @return string Jednolinijkowy skrót.
   */
  function question_excerpt(string $text, int $length = 150): string
  {
    return mb_strimwidth(question_plain_text($text), 0, $length, '…');
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

if (!function_exists('js_assets_version')) {
  /**
   * Zwraca wspólny numer wersji (cache-busting) dla całego drzewa public/js/.
   *
   * Zmienia się, gdy zmieni się mtime dowolnego pliku .js - main.js oraz
   * dynamicznie importowane moduły dzielą więc jeden numer wersji, co jest
   * konieczne, żeby modulepreload w <head> (head.php) trafiał w te same URL-e
   * co import() w main.js (który dokleja tę samą wartość jako query string
   * do każdego dynamicznego importu - patrz CACHE_QUERY w public/js/main.js).
   *
   * @return int Znacznik czasu (mtime) najnowszego pliku .js.
   */
  function js_assets_version(): int
  {
    static $version = null;

    if ($version === null) {
      $version = filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/main.js');

      $jsFiles = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($_SERVER['DOCUMENT_ROOT'] . '/js', FilesystemIterator::SKIP_DOTS)
      );
      foreach ($jsFiles as $jsFile) {
        if ($jsFile->isFile() && $jsFile->getExtension() === 'js') {
          $version = max($version, $jsFile->getMTime());
        }
      }
    }

    return $version;
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
