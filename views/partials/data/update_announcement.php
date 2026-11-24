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
  'intro' => 'Poprawiliśmy rzeczy, które przeszkadzały w nauce - szczególnie na telefonie.',
  'items' => [
    [
      'icon' => 'fas fa-mobile-screen',
      'title' => 'Wygodniej na telefonie',
      'text' => 'Karta pytania, podgląd i przegląd odpowiedzi oraz kafelki wyników są dopasowane do małych ekranów. Menu jest lżejsze, a samouczki pokazują dokładnie ten element, o którym mówią.',
    ],
    [
      'icon' => 'fas fa-stopwatch',
      'title' => 'Czas i pauza zawsze pod ręką',
      'text' => 'Podczas egzaminu na telefonie licznik czasu i przycisk pauzy są widoczne przez cały test, nie tylko na górze strony.',
    ],
    [
      'icon' => 'fas fa-compass',
      'title' => 'Spokojniejsza nawigacja',
      'text' => 'Górny pasek nie wyskakuje już przy każdym drgnięciu palca i nie zasłania treści podczas przewijania.',
    ],
    [
      'icon' => 'fas fa-layer-group',
      'title' => 'Wybór tematów w trybie jednego pytania',
      'text' => 'Panel tematów działa wygodnie także na telefonie i tablecie.',
    ],
    [
      'icon' => 'fas fa-bolt',
      'title' => 'Szybszy start testu',
      'text' => 'Pytania do egzaminu ładują się szybciej, więc od kliknięcia do pierwszego pytania mija mniej czasu.',
    ],
    [
      'icon' => 'fas fa-user-shield',
      'title' => '30 dni na zmianę zdania',
      'text' => 'Po usunięciu konta możesz je przywrócić, logując się w ciągu 30 dni. Później dane są trwale anonimizowane.',
    ],
    [
      'icon' => 'fas fa-shield-halved',
      'title' => 'Bezpieczeństwo i stabilność',
      'text' => 'Poprawki pod maską, dzięki którym Zdajnik działa stabilniej i bezpieczniej.',
    ],
  ],
];
