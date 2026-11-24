/**
 * @file /features/one-question/index.js
 * @module one-question-quiz
 * @description
 * Główny skrypt sterujący logiką interaktywnego quizu w trybie "jedno pytanie".
 * Działa jako **Kontroler** w architekturze frontendowej, który orkiestruje
 * komunikację między modelem (`ApiClient`) a widokiem (`UiHandler`),
 * zarządzając całym stanem i przepływem aplikacji.
 *
 * ## Wzorce i Architektura
 *
 * - Kontroler: Centralny punkt logiki, który reaguje na akcje użytkownika.
 * - Event Delegation:** Efektywnie obsługuje zdarzenia na dynamicznie
 *   tworzonych elementach.
 * - Zależności: Wstrzykuje i wykorzystuje usługi `ApiClient` i `UiHandler`.
 *
 * @version 1.6.0
 * @author Tobiasz Szerszeń
 */

import api from '../../modules/ApiClient.js';
import ui from '../../modules/UiHandler.js';
import Toast from '../../components/Toast.js';
import saveToggle from '../../components/SaveToggleHandler.js';

export class SingleQuestionPage {
  /**
   * Wyszukuje kluczowe elementy DOM i dane inicjalizacyjne, a następnie
   * uruchamia logikę quizu.
   * @constructs Quiz
   */
  constructor() {
    // Krok 1: Wyszukaj i przypisz kluczowe elementy DOM.
    this.pageContainer = document.getElementById('quiz-single-question');
    this.topicForm = document.getElementById('topic-form');
    this.quizContainer = document.getElementById('quiz-container');
    this.progressBar = document.getElementById('quiz-progress-bar');
    this.progressCounts = document.getElementById('progress-counts');
    this.progressPercent = document.getElementById('progress-percent');
    this.progressFill = document.getElementById('progress-fill');

    // Krok 2: Pobierz dane konfiguracyjne z atrybutów HTML.
    this.examCode = this.pageContainer?.dataset.examCode || null;

    // Krok 3: Zainicjuj wewnętrzny stan.
    this.currentExplanation = null;
    this.currentQuestionVersionId = null; // DODANE
    this.currentQuestionId = null;
    this.currentTopicId = null;
    this.currentExamId = null;
    this.currentQuestionWasAnswered = false;
    this.progressUpdatedForCurrentQuestion = false;
    this.currentProgress = { total: 0, answered: 0 };
    this.loaderTimer = null;

    // Krok 4: Sprawdź, czy wszystko jest na miejscu i uruchom logikę.
    if (this.topicForm && this.quizContainer && this.examCode) {
      this.init();
    } else {
      console.error('Błąd inicjalizacji quizu: brak kluczowych elementów DOM lub atrybutu data-exam-code.');
    }
  }

  /**
   * Wiąże główne nasłuchiwacze zdarzeń z elementami DOM.
   * @private
   */
  init() {
    // Krok 1: Nasłuchuj na wysłanie formularza z tematami.
    this.topicForm.addEventListener('submit', this.handleTopicSubmit.bind(this));
    // Krok 2: Użyj delegacji zdarzeń do obsługi kliknięć wewnątrz kontenera quizu.
    // Jest to wydajniejsze niż dodawanie wielu listenerów do dynamicznych elementów.
    this.quizContainer.addEventListener('click', this.handleInteraction.bind(this));
    this.quizContainer.addEventListener('keydown', this.handleAnswerKeydown.bind(this));

    // Krok 3: Sprawdź URL pod kątem autostartu
    this.handleAutoStart();
  }

  /**
   * Obsługuje automatyczne rozpoczęcie quizu na podstawie URL.
   * @private
   */
  handleAutoStart() {
    const urlParams = new URLSearchParams(window.location.search);
    const hash = window.location.hash;
    const shouldAutoStart = urlParams.get('selectAll') === 'true' || urlParams.get('autoStart') === 'true' || hash === '#select-all-codes';

    if (shouldAutoStart) {
      // Małe opóźnienie, aby TopicFormEnhancer zdążył przetworzyć checkboxy
      setTimeout(() => {
        this.topicForm.dispatchEvent(new Event('submit', { cancelable: true }));
      }, 100);
    }
  }

  /**
   * Obsługuje wysłanie formularza z kategoriami.
   * @private
   * @async
   * @param {Event} event - Obiekt zdarzenia 'submit'.
   */
  async handleTopicSubmit(event) {
    // Krok 1: Zatrzymaj domyślną akcję przeładowania strony przez formularz.
    event.preventDefault();
    // Krok 2: Rozpocznij proces ładowania nowego pytania.
    await this.startNewQuestion();
  }

  /**
   * Zmodyfikowana metoda startNewQuestion
   */
  async startNewQuestion() {
    this.currentExplanation = null;
    this.currentQuestionVersionId = null; // Resetujemy stan
    this.currentQuestionId = null;
    this.currentTopicId = null;
    this.currentExamId = null;
    this.currentQuestionWasAnswered = false;
    this.progressUpdatedForCurrentQuestion = false;

    const selectedSubjects = [...this.topicForm.querySelectorAll('input[name="subject[]"]:checked')]
      .map(cb => cb.value);
    const premiumOption = this.topicForm.querySelector('input[name="premium_option"]:checked')?.value || null;

    if (selectedSubjects.length === 0) {
      api.trackAnalyticsEvent('validation_error', {
        source: 'single_question_configurator',
        result: 'missing_topics',
        count: 0,
      }).catch(() => {});
      Toast.show('Wybierz przynajmniej jedną kategorię.', 'info');
      return;
    }

    try {
      this.loaderTimer = setTimeout(() => { ui.showLoader(this.quizContainer); }, 300);

      const result = await api.fetchQuestion(this.examCode, selectedSubjects, premiumOption);

      if (result.success) {
        const data = result.data;
        if (data.status === 'no_questions_left') {
          Toast.show(data.message, 'info');
          this.quizContainer.innerHTML = '<p class="quiz-placeholder">Wybierz inne kryteria.</p>';
        } else {
          // KROK KLUCZOWY: Zapisujemy question_version_id do stanu klasy
          this.currentExplanation = data.question.explanation;
          this.currentQuestionVersionId = data.question.question_version_id;
          this.currentQuestionId = data.question.question_id;
          this.currentTopicId = data.question.topic_id;
          this.currentExamId = data.question.exam_type_id;
          this.currentQuestionWasAnswered = data.question.was_answered === true;

          ui.renderQuestion(this.quizContainer, data.question, data.answers);

          // Aktualizacja paska postępu
          this.updateProgressBar(data.pool_progress);
          this.revealQuestionOnNarrowScreen();
        }
      } else {
        if (result.status === 'gatekeeper') {
          Toast.show(result.error, 'info');
        } else {
          Toast.show(result.error, 'error');
        }
      }
    } catch (error) {
      Toast.show('Wystąpił błąd sieci.', 'error');
    } finally {
      clearTimeout(this.loaderTimer);
    }
  }

  /**
   * Centralny handler dla kliknięć wewnątrz kontenera quizu (delegacja zdarzeń).
   * @private
   * @param {Event} event - Obiekt zdarzenia 'click'.
   */
  handleInteraction(event) {
    // Krok 1: Sprawdź, czy celem kliknięcia był przycisk zapisu.
    const saveBtn = event.target.closest('.quiz-card__save-btn');
    if (saveBtn) {
      this.handleSaveToggle(saveBtn);
      return;
    }

    // Krok 2: Sprawdź, czy celem kliknięcia była etykieta odpowiedzi.
    const clickedLabel = event.target.closest('.quiz-card__answer');

    if (clickedLabel) {
      // Krok 2: Sprawdź, czy na to pytanie już odpowiedziano (ochrona przed wielokrotnym sprawdzaniem).
      const answersContainer = clickedLabel.closest('.quiz-card__answers');
      if (answersContainer?.dataset.answered === 'true') {
        return; // Ignoruj kliknięcie.
      }
      // Krok 3: Jeśli wszystko się zgadza, przekaż kontrolę do metody sprawdzającej.
      this.checkSelectedAnswer(clickedLabel);
    }
  }

  /**
   * Strzałki w grupie radio domyślnie zaznaczają kolejną odpowiedź (i wywołują
   * click), co w tym trybie od razu by ją sprawdzało. Przenosimy więc tylko
   * fokus - wybór następuje Spacją, jak przy zwykłym polu radio.
   *
   * @param {KeyboardEvent} event
   */
  handleAnswerKeydown(event) {
    const keys = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 };
    const step = keys[event.key];
    if (!step || !(event.target instanceof HTMLInputElement) || event.target.type !== 'radio') return;

    const radios = Array.from(
      event.target.closest('.quiz-card__answers')?.querySelectorAll('input[type="radio"]') || []
    );
    const index = radios.indexOf(event.target);
    if (index === -1) return;

    event.preventDefault();
    radios[(index + step + radios.length) % radios.length].focus();
  }

  /**
   * Naprawiona metoda checkSelectedAnswer
   */
  async checkSelectedAnswer(clickedLabel) {
    const answersContainer = clickedLabel.closest('.quiz-card__answers');
    answersContainer.dataset.answered = 'true';

    // Krok 2: Pobieramy dane. 
    // Rezygnujemy z document.getElementById('question_id_hidden') na rzecz stanu klasy.
    const userAnswerId = clickedLabel.querySelector('input[type="radio"]').value;
    const versionId = this.currentQuestionVersionId; // Używamy zapisanego ID wersji

    if (!versionId) {
      Toast.show('Błąd synchronizacji danych pytania.', 'error');
      return;
    }

    // Krok 3: Wywołaj API (ApiClient prześle to jako question_version_id)
    const result = await api.checkAnswer(versionId, userAnswerId);

    if (result.success) {
      const data = result.data;
      ui.showAnswerFeedback(data.is_correct, data.correct_answer_id, userAnswerId);
      this.advanceProgressAfterAnswer();
      ui.renderActionButtons(
        this.currentExplanation,
        this.startNewQuestion.bind(this),
        {
          examId: this.currentExamId,
          questionId: this.currentQuestionId,
          topicId: this.currentTopicId,
          source: 'single_question_module',
          isCorrect: data.is_correct,
        }
      );
    } else {
      Toast.show(result.error || 'Wystąpił błąd.', 'error');
      delete answersContainer.dataset.answered;
    }
  }

  /**
   * Obsługuje przełączanie stanu zapisania pytania.
   * @private
   * @async
   * @param {HTMLElement} btn - Element przycisku zapisu.
   */
  async handleSaveToggle(btn) {
    saveToggle.toggle(btn);
  }

  /**
   * Aktualizuje wizualnie pasek postępu.
   * @private
   * @param {object} progress - Obiekt z total i answered.
   */
  updateProgressBar(progress) {
    if (!this.progressBar) return;

    const total = this.toSafeCount(progress?.total);
    const answered = Math.min(this.toSafeCount(progress?.answered), total);
    const percent = total > 0 ? this.clamp(Math.round((answered / total) * 100), 0, 100) : 0;

    this.currentProgress = { total, answered };
    this.progressBar.classList.remove('hidden');
    this.progressBar.style.display = 'block';

    if (this.progressCounts) this.progressCounts.textContent = `${answered} / ${total}`;
    if (this.progressPercent) this.progressPercent.textContent = `${percent}%`;
    if (this.progressFill) this.progressFill.style.width = `${percent}%`;
  }

  /**
   * Na tablecie/telefonie (układ jednokolumnowy, panel tematów NAD pytaniem)
   * przewija do nowego pytania. Bez tego po "Rozpocznij naukę" pytanie
   * ładowało się poza ekranem, a po "Następne pytanie" użytkownik zostawał
   * na dole poprzedniego wyjaśnienia.
   * @private
   */
  revealQuestionOnNarrowScreen() {
    if (!window.matchMedia('(max-width: 992px)').matches) return;

    const progressVisible = this.progressBar && !this.progressBar.classList.contains('hidden');
    const anchor = progressVisible ? this.progressBar : this.quizContainer;
    const rect = anchor.getBoundingClientRect();
    const isScrollingUp = rect.top < 0;
    // Navbar chowa się przy przewijaniu w dół i wraca przy przewijaniu w górę -
    // miejsce na niego rezerwujemy tylko w tym drugim przypadku.
    const navOffset = isScrollingUp ? (document.querySelector('.main-nav')?.offsetHeight || 0) : 0;

    // Początek pytania jest już w górnej części ekranu - nie ruszamy widoku.
    if (!isScrollingUp && rect.top < window.innerHeight * 0.4) return;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    window.scrollTo({
      top: Math.max(0, window.scrollY + rect.top - navOffset - 12),
      behavior: reduceMotion ? 'auto' : 'smooth',
    });
  }

  /**
   * Aktualizuje progres po zapisaniu pierwszej odpowiedzi na bieżące pytanie.
   * @private
   */
  advanceProgressAfterAnswer() {
    if (this.currentQuestionWasAnswered || this.progressUpdatedForCurrentQuestion) return;

    this.progressUpdatedForCurrentQuestion = true;
    this.currentQuestionWasAnswered = true;
    this.updateProgressBar({
      total: this.currentProgress.total,
      answered: this.currentProgress.answered + 1,
    });
  }

  /**
   * Zwraca bezpieczną, nieujemną liczbę całkowitą.
   * @private
   * @param {unknown} value
   * @returns {number}
   */
  toSafeCount(value) {
    const number = Number(value);
    return Number.isFinite(number) ? Math.max(0, Math.floor(number)) : 0;
  }

  /**
   * Ogranicza wartość liczbową do podanego zakresu.
   * @private
   * @param {number} value
   * @param {number} min
   * @param {number} max
   * @returns {number}
   */
  clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
  }
}

/**
 * --- Punkt Startowy Aplikacji ---
 *
 * Po pełnym załadowaniu struktury DOM, tworzy nową instancję klasy Quiz,
 * inicjując w ten sposób całą logikę aplikacji.
 */
export function initSingleQuestionPage() {
  return new SingleQuestionPage();
}
