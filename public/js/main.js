const CHART_JS_URL = 'https://cdn.jsdelivr.net/npm/chart.js';
const CACHE_QUERY = new URL(import.meta.url).search || `?v=${Date.now()}`;

function modulePath(path) {
  return `${path}${CACHE_QUERY}`;
}

function onReady(callback) {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', callback, { once: true });
    return;
  }

  callback();
}

function has(selector) {
  return Boolean(document.querySelector(selector));
}

function loadScriptOnce(src, globalName) {
  if (globalName && window[globalName]) {
    return Promise.resolve();
  }

  const existing = document.querySelector(`script[src="${src}"]`);
  if (existing) {
    return new Promise((resolve) => {
      existing.addEventListener('load', resolve, { once: true });
      existing.addEventListener('error', resolve, { once: true });
      window.setTimeout(resolve, 2000);
      if (globalName && window[globalName]) resolve();
    });
  }

  return new Promise((resolve) => {
    const script = document.createElement('script');
    script.src = src;
    script.async = true;
    script.addEventListener('load', resolve, { once: true });
    script.addEventListener('error', resolve, { once: true });
    document.head.appendChild(script);
  });
}

async function initHomePage() {
  if (!has('[data-hero-rotating-word], [data-feature-modal]')) return;
  const { initHomePage: init } = await import(modulePath('./features/home/index.js'));
  init();
}

async function initQuestionsListPage() {
  if (!has('.questions-page__results[data-filterable]')) return;
  const { default: FilterableList } = await import(modulePath('./utils/FilterableList.js'));
  new FilterableList({
    formSelector: '.questions-filters',
    resultsSelector: '.questions-page__results',
    debouncedFieldSelectors: ['#q'],
    immediateFieldSelectors: ['#temat'],
    pageParam: 'strona',
  }).init();
}

async function initReviewPage() {
  // Uwaga: `.review-page__questions` jest też używana na test.php/personalized_test.php
  // (wspólny styl kart wyników), ale te strony mają WŁASNY handler serduszka
  // wbudowany w TestRunner.js. Ograniczenie do body.review-page (exam_review.php,
  // question_review.php) zapobiega podwójnemu podpięciu SaveToggleHandler na tym
  // samym przycisku (dwa równoległe zapytania toggle-saved przy jednym kliknięciu).
  if (!document.body.classList.contains('review-page')) return;

  const reviewContainer = document.querySelector('.review-page__questions, .review-page__main');
  if (!reviewContainer) return;

  const [{ initExplanationToggler }, { default: saveToggle }] = await Promise.all([
    import(modulePath('./components/ExplanationToggler.js')),
    import(modulePath('./components/SaveToggleHandler.js')),
  ]);

  initExplanationToggler();
  saveToggle.init(reviewContainer.classList.contains('review-page__questions')
    ? '.review-page__questions'
    : '.review-page__main');
}

async function initSingleQuestionPage() {
  if (!has('#quiz-single-question')) return;

  const [
    { initTopicFormEnhancer },
    { initPremiumOptionsTutorial },
    { initOneQuestionTutorial },
    { initSingleQuestionPage: init },
  ] = await Promise.all([
    import(modulePath('./components/TopicFormEnhancer.js')),
    import(modulePath('./components/PremiumOptionsTutorial.js')),
    import(modulePath('./features/one-question/OneQuestionTutorial.js')),
    import(modulePath('./features/one-question/index.js')),
  ]);

  initTopicFormEnhancer();
  const urlParams = new URLSearchParams(window.location.search);
  initPremiumOptionsTutorial({
    mode: 'one-question',
    forceOpen: urlParams.get('tutorial') === '1',
    guestPreview: urlParams.get('guestPreview') === '1',
    // Samouczek stronicowy (OneQuestionTutorial) obejmuje już sekcję filtrów jako jeden z kroków,
    // więc ten poradnik nie startuje automatycznie - dostępny tylko ręcznie przez przycisk "?".
    autoStart: false,
  });
  initOneQuestionTutorial();
  init();
}

async function initPersonalizedTestPage() {
  if (!has('#personalized-test-page')) return;

  const [
    { initSliderEnhancer },
    { initPersonalizedTestPage: init },
    { initTopicFormEnhancer },
    { initPremiumOptionsTutorial },
    { initPersonalizedTestTutorial },
  ] = await Promise.all([
    import(modulePath('./components/SliderEnhancer.js')),
    import(modulePath('./features/personalized-test/index.js')),
    import(modulePath('./components/TopicFormEnhancer.js')),
    import(modulePath('./components/PremiumOptionsTutorial.js')),
    import(modulePath('./features/personalized-test/PersonalizedTestTutorial.js')),
  ]);

  initSliderEnhancer();
  init();
  initTopicFormEnhancer();
  initPremiumOptionsTutorial({
    mode: 'personalized-test',
    // Samouczek stronicowy (PersonalizedTestTutorial) obejmuje już sekcję filtrów jako
    // jeden z kroków, więc ten poradnik nie startuje automatycznie - tylko ręcznie przez "?".
    autoStart: false,
  });
  initPersonalizedTestTutorial();
}

async function initSheetsPages() {
  if (!has('.sheets-page')) return;

  const { initSheetsListPage, initSheetDetailPage } = await import(modulePath('./features/sheets/index.js'));
  initSheetsListPage();
  initSheetDetailPage();
}

async function initFullTestPage() {
  if (!has('#test-container')) return;
  const { initFullTestPage: init } = await import(modulePath('./features/test/index.js'));
  init();
}

async function initSettingsPage() {
  if (!has('#settings-accordion, #avatar-uploader, #changeEmailBtn')) return;

  const [{ initSettingsAccordion }, { default: CountdownButton }, { default: AvatarUploader }] = await Promise.all([
    import(modulePath('./components/SettingsAccordion.js')),
    import(modulePath('./components/CountdownButton.js')),
    import(modulePath('./features/settings/AvatarUploader.js')),
  ]);

  initSettingsAccordion();
  new CountdownButton('changeEmailBtn');
  new AvatarUploader();
}

async function initCountdownButtons() {
  const buttonIds = ['submitBtn', 'resendButton'];
  if (!buttonIds.some((id) => document.getElementById(id))) return;

  const { default: CountdownButton } = await import(modulePath('./components/CountdownButton.js'));
  buttonIds.forEach((id) => new CountdownButton(id));
}

async function initStatisticsPage() {
  if (!has('[data-default-stats-exam], #efficiencyTrendChart, [data-stats-tutorial-root]')) return;

  const [
    { initDefaultStatsExamPreference },
    { initEfficiencyChart },
    { initStatisticsTutorial },
  ] = await Promise.all([
    import(modulePath('./features/statistics/DefaultStatsExamPreference.js')),
    import(modulePath('./components/EfficiencyChart.js')),
    import(modulePath('./features/statistics/StatisticsTutorial.js')),
  ]);

  initDefaultStatsExamPreference();
  if (has('#efficiencyTrendChart')) {
    await loadScriptOnce(CHART_JS_URL, 'Chart');
    if (window.Chart) initEfficiencyChart();
  }
  initStatisticsTutorial();
}

async function initAdminAnalytics() {
  if (!has('[data-analytics-root]')) return;

  await loadScriptOnce(CHART_JS_URL, 'Chart');
  const { initAdminAnalytics: init } = await import(modulePath('./components/AdminAnalytics.js'));
  init();
}

async function initAdminTables() {
  if (!has('[data-admin-table-root]')) return;

  const { initAdminResourceTables } = await import(modulePath('./features/admin/AdminResourceTable.js'));
  initAdminResourceTables();
}

async function initAdminSparklines() {
  if (!has('[data-sparkline-metric]')) return;

  await loadScriptOnce(CHART_JS_URL, 'Chart');
  const { initDashboardSparklines } = await import(modulePath('./features/admin/DashboardSparklines.js'));
  initDashboardSparklines();
}

async function initPageModules() {
  const results = await Promise.allSettled([
    initHomePage(),
    initQuestionsListPage(),
    initReviewPage(),
    initSingleQuestionPage(),
    initPersonalizedTestPage(),
    initSheetsPages(),
    initFullTestPage(),
    initSettingsPage(),
    initCountdownButtons(),
    initStatisticsPage(),
    initAdminAnalytics(),
    initAdminTables(),
    initAdminSparklines(),
  ]);

  results.forEach((result) => {
    if (result.status === 'rejected') {
      console.error('Zdajnik: błąd inicjalizacji modułu strony', result.reason);
    }
  });
}

async function initGlobalModules() {
  const [
    { initNavbarHandler },
    { initThemeSwitcher },
    { initPasswordToggles },
    { registerServiceWorker },
    { initCookieConsent },
  ] = await Promise.all([
    import(modulePath('./components/NavbarHandler.js')),
    import(modulePath('./components/ThemeSwitcher.js')),
    import(modulePath('./components/show-password-toggle.js')),
    import(modulePath('./modules/ServiceWorkerRegistration.js')),
    import(modulePath('./components/CookieConsent.js')),
  ]);

  initNavbarHandler();
  initThemeSwitcher();
  initPasswordToggles();
  registerServiceWorker();
  initCookieConsent();
}

/**
 * Wysyła wyniki egzaminów, których nie udało się zapisać z powodu braku sieci
 * (kolejka w localStorage, zob. ApiClient.saveAttemptWithRetry()).
 */
async function initPendingAttemptsSync() {
  if (!window.zdajnikAppState?.isUserLoggedIn) return;

  let hasPending = false;
  try {
    hasPending = Boolean(localStorage.getItem('zdajnik_pending_attempts'));
  } catch (e) {
    return;
  }
  if (!hasPending) return;

  const [{ default: api }, { default: Toast }] = await Promise.all([
    import(modulePath('./modules/ApiClient.js')),
    import(modulePath('./components/Toast.js')),
  ]);

  const flush = async () => {
    const saved = await api.flushPendingAttempts();
    if (saved > 0) {
      Toast.show(saved === 1 ? 'Zapisano zaległy wynik egzaminu.' : `Zapisano zaległe wyniki egzaminów: ${saved}.`, 'success');
    }
  };

  window.addEventListener('online', flush);
  await flush();
}

onReady(() => {
  initPendingAttemptsSync().catch((error) => {
    console.error('Zdajnik: błąd synchronizacji zaległych wyników', error);
  });
  initGlobalModules().catch((error) => {
    console.error('Zdajnik: błąd inicjalizacji modułów globalnych', error);
  });
  initPageModules().catch((error) => {
    console.error('Zdajnik: błąd inicjalizacji modułów strony', error);
  });
});
