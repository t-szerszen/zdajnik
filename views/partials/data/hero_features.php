<?php

/**
 * Wspólna lista funkcji Zdajnika, prezentowana w komponencie `partials/feature_grid.php`.
 * Współdzielona między stroną główną (home.php) i stroną o projekcie (about_project.php),
 * żeby oba miejsca opisywały te same możliwości w ten sam sposób (DRY).
 *
 * @return array<int, array{icon:string,title:string,description:string,details?:string,status?:string}>
 */

return [
  [
    'icon' => 'fas fa-database',
    'title' => 'Baza pytań',
    'description' => 'Setki pytań prosto z arkuszy egzaminacyjnych i wymagań CKE, poukładane według kwalifikacji i działów.',
    'details' => 'Pytania są pogrupowane po kwalifikacji i po dziale tematycznym, więc możesz ćwiczyć całą kwalifikację naraz albo wejść tylko w temat, który akurat Cię interesuje lub idzie Ci najsłabiej.',
  ],
  [
    'icon' => 'fas fa-circle-question',
    'title' => 'Wyjaśnienia do pytań',
    'description' => 'Po odpowiedzi widzisz nie tylko "źle", ale i "dlaczego?".',
    'details' => 'Zamiast zgadywać za drugim razem, od razu wiesz, co przeoczyłeś/aś. Świetnie się to sprawdza przy pytaniach, które wracają jako Twoje częste błędy. Ponadto nie musisz samemu googlować wyjaśnienia do pytania.',
  ],
  [
    'icon' => 'fas fa-filter',
    'title' => 'Filtry nauki',
    'description' => '5 trybów, które same dobierają Ci pytania: nieodkryte, słabe punkty, powtórki, ostatnie błędy lub zapisane.',
    'details' => 'Włączasz jeden filtr naraz - np. "Słabe punkty" pokaże pytania, w których wypadasz poniżej 60%, a "Ostatnie błędy" wróci do tego, co ostatnio zawaliłeś/aś. Wszystko po to by zmaksymalizować efekty Twojej nauki.',
  ],
  [
    'icon' => 'fas fa-bookmark',
    'title' => 'Zapisane pytania',
    'description' => 'Kliknij serduszko przy trudnym pytaniu i wróć do niego, kiedy będziesz mieć chwilę na powtórkę.',
    'details' => 'Zapisane pytania trafiają na osobną listę widoczną w statystykach i jako osobny filtr nauki - dzięki temu nie musisz szukać po omacku tego jednego zadania, które Cię zaskoczyło.',
  ],
  [
    'icon' => 'fas fa-chart-simple',
    'title' => 'Statystyki postępów',
    'description' => 'Zobacz, ile materiału już ogarniasz, gdzie masz luki i czy Twój wynik realnie rośnie z testu na test.',
    'details' => 'Panel pokazuje opanowanie materiału po działach, trend skuteczności na wykresie oraz listę Twoich krytycznych błędów - czyli dokładnie to, co warto powtórzyć w pierwszej kolejności przed egzaminem.',
  ],
  [
    'icon' => 'fas fa-pen-to-square',
    'title' => 'Tryby nauki',
    'description' => 'Masz 5 minut? Rozwiąż jedno pytanie. Masz godzinę? Zrób pełny egzamin próbny na czas.',
    'details' => 'Wybierz to, co pasuje do Twojego czasu: pojedyncze pytanie do szybkiej powtórki, personalizowany test złożony z wybranych działów albo pełny egzamin próbny - 40 pytań, 60 minut, dokładnie jak na prawdziwym egzaminie.',
  ],
  [
    'icon' => 'fas fa-magnifying-glass',
    'title' => 'Wyszukiwarka pytań',
    'description' => 'Pamiętasz fragment pytania albo jego ID? Wpisz go i od razu je znajdziesz.',
    'details' => 'Szukasz po treści, numerze ID, kwalifikacji albo temacie. Przydaje się gdy kojarzysz dane pytanie, ale pamiętasz skrawek jego treści.',
  ],
  [
    'icon' => 'fas fa-file-lines',
    'title' => 'Arkusze praktyczne',
    'description' => 'Zadania praktyczne do samodzielnego rozwiązania przy komputerze - w stylu części praktycznej egzaminu.',
    'status' => 'Wkrótce',
  ],
  [
    'icon' => 'fas fa-graduation-cap',
    'title' => 'Kursy',
    'description' => 'Kursy wideo, które krok po kroku przeprowadzą Cię przez materiał z Twojej kwalifikacji.',
    'status' => 'Wkrótce',
  ],
];
