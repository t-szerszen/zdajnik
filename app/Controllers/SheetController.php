<?php

namespace App\Controllers;

use App\Models\Sheet;
use App\Models\SheetNote;
use App\Services\DynamicDataService;

/**
 * Kontroler Biblioteki Arkuszy.
 *
 * Obsługuje publiczną część modułu arkuszy, podzieloną per kwalifikacja:
 * - hub (`arkusze`) — wybór kwalifikacji wraz z liczbą dostępnych arkuszy,
 * - lista (`arkusze/{code}`) — arkusze danej kwalifikacji pogrupowane po tematach,
 * - szczegóły (`arkusze/{code}/{sheetId}`) — pliki, podgląd PDF i notatka,
 * - panel notatek (`moje-notatki`) — wszystkie notatki zalogowanego użytkownika.
 *
 * @version 2.0.0
 * @author Tobiasz Szerszeń
 * @package App\Controllers
 */
class SheetController extends BaseController
{
  /** @var Sheet Model odczytu arkuszy */
  private Sheet $sheetModel;

  /** @var SheetNote Model notatek użytkowników */
  private SheetNote $noteModel;

  /** @var DynamicDataService Serwis danych kwalifikacji (lookup po kodzie) */
  private DynamicDataService $dynamicData;

  public function __construct()
  {
    parent::__construct();
    $this->sheetModel = new Sheet();
    $this->noteModel = new SheetNote();
    $this->dynamicData = new DynamicDataService();
  }

  /**
   * Tymczasowe zabezpieczenie: Biblioteka Arkuszy nie jest jeszcze udostępniona
   * użytkownikom (funkcja w przygotowaniu), więc każde publiczne żądanie
   * jest przekierowywane na stronę główną.
   *
   * @return void
   */
  private function checkAvailability(): void
  {
    header('Location: ' . url('/'));
    exit();
  }

  /**
   * Hub biblioteki — wybór kwalifikacji.
   *
   * @return void
   */
  public function index(): void
  {
    $this->checkAvailability();

    $this->renderView('sheets_hub', [
      'pageTitle' => 'Biblioteka Arkuszy | Zdajnik',
      'pageDescription' => 'Arkusze praktyczne do egzaminów zawodowych INF — wybierz kwalifikację i przeglądaj arkusze z poziomem trudności oraz prywatnymi notatkami.',
      'exams' => $this->sheetModel->getExamsWithSheetCounts(),
    ]);
  }

  /**
   * Lista arkuszy wybranej kwalifikacji, pogrupowana po tematach.
   *
   * @param string $code Kod kwalifikacji z adresu URL (np. INF.04).
   * @return void
   */
  public function listByExam(string $code): void
  {
    $this->checkAvailability();

    $exam = $this->dynamicData->getExamDetails($code);

    if (!$exam) {
      http_response_code(404);
      $errorController = new ErrorController();
      $errorController->showError(404, 'Kwalifikacja nie została znaleziona.');
      return;
    }

    $userId = $this->isUserLoggedIn ? $this->currentUser->getId() : null;

    $this->renderView('sheets', [
      'pageTitle' => 'Arkusze ' . $exam['code'] . ' | Biblioteka Arkuszy | Zdajnik',
      'pageDescription' => 'Arkusze praktyczne ' . $exam['code'] . ' pogrupowane po tematach, z poziomem trudności i prywatnymi notatkami do każdego arkusza.',
      'exam' => $exam,
      'topics' => $this->sheetModel->getTopicsWithSheets((int) $exam['id'], $userId),
    ]);
  }

  /**
   * Szczegóły pojedynczego arkusza wraz z notatką użytkownika.
   *
   * @param string $code Kod kwalifikacji z adresu URL.
   * @param string $sheetId ID arkusza z adresu URL.
   * @return void
   */
  public function show(string $code, string $sheetId): void
  {
    $this->checkAvailability();

    $sheet = ctype_digit($sheetId) ? $this->sheetModel->findActiveById((int) $sheetId) : false;

    // Arkusz musi istnieć i należeć do kwalifikacji wskazanej w adresie
    if (!$sheet || strcasecmp((string) $sheet['exam_code'], $code) !== 0) {
      http_response_code(404);
      $errorController = new ErrorController();
      $errorController->showError(404, 'Arkusz nie został znaleziony.');
      return;
    }

    $note = null;
    if ($this->isUserLoggedIn) {
      $note = $this->noteModel->findForUser($this->currentUser->getId(), (int) $sheet['id']) ?: null;
    }

    $this->renderView('sheet_detail', [
      'pageTitle' => $sheet['title'] . ' | Arkusze ' . $sheet['exam_code'] . ' | Zdajnik',
      'pageDescription' => 'Arkusz „' . $sheet['title'] . '” (' . $sheet['exam_code'] . ') — podgląd PDF, pliki do pobrania i prywatna notatka.',
      'sheet' => $sheet,
      'note' => $note,
    ]);
  }

  /**
   * Panel "Moje notatki" — wszystkie notatki użytkownika do arkuszy.
   *
   * @return void
   */
  public function myNotes(): void
  {
    $this->checkAvailability();
    $this->requireAuth();
    $userId = $this->currentUser->getId();

    $sort = ($_GET['sortowanie'] ?? '') === 'najnowsze' ? 'najnowsze' : 'najtrudniejsze';

    $this->renderView('sheet_notes', [
      'pageTitle' => 'Moje notatki | Biblioteka Arkuszy | Zdajnik',
      'pageDescription' => 'Twoje notatki do arkuszy — subiektywne oceny trudności i opisy problemów w jednym miejscu.',
      'notes' => $this->noteModel->getAllForUser($userId, $sort),
      'summary' => $this->noteModel->getSummaryForUser($userId),
      'sort' => $sort,
    ]);
  }
}
