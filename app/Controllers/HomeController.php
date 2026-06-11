<?php

namespace App\Controllers;

use App\Services\DynamicDataService;

/**
 * Kontroler Strony Głównej (HomeController).
 *
 * Odpowiada za serwowanie głównego punktu wejściowego do aplikacji oraz 
 * stron informacyjnych i prawnych (Polityka Prywatności).
 * 
 * Głównym zadaniem kontrolera jest agregacja dostępnych kwalifikacji (egzaminów) 
 * i przypisanych do nich tematów, aby umożliwić użytkownikowi wybór ścieżki nauki 
 * bezpośrednio ze strony startowej.
 *
 * @version 1.2.0
 * @author Tobiasz Szerszeń
 * @package App\Controllers
 */
class HomeController extends BaseController
{
  /** @var DynamicDataService Serwis dostarczający dane o strukturze egzaminów */
  private DynamicDataService $dynamicDataService;

  /**
   * Projektuje i inicjalizuje komponenty strony głównej.
   */
  public function __construct()
  {
    parent::__construct();
    $this->dynamicDataService = new DynamicDataService();
  }

  /**
   * Renderuje widok strony głównej (Landing Page).
   * 
   * @return void
   */
  public function show(): void
  {
    // KROK: Pobranie struktury kwalifikacji i ich kategorii tematycznych
    $examsCategories = $this->loadExamsAndTopics();

    // KROK: Wyświetlenie widoku startowego z dynamiczną listą egzaminów
    $this->renderView('home', [
      'examsCategories' => $examsCategories,
      'pageTitle' => 'Examly - pytania i testy do egzaminów zawodowych'
    ]);
  }

  /**
   * Renderuje widok Polityki Prywatności i Regulaminu serwisu (GDPR compliance).
   * 
   * @return void
   */
  public function showPrivacyPolicy(): void
  {
    $this->renderView('privacy_policy', [
      'pageTitle' => 'Polityka Prywatności - Examly'
    ]);
  }

  /**
   * Wewnętrzna logika agregacji struktury egzaminacyjnej.
   * 
   * Wykonuje iteracyjne zapytania po tematach dla każdego dostępnego typu egzaminu.
   * 
   * @return array<string, array> Tablica asocjacyjna [kod_egzaminu => [lista_kategorii]].
   */
  private function loadExamsAndTopics(): array
  {
    // KROK 1: Pobranie unikalnych kodów kwalifikacji (INF.02, INF.03, etc.)
    $exams = $this->dynamicDataService->getExams();

    $examsCategories = [];
    foreach ($exams as $exam) {
      // KROK 2: Pobranie podkategorii (Topików) dla każdej kwalifikacji
      $categories = $this->dynamicDataService->getCategoriesForExam($exam['id']);
      $examsCategories[$exam['code']] = $categories;
    }

    return $examsCategories;
  }
}
