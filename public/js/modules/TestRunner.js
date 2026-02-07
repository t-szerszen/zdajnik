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

    // Krok 3: Zapewnij prawidłowy kontekst `this` dla metod używanych jako event handlery.
    this.handleBeforeUnload = this.handleBeforeUnload.bind(this);
    this.finishTest = this.finishTest.bind(this);
    this.handleAnswerSelection = this.handleAnswerSelection.bind(this);
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

    // Krok 2: Powiąż nasłuchiwacze zdarzeń.
    this.bindListeners();

    // Krok 3: Wyrenderuj pytania na stronie.
    this.renderAllQuestions(this.questionsData);

    // Krok 4: Uruchom zegar z czasem obliczonym na podstawie liczby pytań.
    const duration = this.questionsData.length * 90; // 1.5 minuty na pytanie
    this.startTimer(duration);
  }

  /**
   * Wiąże wszystkie niezbędne nasłuchiwacze zdarzeń.
   * @private
   */
  bindListeners() {
    window.addEventListener('beforeunload', this.handleBeforeUnload);
    this.finishBtn.addEventListener('click', this.finishTest);
    this.questionsWrapper.addEventListener('click', this.handleAnswerSelection);
  }

  /**
   * Zaktualizowana metoda renderAllQuestions
   */
  renderAllQuestions(questions) {
    this.questionsWrapper.innerHTML = '';

    questions.forEach((qData, index) => {
      // Pobieramy dane z nowymi kluczami
      const { question_id, question_version_id, question_text, image_path } = qData.question;
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
      
      const imageHTML = image_path
        ? `<div class="quiz-card__image-container">
             <img src="/examly/public/images/questions/${escapeHTML(image_path)}"
                  alt="Ilustracja do pytania" class="quiz-card__image">
           </div>`
        : '';

      questionElement.innerHTML = `
        <header class="quiz-card__header">
          <span class="quiz-card__question-number">Pytanie ${index + 1} / ${questions.length}</span>
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
    clearInterval(this.timerInterval);
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
      const { question_id, question_version_id, question_text, image_path, explanation } = qData.question;
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
            <span class="quiz-card__question-number">Pytanie ${index + 1}</span>
            ${badgeHTML}
        </header>
        <div class="quiz-card__content">
          <p class="quiz-card__question-text">${escapeHTML(question_text)}</p>
          ${image_path ? `<div class="quiz-card__image-container"><img src="/examly/public/images/questions/${escapeHTML(image_path)}" class="quiz-card__image"></div>` : ''}
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
    }
  }

  /**
   * Uruchamia i zarządza licznikiem czasu.
   * @private
   * @param {number} duration - Czas testu w sekundach.
   */
  startTimer(duration) {
    let timeLeft = duration;
    const timerEl = document.getElementById('timer');

    this.timerInterval = setInterval(() => {
      // Krok 1: Aktualizuj upływający czas.
      this.timeSpent++;
      timeLeft = duration - this.timeSpent;
      if (timeLeft < 0) timeLeft = 0;

      // Krok 2: Zaktualizuj interfejs użytkownika.
      const minutes = Math.floor(timeLeft / 60);
      const seconds = timeLeft % 60;
      if (timerEl) {
        timerEl.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
      }

      // Krok 3: Jeśli czas się skończył, automatycznie zakończ test.
      if (timeLeft <= 0) {
        this.finishTest();
      }
    }, 1000);
  }
}