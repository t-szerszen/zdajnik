<?php

namespace App\Controllers;

use App\Models\Question;
use App\Services\DynamicDataService;

/**
 * Kontroler Wyszukiwarki Pytań.
 *
 * Lekki punkt wejścia do bazy pytań: wybór kwalifikacji (ewentualnie z
 * wpisaną wcześniej frazą) prowadzi wprost do filtrowanego listingu
 * /pytania/{kod}, gdzie mieszka już cała logika filtrowania/paginacji
 * (patrz QuestionController::listByExam()). Ta klasa odpowiada wyłącznie
 * za sam wybór kwalifikacji oraz krótkie wyszukiwanie międzykwalifikacyjne
 * (po treści/ID), zanim użytkownik zdecyduje, której kwalifikacji szuka.
 *
 * @version 2.0.0
 * @author Tobiasz Szerszeń
 * @package App\Controllers
 */
class SearchController extends BaseController
{
  /** @var Question Model do operacji na pytaniach egzaminacyjnych */
  private Question $questionModel;

  /** @var DynamicDataService Serwis do pobierania struktury egzaminów i kategorii */
  private DynamicDataService $dynamicData;

  /**
   * Inicjalizuje kontroler wyszukiwarki.
   *
   * Ustawia dostęp do modeli i serwisów danych. Akcja nie wymaga autoryzacji,
   * co pozwala na przeglądanie bazy pytań przez gości (szczegóły pytań
   * są chronione w ReviewController).
   */
  public function __construct()
  {
    parent::__construct();
    $this->questionModel = new Question();
    $this->dynamicData = new DynamicDataService();
  }

  /**
   * Główny punkt wejściowy wyszukiwarki (/search).
   *
   * Stare adresy z numerycznym `exam_id` (poprzednia wersja wyszukiwarki)
   * dostają 301 na kanoniczny, filtrowany listing /pytania/{kod}. W
   * przeciwnym razie renderuje wybór kwalifikacji i - jeśli podano `q` -
   * krótką listę wyników międzykwalifikacyjnych.
   *
   * @return void
   */
  public function showSearch(): void
  {
    // Kompatybilność wsteczna: stare zakładki/linki z wybraną kwalifikacją
    // (numeryczne `exam_id`) przenosimy na kanoniczny adres nowego listingu.
    if (!empty($_GET['exam_id'])) {
      $examCode = $this->dynamicData->getExamCodeById((int) $_GET['exam_id']);

      // Stary parametr mógł wskazywać na kwalifikację bez gotowej, publicznej
      // bazy pytań (np. "Wkrótce") - przekierowanie miałoby wtedy sens tylko
      // do strony, która i tak zwróci 404, więc traktujemy to jak brak dopasowania.
      if ($examCode !== null && in_array(strtoupper($examCode), DynamicDataService::AVAILABLE_EXAM_CODES, true)) {
        $target = url('pytania/' . exam_code_to_url($examCode));
        $params = array_filter([
          'temat' => !empty($_GET['topic_id']) ? (int) $_GET['topic_id'] : null,
          'q' => !empty($_GET['query']) ? trim((string) $_GET['query']) : null,
        ]);
        if ($params) {
          $target .= '?' . http_build_query($params);
        }

        header('Location: ' . $target, true, 301);
        exit;
      }
    }

    $query = trim((string) ($_GET['q'] ?? $_GET['query'] ?? ''));

    $this->renderView('search', [
      'pageTitle' => 'Wyszukiwarka Pytań | Zdajnik',
      'pageDescription' => 'Wybierz kwalifikację lub znajdź pytanie po treści/numerze (ID) w bazie egzaminacyjnej.',
      'canonicalUrl' => url('search'),
      'noIndex' => $query !== '',
      'exams' => $this->dynamicData->getSearchableExams(),
      'query' => $query,
      'crossExamResults' => $query !== '' ? $this->questionModel->searchAcrossExams($query, 10) : [],
    ]);
  }
}
