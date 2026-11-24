<?php

/**
 * @file update_announcement.php
 * @description
 * Treść ogłoszenia "Co nowego" pokazywanego w hero strony głównej
 * (views/home.php). Przy kolejnej aktualizacji wystarczy podmienić `version`
 * i listę `items` - zmiana `version` sprawia, że kropka "nowe" na przycisku
 * pojawia się ponownie u osób, które widziały poprzednie ogłoszenie.
 * Zwrócenie `null` całkowicie ukrywa ogłoszenie.
 *
 * @version 1.0.0
 * @author Tobiasz Szerszeń
 */

return [
  'version' => '2026-11-24',
  'label' => 'Aktualizacja',
  'teaser' => 'Zobacz, co poprawiliśmy',
  'title' => 'Co nowego w Zdajniku',
  'intro' => 'Nowe wyjaśnienia, formatowanie kodu i wygodniejsza nauka na telefonie.',
  'items' => [
    [
      'icon' => 'fas fa-lightbulb',
      'title' => 'Wyjaśnienia do wszystkich pytań',
      'text' => '947 pytań dostało wyjaśnienie, którego wcześniej nie miało, a 488 istniejących przepisaliśmy na jaśniejsze. Teraz każde z 3513 pytań ma wyjaśnienie.',
    ],
    [
      'icon' => 'fas fa-code',
      'title' => 'Pytania z kodem mają formatowanie',
      'text' => 'W około 700 pytaniach kod jest wyraźnie oznaczony i sformatowany - jak w edytorze - więc łatwiej go przeczytać i porównać odpowiedzi.',
    ],
    [
      'icon' => 'fas fa-flag',
      'title' => 'Zgłoś błąd w pytaniu',
      'text' => 'Widzisz błąd w pytaniu albo wyjaśnieniu? Kliknij flagę na karcie pytania i opisz problem - zgłoszenie trafi prosto do nas.',
    ],
    [
      'icon' => 'fas fa-mobile-screen',
      'title' => 'Wygodniej na telefonie',
      'text' => 'Karta pytania, przegląd odpowiedzi i kafelki wyników są dopasowane do małych ekranów, menu jest lżejsze, a czas i pauza testu są widoczne przez cały egzamin.',
    ],
    [
      'icon' => 'fas fa-wand-magic-sparkles',
      'title' => 'Drobne usprawnienia',
      'text' => 'Spokojniejsza nawigacja przy przewijaniu, szybszy start testu i samouczki, które pokazują dokładnie ten element, o którym mówią.',
    ],
    [
      'icon' => 'fas fa-shield-halved',
      'title' => 'Bezpieczeństwo',
      'text' => 'Wprowadziliśmy poprawki bezpieczeństwa i stabilności.',
    ],
  ],
];
