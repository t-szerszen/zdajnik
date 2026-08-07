<?php

namespace App\Controllers;

use App\Models\Question;
use App\Models\Sheet;
use App\Services\DynamicDataService;

/**
 * Kontroler Mapy Witryny (Sitemap).
 *
 * Generuje /sitemap.xml w locie, dzięki czemu każde pytanie dodane w panelu
 * administracyjnym jest widoczne dla wyszukiwarek bez ponownego wdrożenia.
 * Mapa obejmuje strony statyczne, tryby nauki każdej udostępnionej kwalifikacji,
 * publiczną bazę pytań (hub, listingi, pojedyncze pytania) oraz - gdy sekcja
 * zostanie upubliczniona - Bibliotekę Arkuszy.
 *
 * Odpowiedź jest wypisywana bezpośrednio, z pominięciem renderView() - inaczej
 * BaseController zapisywałby każde wejście robota jako zdarzenie `page_view`
 * i zaburzał statystyki produktu.
 *
 * @version 2.0.0
 * @author Tobiasz Szerszeń
 * @package App\Controllers
 */
class SitemapController extends BaseController
{
  /** @var int Liczba pytań na stronie listingu - musi odpowiadać QuestionController */
  private const QUESTIONS_PER_PAGE = 50;

  /**
   * @var string[] Ścieżki stron statycznych. Nie mają w bazie znacznika
   * modyfikacji, więc trafiają do mapy bez pola <lastmod>.
   */
  private const STATIC_PATHS = [
    '',
    'search',
    'o-projekcie',
    'kontakt',
    'polityka-prywatnosci',
  ];

  /** @var array<string, array{loc: string, lastmod: string|null}> Wpisy mapy kluczowane adresem (deduplikacja) */
  private array $urls = [];

  /** @var Question Model do operacji na bazie pytań */
  private Question $questionModel;

  /** @var Sheet Model biblioteki arkuszy */
  private Sheet $sheetModel;

  /** @var DynamicDataService Serwis danych o kwalifikacjach */
  private DynamicDataService $dynamicData;

  /**
   * Inicjalizuje generator mapy witryny.
   */
  public function __construct()
  {
    parent::__construct();
    $this->questionModel = new Question();
    $this->sheetModel = new Sheet();
    $this->dynamicData = new DynamicDataService();
  }

  /**
   * Buduje i wypisuje kompletną mapę witryny.
   *
   * UWAGA: standard sitemap dopuszcza 50 000 adresów (i 50 MB) na plik. Przy
   * zbliżaniu się do tego progu należy rozbić wynik na mapy per sekcja i wystawić
   * pod tym adresem indeks (<sitemapindex>) zamiast pojedynczego <urlset>.
   *
   * @return void
   */
  public function index(): void
  {
    $this->collectStaticPages();
    $this->collectExamPages();
    $this->collectQuestionPages();
    $this->collectSheetPages();

    header('Content-Type: application/xml; charset=UTF-8');

    echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

    foreach ($this->urls as $entry) {
      echo '  <url>' . PHP_EOL;
      echo '    <loc>' . htmlspecialchars($entry['loc'], ENT_QUOTES | ENT_XML1, 'UTF-8') . '</loc>' . PHP_EOL;

      if ($entry['lastmod'] !== null) {
        echo '    <lastmod>' . $entry['lastmod'] . '</lastmod>' . PHP_EOL;
      }

      echo '  </url>' . PHP_EOL;
    }

    echo '</urlset>' . PHP_EOL;
    exit;
  }

  /**
   * Strony statyczne serwisu.
   *
   * @return void
   */
  private function collectStaticPages(): void
  {
    foreach (self::STATIC_PATHS as $path) {
      $this->addUrl(url($path === '' ? '/' : $path));
    }
  }

  /**
   * Tryby nauki każdej udostępnionej kwalifikacji.
   *
   * Lista kwalifikacji pochodzi z DynamicDataService::AVAILABLE_EXAM_CODES -
   * z tego samego źródła korzystają wyszukiwarka i publiczne strony pytań,
   * więc mapa nigdy nie wskaże kwalifikacji oznaczonej na stronie głównej
   * jako "Wkrótce".
   *
   * @return void
   */
  private function collectExamPages(): void
  {
    foreach ($this->dynamicData->getSearchableExamsWithTimestamps() as $exam) {
      $code = exam_code_to_url((string) $exam['code']);
      $lastmod = $this->formatLastmod((string) ($exam['updated_at'] ?? ''));

      $this->addUrl(url('test/' . $code), $lastmod);
      $this->addUrl(url('jedno-pytanie/' . $code), $lastmod);
      $this->addUrl(url('personalizowany-test/' . $code), $lastmod);
    }
  }

  /**
   * Publiczna baza pytań: hub, stronicowane listingi i pojedyncze pytania.
   *
   * @return void
   */
  private function collectQuestionPages(): void
  {
    $summary = $this->questionModel->getPublicExamSummary();

    if (empty($summary)) {
      return;
    }

    // Hub odzwierciedla najświeższą zmianę w całej bazie pytań
    $newest = max(array_map(
      static fn(array $exam): string => (string) ($exam['lastmod'] ?? ''),
      $summary
    ));
    $this->addUrl(url('pytania'), $this->formatLastmod($newest));

    foreach ($summary as $exam) {
      $examCode = (string) $exam['exam_code'];
      $urlCode = exam_code_to_url($examCode);
      $examLastmod = $this->formatLastmod((string) ($exam['lastmod'] ?? ''));

      $examTypeId = $this->dynamicData->getExamIdByCode($examCode);
      if ($examTypeId === null) {
        continue;
      }

      // Listing wraz z kolejnymi stronami paginacji
      $totalPages = max(1, (int) ceil((int) $exam['questions_count'] / self::QUESTIONS_PER_PAGE));
      for ($page = 1; $page <= $totalPages; $page++) {
        $listingUrl = url('pytania/' . $urlCode) . ($page > 1 ? '?strona=' . $page : '');
        $this->addUrl($listingUrl, $examLastmod);
      }

      foreach ($this->questionModel->getPublicSitemapRows($examTypeId) as $row) {
        $this->addUrl(question_url($row), $this->formatLastmod((string) ($row['lastmod'] ?? '')));
      }
    }
  }

  /**
   * Biblioteka Arkuszy - hub, listingi kwalifikacji i strony arkuszy.
   *
   * Sekcja trafia do mapy dopiero po jej upublicznieniu; dopóki
   * SheetController::isLibraryPublic() zwraca false, wszystkie te adresy
   * przekierowują na stronę główną i nie powinny być zgłaszane wyszukiwarkom.
   *
   * @return void
   */
  private function collectSheetPages(): void
  {
    if (!SheetController::isLibraryPublic()) {
      return;
    }

    $sheets = $this->sheetModel->getAllActiveForSitemap();

    if (empty($sheets)) {
      return;
    }

    $this->addUrl(url('arkusze'));

    $listedExams = [];
    foreach ($sheets as $sheet) {
      $examCode = (string) $sheet['exam_code'];
      $lastmod = $this->formatLastmod((string) ($sheet['updated_at'] ?? ''));

      if (!isset($listedExams[$examCode])) {
        $this->addUrl(url('arkusze/' . exam_code_to_url($examCode)));
        $listedExams[$examCode] = true;
      }

      $this->addUrl(url('arkusze/' . exam_code_to_url($examCode) . '/' . (int) $sheet['id']), $lastmod);
    }
  }

  /**
   * Dopisuje adres do mapy, pomijając duplikaty.
   *
   * @param string $loc Pełny adres URL.
   * @param string|null $lastmod Data w formacie W3C lub null, gdy nieznana.
   * @return void
   */
  private function addUrl(string $loc, ?string $lastmod = null): void
  {
    if (isset($this->urls[$loc])) {
      return;
    }

    $this->urls[$loc] = ['loc' => $loc, 'lastmod' => $lastmod];
  }

  /**
   * Formatuje datę z bazy na format wymagany przez standard sitemap (W3C Datetime).
   *
   * @param string $dbDate Data w formacie MySQL.
   * @return string|null Data ISO 8601 lub null przy braku / niepoprawnej wartości.
   */
  private function formatLastmod(string $dbDate): ?string
  {
    if ($dbDate === '') {
      return null;
    }

    $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dbDate);

    return $date === false ? null : $date->format(DATE_ATOM);
  }
}
