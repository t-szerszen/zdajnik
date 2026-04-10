<?php

namespace App\Controllers;

/**
 * Kontroler Użytkownika (UserController).
 *
 * Obsługuje podstawowe akcje profilowe oraz systemowe zarezerwowane wyłącznie 
 * dla zalogowanych użytkowników. Zapewnia ochronę dostępu do widoków prywatnych 
 * poprzez wymuszenie sesji w konstruktorze.
 *
 * Główne funkcjonalności:
 * - Serwowanie widoku statystyk użytkownika.
 * - Bezpieczny proces wylogowania (niszczenie sesji).
 *
 * @version 1.1.0
 * @author Tobiasz Szerszeń
 * @package App\Controllers
 */
class UserController extends BaseController
{
  /**
   * Konstruktor zabezpieczający dostęp do metod kontrolera.
   * Wywołuje requireAuth() z BaseController, blokując dostęp dla gości.
   */
  public function __construct()
  {
    parent::__construct();
    // KROK: Weryfikacja tożsamości przed renderowaniem jakiejkolwiek akcji
    $this->requireAuth();
  }

  /**
   * Renderuje widok panelu statystyk i osiągnięć użytkownika.
   * Dane do tego widoku są pobierane asynchronicznie przez ApiController.
   */
  public function showStatistics(): void
  {
    $this->renderView('statistics', [
      'pageTitle' => 'Twoje Statystyki - Examly'
    ]);
  }

  /**
   * Wykonuje procedurę wylogowania użytkownika.
   *
   * Proces obejmuje:
   * 1. Wyczyszczenie tablicy $_SESSION.
   * 2. Fizyczne zniszczenie pliku sesji na serwerze.
   * 3. Przekierowanie do strony powitalnej/logowania.
   *
   * @return void
   */
  public function logout(): void
  {
    // KROK 1: Czyszczenie stanu sesji
    session_unset(); 

    // KROK 2: Terminacja sesji systemowej
    session_destroy(); 

    // KROK 3: Redirekcja do bramki logowania
    header('Location: ' . url('logowanie'));
    exit();
  }
}
