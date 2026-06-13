# Admin analytics - checklista weryfikacji

Projekt nie ma obecnie automatycznego zestawu testów PHP ani SQL. Przed wdrożeniem:

1. Uruchom migracje i sprawdź `SHOW INDEX FROM analytics_events`, `attempts`, `saved_questions` oraz `users`.
2. Otwórz `/admin/analytics` jako administrator i potwierdź odpowiedź 403 dla zwykłego użytkownika.
3. Sprawdź zakresy `7d`, `30d`, `90d`, `custom` oraz `all`.
4. Sprawdź każdy filtr osobno i w kombinacji: egzamin, typ testu, provider, `role`, `is_verified`, event i wyszukiwanie.
5. Porównaj liczbę ukończonych testów z ręcznym `COUNT(*)` w `attempts` dla tego samego zakresu.
6. Porównaj średni wynik i czas z `AVG(correct_count * 100 / total_questions)` oraz `AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at))`.
7. Potwierdź, że źródła i ścieżki nie zawierają query stringów, tokenów ani adresów e-mail.
8. Wykonaj logowanie lokalne i Google, a następnie sprawdź eventy `login` oraz provider.
9. Ukończ test z pominiętym pytaniem i sprawdź sekcję drop-off oraz najtrudniejsze pytania.
10. Pobierz eksporty `users`, `exams`, `questions`, `events` i `retention`, a następnie otwórz je w arkuszu kalkulacyjnym.
11. Sprawdź puste zakresy danych i zachowanie wszystkich empty states.
12. Sprawdź panel na desktopie i mobile, w tym poziome przewijanie szerokich tabel.
13. Sprawdź, że `contentQuality` i `userActivity` zawierają `items`, `page`, `perPage`, `total` i `totalPages`.
14. Potwierdź, że eksport eventów nie zawiera `metadata`, haseł, tokenów, ciasteczek ani nagłówków autoryzacyjnych.
15. Wywołaj `/admin/analytics/data` i `/admin/analytics/export` bez sesji administratora; oba endpointy muszą odmówić dostępu.
