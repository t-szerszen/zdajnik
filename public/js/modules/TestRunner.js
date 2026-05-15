/**
 * @file TestRunner.js
 * @module TestRunner
 * @description
 * Moduł dostarcza reużywalną klasę `TestRunner`, która jest generycznym
 * "silnikiem" do przeprowadzania testów. Został zaprojektowany zgodnie z zasadą
 * wstrzykiwania zależności (Dependency Injection), co oznacza, że nie wie nic
 * o otaczającym go świecie – wszystkie potrzebne elementy (przyciski, kontenery)
 * otrzymuje z zewnątrz. To czyni go ekstremalnie elastycznym i łatwym do
 * testowania.
 *
 * ## Wymagania i Zależności
 *
 * 1. Struktura HTML: Komponent wymaga istnienia w DOM elementów, których ID
 *    zostaną przekazane w obiekcie konfiguracyjnym do konstruktora.
 * 2. Biblioteki zewnętrzne: Skrypt polega na globalnie dostępnych
 *    bibliotekach `marked` i `DOMPurify` (do parsowania i czyszczenia HTML).
 * 3. API Client: Importuje moduł `ApiClient` do komunikacji z backendem.
 *
 * @version 1.2.0
 * @author Tobiasz Szerszeń
 */

import api from './ApiClient.js';
import { escapeHTML } from '../utils/sanitize.js';
import ExplanationToggler from '../components/ExplanationToggler.js';
import saveToggle from '../components/SaveToggleHandler.js';

export class TestRunner {
  /**
   * Inicjalizuje instancję TestRunnera, wstrzykując wszystkie zależności.
   *
   * @constructs TestRunner
   * @param {object} config - Obiekt konfiguracyjny z zależnościami.
   * @param {HTMLElement} config.testView - Główny kontener widoku testu.
   * @param {HTMLElement} config.questionsWrapper - Kontener do renderowania pytań.
   * @param {HTMLElement} config.resultsScreen - Ekran wyników.
   * @param {HTMLElement} config.finishBtn - Przycisk "Zakończ test".
   * @param {HTMLElement} config.resultsDetailsContainer - Kontener na szczegółowe wyniki.
   * @param {boolean} [config.isFullExam=false] - Flaga, czy to pełny egzamin.
   */
  constructor(config) {
    // Krok 1: Przypisz wstrzyknięte zależności (elementy UI) do właściwości klasy.
    this.testView = config.testView;
    this.questionsWrapper = config.questionsWrapper;
    this.resultsScreen = config.resultsScreen;
    this.finishBtn = config.finishBtn;
    this.resultsDetailsContainer = config.resultsDetailsContainer;

    // Krok 2: Zainicjuj wewnętrzny stan komponentu.
    this.examCode = config.examCode || 'INF.03'; // Fallback to avoid breaking legacy calls
    this.isFullExam = config.isFullExam || false;
    this.questionsData = [];
    this.timerInterval = null;
    this.timeSpent = 0;
    this.isTestInProgress = false;

    // Bindowanie metod
    this.handleBeforeUnload = this.handleBeforeUnload.bind(this);
    this.finishTest = this.finishTest.bind(this);
    this.handleAnswerSelection = this.handleAnswerSelection.bind(this);
    this.togglePause = this.togglePause.bind(this);
    this.resumeTest = this.resumeTest.bind(this);
    this.lastScrollY = window.scrollY;
    this.handleSyncScroll = this.handleSyncScroll.bind(this);

    // Krok 4: Pierwsza próba wyszukania elementów
    this.refreshUIElements();
  }

  /**
   * Odświeża referencje do elementów UI (pomocne, gdy DOM jest wstrzykiwany dynamicznie).
   * @public
   */
  refreshUIElements() {
    const root = document;
    const view = this.testView || document;

    this.progressBar = view.querySelector('#test-progress-bar') || root.getElementById('test-progress-bar');
    this.progressText = view.querySelector('#test-progress-text') || root.getElementById('test-progress-text');
    this.timerEl = view.querySelector('#timer') || root.getElementById('timer');
    this.testHeader = view.querySelector('.test-header') || root.querySelector('.test-header');
    this.pauseBtn = view.querySelector('#pause-test-btn') || root.getElementById('pause-test-btn') || view.querySelector('.test-header__pause-btn');
    this.resumeBtn = view.querySelector('#resume-test-btn') || root.getElementById('resume-test-btn');
    this.pauseOverlay = view.querySelector('#pause-overlay') || root.getElementById('pause-overlay');

    // Elementy w nawigacji (Sync)
    this.navTimer = root.getElementById('nav-timer');
    this.navProgressBar = root.getElementById('nav-test-progress-bar');
    this.navProgressText = root.getElementById('nav-test-progress-text');
    this.navPauseBtn = root.getElementById('nav-pause-btn');
  }

  /**
   * Uruchamia wykonanie testu z podanym zestawem pytań.
   *
   * @param {Array<Object>} questions - Tablica obiektów z danymi pytań.
   */
  run(questions) {
    // Krok 1: Ustaw stan wewnętrzny testu.
    this.questionsData = questions;
    this.isTestInProgress = true;

    // Odświeżamy referencje - kluczowe dla personalizowanego testu, gdzie HTML wchodzi późno
    this.refreshUIElements();

    // Krok 2: Powiąż nasłuchiwacze zdarzeń.
    this.bindListeners();

    // Krok 3: Wyrenderuj pytania na stronie.
    this.renderAllQuestions(this.questionsData);

    // Krok 4: Uaktualnij licznik pytań początkowy
    this.updateProgress();

    // Krok 5: Uruchom zegar z czasem obliczonym na podstawie liczby pytań.
    const duration = this.questionsData.length * 90; // 1.5 minuty na pytanie
    this.startTimer(duration);

    // Krok 6: Synchronizacja widoczności timera w navie
    window.addEventListener('scroll', this.handleSyncScroll, { passive: true });
  }

  /**
   * Synchronizuje widoczność paska nawigacji z pozycją scrolla względem headera testu.
   * @private
   */
  handleSyncScroll() {
    const currentScrollY = window.scrollY;

    if (!this.testHeader || window.innerWidth <= 1024) {
      document.body.classList.remove('test-scrolled-past-header');
      this.lastScrollY = currentScrollY;
      return;
    }

    const headerBottom = this.testHeader.offsetTop + this.testHeader.offsetHeight;
    const isScrollingUp = currentScrollY < this.lastScrollY;
    const isPastHeader = currentScrollY > headerBottom;

    // Bańka włącza się tylko przy jechaniu w górę i byciu poniżej nagłówka
    if (isPastHeader && isScrollingUp) {
      document.body.classList.add('test-scrolled-past-header');
    } else {
      // Przy jeździe w dół lub byciu nad nagłówkiem - chowamy natychmiast
      document.body.classList.remove('test-scrolled-past-header');
    }

    this.lastScrollY = currentScrollY;
  }

  /**
   * Wiąże wszystkie niezbędne nasłuchiwacze zdarzeń.
   * @private
   */
  bindListeners() {
    window.addEventListener('beforeunload', this.handleBeforeUnload);
    this.finishBtn.addEventListener('click', this.finishTest);
    this.questionsWrapper.addEventListener('click', this.handleAnswerSelection);

    if (this.pauseBtn) this.pauseBtn.addEventListener('click', this.togglePause);
    if (this.navPauseBtn) this.navPauseBtn.addEventListener('click', this.togglePause);
    if (this.resumeBtn) this.resumeBtn.addEventListener('click', this.resumeTest);

    this.resultsDetailsContainer.addEventListener('click', (e) => {
      const saveBtn = e.target.closest('.quiz-card__save-btn');
      if (saveBtn) {
        saveToggle.toggle(saveBtn);
      }
    });
  }

  /**
   * Zaktualizowana metoda renderAllQuestions
   */
  renderAllQuestions(questions) {
    this.questionsWrapper.innerHTML = '';

    questions.forEach((qData, index) => {
      // Pobieramy dane z nowymi kluczami
      const { question_id, question_version_id, question_text, image_name } = qData.question;
      const answers = qData.answers;
      let answersHTML = '';

      answers.forEach((answer, answerIndex) => {
        const letter = String.fromCharCode(65 + answerIndex);

        // FIX: Zmiana z .content na .answer_text
        // Dodajemy || '', aby uniknąć błędu marked w razie pustej odpowiedzi
        const unsafeHTML = marked.parse(answer.answer_text || '', { gfm: true, breaks: true });
        const contentHTML = DOMPurify.sanitize(unsafeHTML);

        answersHTML += `
          <label class="quiz-card__answer">
            <input type="radio" name="q_${question_version_id}" value="${answer.id}">
            <span class="quiz-card__answer-prefix">${letter}</span>
            <span class="quiz-card__answer-text">${contentHTML}</span>
          </label>`;
      });

      const questionElement = document.createElement('section');
      questionElement.className = 'quiz-card';
      questionElement.id = `question-${question_id}`; // ID bazowe dla DOM

      const imageHTML = image_name
        ? `<div class="quiz-card__image-container">
             <img src="${window.examlyAppState.baseUrl}/images/questions/${escapeHTML(image_name)}"
                  alt="Ilustracja do pytania" class="quiz-card__image">
           </div>`
        : '';

      questionElement.innerHTML = `
          <header class="quiz-card__header">
            <div class="quiz-card__header-left">
              <span class="quiz-card__question-number">Pytanie ${index + 1} / ${questions.length}</span>
              <span class="quiz-card__id">ID: ${question_id}</span>
            </div>
            <div class="quiz-card__header-actions">
           ${window.examlyAppState.isUserLoggedIn ? `
             <button class="quiz-card__save-btn" data-question-id="${question_id}" title="Zapisz pytanie">
               <i class="${qData.question.is_saved ? 'fas' : 'far'} fa-heart"></i>
             </button>
           ` : ''}
           </div>
         </header>
        <div class="quiz-card__content">
          <p class="quiz-card__question-text">${escapeHTML(question_text)}</p>
          ${imageHTML}
        </div>
        <div class="quiz-card__answers">${answersHTML}</div>
        <div class="quiz-card__actions">
          <div class="quiz-card__button-container"></div>
          <div class="quiz-card__explanation"></div>
        </div>`;

      this.questionsWrapper.appendChild(questionElement);
    });
  }

  async finishTest() {
    this.isTestInProgress = false;
    this.stopTimer();
    window.removeEventListener('beforeunload', this.handleBeforeUnload);

    let correctAnswersCount = 0;
    const topicIdsInTest = new Set();
    const answersDetails = [];
    const userAnswersMap = {}; // DODANE: Mapa do podsumowania

    this.questionsData.forEach((qData) => {
      const { question_version_id, topic_id } = qData.question;
      topicIdsInTest.add(topic_id);

      const selectedInput = this.questionsWrapper.querySelector(`input[name="q_${question_version_id}"]:checked`);
      const selectedAnswerId = selectedInput ? parseInt(selectedInput.value, 10) : null;

      // Zapisujemy wybór do mapy
      userAnswersMap[question_version_id] = selectedAnswerId;

      const correctAnswer = qData.answers.find(a => a.is_correct === 1);
      const isCorrect = selectedAnswerId && correctAnswer && selectedAnswerId === correctAnswer.id;

      if (isCorrect) {
        correctAnswersCount++;
      }

      // ZAWSZE dodajemy do answersDetails - nawet jeśli pominięte (selectedAnswerId = null)
      // Backend wymaga tego do śledzenia pominiętych pytań
      answersDetails.push({
        question_version_id: question_version_id,
        answer_id: selectedAnswerId, // może być null dla pominiętych
        is_correct: isCorrect ? 1 : 0
      });
    });

    const totalQuestions = this.questionsData.length;
    const score = totalQuestions > 0 ? (correctAnswersCount / totalQuestions) * 100 : 0;

    if (window.examlyAppState?.isUserLoggedIn) {
      const attemptPayload = {
        examCode: this.examCode || 'INF.03',
        isFullExam: this.isFullExam,
        correctAnswers: correctAnswersCount,
        totalQuestions: totalQuestions,
        duration: this.timeSpent,
        topicIds: Array.from(topicIdsInTest),
        answers: answersDetails
      };
      await api.saveAttempt(attemptPayload);
    }

    // WAŻNE: Przekazujemy trzeci argument
    this.showResults(score, correctAnswersCount, userAnswersMap);
  }

  /**
   * Buduje i wyświetla ekran wyników ze szczegółowym podsumowaniem.
   */
  /**
  /**
   * Buduje i wyświetla ekran wyników ze szczegółowym podsumowaniem.
   */
  showResults(score, correctCount, userAnswers) {
    // Ukryj widok testu i główny kontener (aby tło review-page działało na pełną stronę)
    this.testView.classList.add('hidden');
    if (this.testView.parentElement) {
      this.testView.parentElement.classList.add('hidden');
    }

    // Dodaj klasę do body dla stylów tła
    document.body.classList.add('review-page');

    // Pokaż ekran wyników
    this.resultsScreen.classList.remove('hidden');

    // Sekcja Podsumowania
    const scoreSummaryContainer = document.getElementById('score-summary');

    const scoreToDisplay = this.isFullExam ? score.toFixed(2) : Math.round(score);
    const scoreForAnimation = Math.round(score);

    const minutes = Math.floor(this.timeSpent / 60);
    const seconds = this.timeSpent % 60;
    const durationString = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;

    const totalQuestions = this.questionsData.length;

    // Data i czas dla "Data podejścia"
    const now = new Date();
    const dateString = now.toLocaleDateString('pl-PL');
    const timeString = now.toLocaleTimeString('pl-PL', { hour: '2-digit', minute: '2-digit' });

    scoreSummaryContainer.innerHTML = `
      <div class="review-page__score-display">
        <div class="score-circle" style="--score: ${scoreForAnimation}">
          <div class="score-circle__percent">${scoreToDisplay}%</div>
        </div>
        <p class="label-text">Twój wynik</p>
      </div>

      <div class="review-page__stats-grid">
        <div class="review-page__stat-box">
          <span class="stat-label">Wynik punktowy</span>
          <span class="stat-value text-gradient">
            <i class="fas fa-bullseye" style="font-size: 0.8em; margin-right: 0.25rem;"></i>
            ${correctCount} / ${totalQuestions}
          </span>
        </div>
        <div class="review-page__stat-box">
          <span class="stat-label">Czas pracy</span>
          <span class="stat-value">
            <i class="fas fa-clock" style="font-size: 0.8em; margin-right: 0.25rem;"></i>
            ${durationString}
          </span>
        </div>
        <div class="review-page__stat-box">
             <span class="stat-label">Data podejścia</span>
             <span class="stat-value" style="font-size: 1.1rem; line-height: 1.2;">
               <i class="fas fa-calendar-alt" style="font-size: 0.8em; margin-right: 0.25rem; color: var(--accent);"></i>
               ${dateString}
               <small style="display: block; opacity: 0.6; font-size: 0.8rem; margin-top: 2px;">
                 Godzina ${timeString}
               </small>
             </span>
        </div>
      </div>`;

    // Sekcja detali pytań (szczegóły są w htmlu statycznie, ale content dynamicznie)
    this.resultsDetailsContainer.innerHTML = '';

    this.questionsData.forEach((qData, index) => {
      const { question_id, question_version_id, question_text, image_name, explanation } = qData.question;
      const userAnswerId = userAnswers[question_version_id]; // Odczyt z mapy
      const answers = qData.answers;

      const card = document.createElement('article');
      card.className = 'quiz-card';
      card.id = `result-q-${question_id}`;
      card.style.animationDelay = `${index * 0.1}s`;

      // Dodaj nagłówek z numerem pytania i statusem
      const isUserCorrectHeader = answers.find(a => a.id === userAnswerId)?.is_correct === 1;
      let badgeHTML = '';
      if (userAnswerId === null || userAnswerId === undefined) {
        badgeHTML = '<span class="status-badge status-badge--skipped"><i class="fas fa-share"></i> Pominięte</span>';
      } else if (isUserCorrectHeader) {
        badgeHTML = '<span class="status-badge status-badge--success"><i class="fas fa-check"></i> Poprawne</span>';
      } else {
        badgeHTML = '<span class="status-badge status-badge--error"><i class="fas fa-times"></i> Błąd</span>';
      }

      let answersHTML = '';
      answers.forEach((ans, idx) => {
        const letter = String.fromCharCode(65 + idx);

        // LOGIKA KOLORYSTYKI ZGODNA Z REVIEW PAGE
        const isCorrect = ans.is_correct === 1;
        const isSelected = (userAnswerId === ans.id);

        let statusClass = '';
        if (isCorrect) {
          if (userAnswerId !== null && userAnswerId !== undefined) {
            statusClass = 'quiz-card__answer--correct';
          } else {
            statusClass = 'quiz-card__answer--skipped';
          }
        } else if (isSelected && !isCorrect) {
          statusClass = 'quiz-card__answer--incorrect';
        }

        const contentHTML = DOMPurify.sanitize(marked.parse(ans.answer_text || ''));

        // Input checked tylko wizualnie, żeby widzieć co się kliknęło (opcjonalne, bo mamy kolory)
        // Ale dodajmy dla pewności
        const checkedAttr = isSelected ? 'checked' : '';
        // const checkedAttr = isSelected ? 'checked' : '';

        answersHTML += `
          <div class="quiz-card__answer ${statusClass}">
            <span class="quiz-card__answer-prefix">${letter}</span>
            <span class="quiz-card__answer-text">${contentHTML}</span>
          </div>`;
      });

      card.innerHTML = `
          <header class="quiz-card__header">
              <div class="quiz-card__header-left">
                <span class="quiz-card__question-number">Pytanie ${index + 1}</span>
                <span class="quiz-card__id">ID: ${question_id}</span>
              </div>
              <div class="quiz-card__header-actions">
               ${window.examlyAppState.isUserLoggedIn ? `
                 <button class="quiz-card__save-btn" data-question-id="${question_id}" title="Zapisz pytanie">
                   <i class="${qData.question.is_saved ? 'fas' : 'far'} fa-heart"></i>
                 </button>
               ` : ''}
               ${badgeHTML}
             </div>
         </header>
        <div class="quiz-card__content">
          <p class="quiz-card__question-text">${escapeHTML(question_text)}</p>
          ${image_name ? `<div class="quiz-card__image-container"><img src="${window.examlyAppState.baseUrl}/images/questions/${escapeHTML(image_name)}" class="quiz-card__image"></div>` : ''}
        </div>
        <div class="quiz-card__answers" data-answered="true">${answersHTML}</div>
        <div class="quiz-card__actions"></div>
      `;

      if (explanation && explanation.trim()) {
        this.renderExplanationResult(card, explanation);
      }

      this.resultsDetailsContainer.appendChild(card);
    });

    window.scrollTo(0, 0);
  }

  /**
   * Renderuje sekcję wyjaśnienia w wynikach (z działającym przyciskiem).
   */
  renderExplanationResult(card, explanation) {
    const actionsContainer = card.querySelector('.quiz-card__actions');

    const btnContainer = document.createElement('div');
    btnContainer.className = 'quiz-card__button-container';

    const explContainer = document.createElement('div');
    // UID dla togglera
    const explId = 'expl-' + Math.random().toString(36).substr(2, 9);
    explContainer.id = explId;
    explContainer.className = 'quiz-card__explanation';

    const explanationHTML = DOMPurify.sanitize(marked.parse(explanation || ''));
    explContainer.innerHTML = `
        <div class="explanation-title">
            <i class="fas fa-magic"></i> Wyjaśnienie
        </div>
        <div class="explanation-inner">
            ${explanationHTML}
        </div>
    `;

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn--secondary btn--small toggle-explanation-btn';
    button.innerHTML = '<i class="fas fa-lightbulb"></i> Pokaż wyjaśnienie';
    button.dataset.target = explId;

    // Używamy helpera do obsługi logiki
    ExplanationToggler.attach(button, explContainer);

    btnContainer.appendChild(button);
    actionsContainer.appendChild(btnContainer);
    actionsContainer.appendChild(explContainer);
  }

  /**
   * Ostrzega użytkownika przed niezapisanymi zmianami przy próbie zamknięcia strony.
   * @private
   * @param {Event} event - Obiekt zdarzenia `beforeunload`.
   */
  handleBeforeUnload(event) {
    if (this.isTestInProgress) {
      event.preventDefault();
      event.returnValue = '';
    }
  }

  /**
   * Dodaje wizualny feedback (klasę CSS) po kliknięciu na odpowiedź.
   * @private
   * @param {Event} event - Obiekt zdarzenia `click`.
   */
  handleAnswerSelection(event) {
    // Krok 1: Przycisk zapisu
    const saveBtn = event.target.closest('.quiz-card__save-btn');
    if (saveBtn) {
      this.handleSaveToggle(saveBtn);
      return;
    }

    const answerLabel = event.target.closest('.quiz-card__answer');
    if (!answerLabel) return;

    const currentQuestionCard = answerLabel.closest('.quiz-card');
    if (currentQuestionCard) {
      // Usuń zaznaczenie z poprzednio wybranej odpowiedzi w ramach TEGO pytania.
      currentQuestionCard
        .querySelectorAll('.quiz-card__answer.selected')
        .forEach((label) => label.classList.remove('selected'));
      // Dodaj zaznaczenie do klikniętej odpowiedzi.
      answerLabel.classList.add('selected');

      // Uaktualnij progres po zaznaczeniu odpowiedzi
      this.updateProgress();
    }
  }

  /**
   * Aktualizuje pasek postępu i licznik pytań.
   * @private
   */
  updateProgress() {
    // Jeśli elementy nagle zniknęły lub ich nie było - spróbuj znaleźć ponownie
    if (!this.progressText || !this.progressBar) {
      this.refreshUIElements();
    }

    if (!this.questionsWrapper) return;

    const total = this.questionsData.length;

    // Zliczamy unikalne karty pytań, które mają zaznaczoną chociaż jedną odpowiedź (.selected)
    const cards = this.questionsWrapper.querySelectorAll('.quiz-card');
    const count = Array.from(cards).filter(card => card.querySelector('.selected')).length;
    const percent = total > 0 ? (count / total) * 100 : 0;

    // Aktualizacja tekstu (X / Y)
    if (this.progressText) {
      this.progressText.innerHTML = `Postęp: <span>${count} / ${total}</span>`;
    }

    // Aktualizacja szerokości paska (0-100%)
    if (this.progressBar) {
      this.progressBar.style.width = `${percent}%`;
    }

    // Dodatkowo aktualizujemy pasek w nawigacji jeśli istnieje
    if (this.navProgressBar) {
      this.navProgressBar.style.width = `${percent}%`;
    }
    if (this.navProgressText) {
      this.navProgressText.textContent = `${count} / ${total}`;
    }
  }

  /**
   * Uruchamia i zarządza licznikiem czasu.
   * @private
   * @param {number} duration - Czas testu w sekundach.
   */
  startTimer(duration) {
    this.timeLeft = duration;
    this.runTimer();
  }

  /**
   * Logika pętli zegara.
   * @private
   */
  runTimer() {
    if (this.timerInterval) clearInterval(this.timerInterval);

    this.timerInterval = setInterval(() => {
      this.timeSpent++;
      this.timeLeft--;

      if (this.timeLeft < 0) this.timeLeft = 0;

      const minutes = Math.floor(this.timeLeft / 60);
      const seconds = this.timeLeft % 60;

      if (this.timerEl) {
        const timeStr = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        this.timerEl.textContent = timeStr;
        if (this.navTimer) this.navTimer.textContent = timeStr;

        if (this.timeLeft <= 300) {
          this.timerEl.classList.add('timer--warning');
          if (this.navTimer) this.navTimer.classList.add('timer--warning');
        } else {
          this.timerEl.classList.remove('timer--warning');
          if (this.navTimer) this.navTimer.classList.remove('timer--warning');
        }
      }

      if (this.timeLeft <= 0) {
        this.finishTest();
      }
    }, 1000);
  }

  /**
   * Zatrzymuje zegar.
   * @private
   */
  stopTimer() {
    if (this.timerInterval) {
      clearInterval(this.timerInterval);
      this.timerInterval = null;
    }
  }

  /**
   * Przełącza stan pauzy (On/Off).
   * @public
   */
  togglePause() {
    if (!this.isTestInProgress) return;

    this.stopTimer();
    if (this.pauseOverlay) this.pauseOverlay.classList.remove('hidden');
    if (this.questionsWrapper) this.questionsWrapper.classList.add('questions-wrapper--blurred');
  }

  /**
   * Wznawia test po pauzie.
   * @public
   */
  resumeTest() {
    if (!this.isTestInProgress) return;

    this.runTimer();
    if (this.pauseOverlay) this.pauseOverlay.classList.add('hidden');
    if (this.questionsWrapper) this.questionsWrapper.classList.remove('questions-wrapper--blurred');
  }

  /**
   * Obsługuje przełączanie stanu zapisania pytania.
   * @private
   * @async
   * @param {HTMLElement} btn - Element przycisku zapisu.
   */
  handleSaveToggle(btn) {
    saveToggle.toggle(btn);
  }
}