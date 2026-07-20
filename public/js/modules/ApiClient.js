/**
 * @file ApiClient.js
 * @module ApiClient
 * @description
 * Moduł dostarcza w pełni skonfigurowaną, gotową do użycia instancję klienta API
 * (wzorzec Singleton). Działa jako warstwa usług (Service Layer), hermetyzując
 * całą logikę komunikacji z backendem. Zapewnia to spójność, reużywalność
 * i centralne miejsce do zarządzania zapytaniami w całej aplikacji.
 *
 * ## Ustandaryzowany Format Odpowiedzi
 *
 * Wszystkie publiczne metody tego klienta zwracają `Promise`, które po
 * rozwiązaniu daje obiekt o ustandaryzowanej strukturze:
 * ```typescript
 * {
 * success: boolean, // true jeśli zapytanie powiodło się (status HTTP 2xx)
 * data?: object,     // Dane zwrócone przez API w przypadku sukcesu
 * error?: string     // Komunikat błędu w przypadku porażki
 * }
 * ```
 *
 * ## Przykład Użycia (w innym module JS)
 *
 * ```javascript
 * import api from './ApiClient.js';
 *
 * async function displayQuestion() {
 * const response = await api.fetchQuestion('INF.03', ['1', '2'], null);
 * if (response.success) {
 * console.log('Otrzymane pytanie:', response.data);
 * } else {
 * console.error('Błąd:', response.error);
 * }
 * }
 * ```
 *
 * @version 1.6.0
 * @author Tobiasz Szerszeń
 */

class ApiClient {
  /**
   * @constructs ApiClient
   * @param {string} baseUrl - Podstawowy URL, który będzie prefiksem dla
   * wszystkich zapytań do API.
   */
  constructor(baseUrl) {
    /**
     * @private
     * @type {string}
     */
    this.baseUrl = baseUrl;
  }

  /**
   * Centralna, prywatna metoda do wysyłania i obsługi wszystkich zapytań `fetch`.
   *
   * @private
   * @param {string} endpoint - Ścieżka endpointu API (np. '/question/INF.03').
   * @param {object} [options={}] - Obiekt konfiguracji dla `fetch`.
   * @returns {Promise<{success: boolean, data?: object, error?: string}>} Obiekt z wynikiem operacji.
   */
  async _request(endpoint, options = {}) {
    try {
      // Krok 1: Wykonaj zapytanie i poczekaj na odpowiedź.
      const response = await fetch(this.baseUrl + endpoint, options);
      // Krok 2: Sparsuj ciało odpowiedzi jako JSON.
      const data = await response.json();

      // Krok 3: Sprawdź status odpowiedzi HTTP.
      if (!response.ok) {
        // Jeśli serwer odpowiedział błędem (np. 404, 500), zwróć ustandaryzowany obiekt błędu.
        const errorMessage = data.message || `Błąd serwera: ${response.status}`;
        return { success: false, error: errorMessage, status: data.status };
      }

      // Krok 4: Jeśli status jest OK, zwróć ustandaryzowany obiekt sukcesu.
      return { success: true, data: data };
    } catch (error) {
      // Krok 5: Jeśli wystąpił błąd sieciowy (np. brak internetu), zaloguj go
      // i zwróć generyczny, przyjazny dla użytkownika komunikat.
      console.error(`Błąd sieci dla endpointu ${endpoint}:`, error);
      return { success: false, error: 'Błąd połączenia. Sprawdź swoje połączenie z internetem.' };
    }
  }

  /**
   * Pobiera pojedyncze pytanie dla określonego egzaminu i kryteriów.
   *
   * @param {string} examCode - Kod egzaminu (np. 'INF.03').
   * @param {string[]} subjects - Tablica ID wybranych tematów.
   * @param {string|null} premiumOption - Wybrana opcja premium lub `null`.
   * @returns {Promise<{success: boolean, data?: object, error?: string}>}
   */
  fetchQuestion(examCode, subjects, premiumOption) {
    // Krok 1: Stwórz obiekt URLSearchParams do bezpiecznego budowania parametrów GET.
    const params = new URLSearchParams();
    subjects.forEach((subjectId) => params.append('subject[]', subjectId));
    if (premiumOption) {
      params.append('premium_option', premiumOption);
    }

    // Krok 2: Zbuduj finalny URL endpointu.
    const endpoint = `/question/${examCode}?${params.toString()}`;

    // Krok 3: Wywołaj centralną metodę _request.
    return this._request(endpoint);
  }

  /**
   * Pobiera pełny test egzaminacyjny (40 pytań).
   *
   * @param {string} examCode - Kod egzaminu (np. 'INF.03').
   * @returns {Promise<{success: boolean, data?: object, error?: string}>}
   */
  fetchFullTest(examCode) {
    const endpoint = `/test/full/${examCode}`;
    return this._request(endpoint);
  }

  /**
   * Pobiera spersonalizowany zestaw pytań testowych.
   *
   * @param {string} examCode - Kod egzaminu.
   * @param {string[]} subjects - Tablica ID tematów.
   * @param {string|null} premiumOption - Opcja premium.
   * @param {number} questionCount - Liczba pytań do pobrania.
   * @returns {Promise<{success: boolean, data?: object, error?: string}>}
   */
  fetchPersonalizedTest(examCode, subjects, premiumOption, questionCount) {
    const params = new URLSearchParams();
    subjects.forEach((subjectId) => params.append('subject[]', subjectId));
    if (premiumOption) {
      params.append('premium_option', premiumOption);
    }
    params.append('question_count', questionCount);

    const endpoint = `/test/personalized/${examCode}?${params.toString()}`;
    return this._request(endpoint);
  }

  /**
   * Zapisuje wynik ukończonego testu (Attempt).
   * @param {object} attemptData - Dane zgodne z modelem Attempt.
   */
  saveAttempt(attemptData) {
    const options = {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(attemptData),
    };
    // Zmieniamy endpoint na /save-attempt
    return this._request('/save-attempt', options);
  }

  /**
   * Zapisuje masowo postęp użytkownika (każdą odpowiedź z testu).
   *
   * @param {Array<object>} progressData - Tablica obiektów z odpowiedziami,
   * np. `[{questionId: 1, isCorrect: true}]`.
   * @returns {Promise<{success: boolean, data?: object, error?: string}>}
   */
  saveBulkProgress(progressData) {
    const options = {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(progressData),
    };
    return this._request('/save-progress-bulk', options);
  }

  /**
   * Sprawdza poprawność odpowiedzi na serwerze.
   * @param {number} questionVersionId - ID wersji pytania.
   * @param {number} answerId - ID odpowiedzi.
   */
  checkAnswer(questionVersionId, answerId) {
    const options = {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        question_version_id: questionVersionId,
        answer_id: answerId,
      }),
    };
    return this._request('/check-answer', options);
  }

  /**
   * Przełącza stan zapisania pytania (Saved Question Toggle).
   * @param {number} questionId - ID pytania.
   * @returns {Promise<{success: boolean, data?: object, error?: string}>}
   */
  toggleSavedQuestion(questionId) {
    const options = {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        question_id: questionId,
      }),
    };
    return this._request('/toggle-saved', options);
  }

  /**
   * Zapisuje (upsert) notatkę użytkownika do arkusza z Biblioteki Arkuszy.
   *
   * @param {number|string} sheetId - ID arkusza.
   * @param {number|string} difficulty - Subiektywna trudność w skali 1-10.
   * @param {string} description - Opis napotkanych problemów.
   * @returns {Promise<{success: boolean, data?: object, error?: string}>}
   */
  saveSheetNote(sheetId, difficulty, description) {
    const options = {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        sheet_id: sheetId,
        difficulty,
        description,
      }),
    };
    return this._request('/sheet-note', options);
  }

  /**
   * Wysyła ograniczony event produktowy. Tryb beacon jest używany przy
   * opuszczaniu strony, kiedy zwykły fetch może zostać anulowany.
   */
  trackAnalyticsEvent(eventType, metadata = {}, useBeacon = false) {
    const payload = JSON.stringify({
      event_type: eventType,
      metadata,
    });
    const endpoint = `${this.baseUrl}/analytics/event`;

    if (useBeacon && navigator.sendBeacon) {
      const queued = navigator.sendBeacon(
        endpoint,
        new Blob([payload], { type: 'application/json' })
      );
      return Promise.resolve({ success: queued });
    }

    return this._request('/analytics/event', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: payload,
      keepalive: useBeacon,
    });
  }
}

/**
 * --- Eksport Instancji (Singleton) ---
 *
 * Tworzymy i eksportujemy jedną, gotową do użycia instancję klienta.
 * Zapewnia to, że cała aplikacja korzysta z tego samego, poprawnie
 * skonfigurowanego obiektu do komunikacji z API.
 */
const api = new ApiClient(`${window.zdajnikAppState.baseUrl}/api`);

export default api;
