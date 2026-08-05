# Deploy Zdajnika

Ten projekt nie uzywa Laravela. Deploy opiera sie na prostym runnerze PHP (`deploy.php`), konfiguracji w `deploy.config.php` i polaczeniu SSH do serwera produkcyjnego.

## Struktura projektu istotna dla deployu

- PHP: autoload przez Composer, zaleznosci w `composer.json`.
- Frontend: SCSS budowany komenda `npm run build:css`.
- Publiczny katalog aplikacji: `public/`, z front controllerem `public/index.php`.
- Migracje: pliki SQL w stylu dbmate w `db/migrations/`, ale bez lokalnego wrappera w `composer.json`.
- Plik srodowiskowy: `.env`, ignorowany przez Git i nie powinien byc nadpisywany deployem.
- Uploady uzytkownikow: `public/images/questions/uploads`, runner deployu nie usuwa tego katalogu.

## Konfiguracja

Najwazniejsze ustawienia sa w `deploy.config.php`:

- `ssh.host`, `ssh.user`, `ssh.port` - dane polaczenia SSH.
- `app.path` - katalog aplikacji na serwerze.
- `app.production_branch` - galaz produkcyjna, domyslnie `main`.
- `commands.pre_deploy` i `commands.post_deploy` - dodatkowe komendy.
- `maintenance.enabled` oraz komendy maintenance - domyslnie wylaczone, bo aplikacja nie ma wbudowanego trybu maintenance.
- `env.path` - sciezka do produkcyjnego `.env`, domyslnie `.env` w katalogu aplikacji.
- `backup` - ustawienia archiwum aplikacji przed deployem.
- `db_backup` - kopie zapasowe bazy danych (dump przy deployu + codzienny cron) - patrz sekcja "Kopie zapasowe bazy danych" nizej.
- `commands.frontend.build_locally` - czy CSS budowac lokalnie i wyslac przez scp (domyslnie tak) czy zdalnie na serwerze - patrz krok 13 w "Co robi deploy".
- `logs.dir` - lokalny katalog logow deployu.

Wartosci mozna nadpisywac zmiennymi srodowiskowymi, np.:

```bash
DEPLOY_HOST=example.com DEPLOY_USER=deploy DEPLOY_PATH=/home/deploy/zdajnik php deploy.php --dry-run
```

## Reczny deploy

Najpierw sprawdz plan bez wykonywania komend:

```bash
php deploy.php --dry-run
```

Wlasciwy deploy:

```bash
php deploy.php
```

Runner pokazuje podsumowanie konfiguracji i wymaga wpisania `deploy`, chyba ze uruchomisz go z `--yes` w kontrolowanym srodowisku CI.

## Co robi deploy

Runner wykonuje kroki w tej kolejnosci:

1. Sprawdza konfiguracje.
2. Laczy sie z serwerem przez SSH.
3. Przechodzi do katalogu aplikacji.
4. Opcjonalnie wlacza maintenance mode.
5. Opcjonalnie uruchamia niestandardowe komendy z `commands.pre_deploy` (pusta lista domyslnie).
6. Sprawdza, czy istnieje produkcyjny `.env`.
7. Tworzy backup aplikacji.
8. Tworzy dump bazy danych (opcjonalnie) i rejestruje/odswieza codzienny cron do backupu bazy (opcjonalnie) - patrz "Kopie zapasowe bazy danych" nizej.
9. Wykonuje `git fetch --prune origin`.
10. Przelacza sie na galaz produkcyjna.
11. Wykonuje `git pull --ff-only origin <branch>`.
12. Uruchamia `composer install`, jesli istnieje `composer.json`.
13. Buduje CSS (`npm ci && npm run build:css`) i wysyla gotowy plik przez `scp`, jesli istnieje `package.json` - domyslnie **lokalnie** (na maszynie odpalajacej `deploy.php`, w CI to runner GitHub Actions), nie na serwerze. Powod: npm-owy `sass` (dart-sass) nie publikuje binarek dla FreeBSD, wiec na hostingach typu mydevil zdalne budowanie CSS po prostu sie wywala (odpala jakis inny, systemowy `sass`). Steruje tym `commands.frontend.build_locally` w `deploy.config.php` - ustaw `false`, jesli Twoj hosting ma dzialajacy `sass` i wolisz budowac zdalnie jak dawniej.
14. Opcjonalnie uruchamia migracje.
15. Opcjonalnie czysci cache i ustawia uprawnienia.
16. Opcjonalnie uruchamia niestandardowe komendy z `commands.post_deploy` (pusta lista domyslnie).
17. Opcjonalnie wylacza maintenance mode.
18. Zapisuje log deployu (logowane jest w praktyce na biezaco przez caly przebieg, nie tylko na koncu).

Deploy zatrzymuje sie przy pierwszym bledzie.

## Kopie zapasowe bazy danych

Oprócz backupu plików aplikacji (tar przed każdym deployem), runner umie też robić kopie
zapasowe bazy danych. Dwa niezależne mechanizmy, oba sterowane sekcją `db_backup` w
`deploy.config.php`:

1. **Dump przed każdym deployem** — krok "Backup database" wykonuje się tuż przed
   `git pull`/migracjami, jako siatka bezpieczeństwa gdyby coś poszło nie tak.
2. **Niezależny, codzienny dump przez cron** — przy pierwszym (i każdym kolejnym) deployu
   runner sam dopisuje wpis do crontaba użytkownika SSH (rozpoznawany po znaczniku w
   komentarzu, więc kolejne deploye go nie duplikują, tylko podmieniają). Domyślnie
   `0 3 * * *` (3:00 w nocy).

Dane połączenia z bazą są czytane bezpośrednio z produkcyjnego `.env` (`DB_HOST`, `DB_NAME`,
`DB_USER`, `DB_PASS`) — **te wartości nie mogą być owinięte w cudzysłowy** w `.env` (tak jak
już są w `.env.example`), bo runner celowo nie robi `source .env` (hasło mogłoby zawierać
znaki specjalne dla basha), tylko czyta te klucze jako czysty tekst.

Dumpy trafiają jako `<backup.dir>/db/<app>-db-<timestamp>.sql.gz` (gzip, `mysqldump
--single-transaction --quick`, bezpieczne dla InnoDB bez blokowania tabel), z automatyczną
rotacją — pliki starsze niż `db_backup.retention_days` (domyślnie 14 dni) są usuwane przy
każdym uruchomieniu.

Zmienne środowiskowe (opcjonalne, wszystkie mają sensowne domyślne wartości):

- `DEPLOY_DB_BACKUP_ENABLED` — domyślnie włączone.
- `DEPLOY_DB_BACKUP_DIR` — domyślnie `<DEPLOY_BACKUP_DIR>/db`.
- `DEPLOY_DB_BACKUP_RETENTION_DAYS` — domyślnie `14`.
- `DEPLOY_DB_BACKUP_CRON` — domyślnie `0 3 * * *`.
- `DEPLOY_DB_BACKUP_REGISTER_CRON` — domyślnie włączone; ustaw na `false`, jeśli wolisz sam
  skonfigurować cron w panelu hostingu zamiast pozwolić runnerowi zarządzać crontabem konta SSH.

Wymaga, żeby na serwerze były dostępne `mysqldump`, `gzip`, `find`, `xargs` i `crontab` (jeśli
`register_cron` jest włączone) — na produkcyjnym hostingu (mydevil.net, FreeBSD) to sprawdzone
i działające, ale na innym hostingu warto zweryfikować przez `php deploy.php --dry-run`, a docelowo pierwszym prawdziwym
deployem i ręcznym sprawdzeniem `crontab -l` na serwerze.

## Migracje

Repo ma migracje SQL w `db/migrations`, ale sam projekt nie zawiera komendy migracyjnej w Composerze. Dlatego krok migracji jest domyslnie wylaczony.

Po zainstalowaniu `dbmate` na produkcji mozesz wlaczyc migracje:

```bash
DEPLOY_RUN_MIGRATIONS=true php deploy.php
```

Albo ustaw w `deploy.config.php`:

```php
'migrations' => [
    'enabled' => true,
    'command' => 'dbmate --migrations-dir db/migrations up',
],
```

**Zanim to włączysz:** `db/migrations/20260805120000_seed.sql` zawiera pełny zrzut (`TRUNCATE`
realnych tabel z pytaniami + `INSERT` ze stałymi `id`) bazy pytań z lokalnego kontenera dev w
momencie wygenerowania - to plik do (od)tworzenia lokalnej/dev bazy, nie do uruchamiania na
produkcji: nadpisałby realne dane produkcyjne starszym zrzutem z dev i nadpisał ich `id`. `dbmate`
nie ma opcji pomijania pojedynczego pliku migracji, więc trzeba to rozwiązać zanim automatyczne
migracje dotkną prawdziwej bazy - np. usuwając/przenosząc `seed.sql` poza `db/migrations/` albo
weryfikując ręcznie każdy pierwszy przebieg. Przy pierwszym stawianiu produkcji migracje zostały
uruchomione ręcznie, z pominięciem tego pliku (patrz historia deployu / notatki z wdrożenia).

## GitHub Actions

Workflow znajduje sie w `.github/workflows/deploy.yml`. Uruchamia sie po pushu do `main` oraz recznie przez `workflow_dispatch`, ale deploy wykona sie tylko wtedy, gdy konfiguracja na to pozwala.

Dodaj w GitHub:

Secrets:

- `DEPLOY_SSH_PRIVATE_KEY` - prywatny klucz SSH bez hasla albo z obsluga przez runner.
- `DEPLOY_HOST` - host serwera.
- `DEPLOY_USER` - uzytkownik SSH.
- `DEPLOY_PORT` - port SSH, zwykle `22`.
- `DEPLOY_PATH` - katalog aplikacji na serwerze.
- `DEPLOY_BACKUP_DIR` - katalog backupow na serwerze.

Variables:

- `DEPLOY_ENABLED=true` - wlacza automatyczny deploy z GitHub Actions.

Opcjonalnie:

- `DEPLOY_BRANCH=main`
- `DEPLOY_RUN_MIGRATIONS=true`
- `DEPLOY_MAINTENANCE_ENABLED=true`
- `DEPLOY_DB_BACKUP_ENABLED=false` - wylacza backup bazy (domyslnie wlaczony).
- `DEPLOY_DB_BACKUP_RETENTION_DAYS=14`
- `DEPLOY_DB_BACKUP_CRON="0 3 * * *"`
- `DEPLOY_DB_BACKUP_REGISTER_CRON=false` - jesli wolisz reczna konfiguracje crona w panelu hostingu.

## Bezpieczne przechowywanie `.env`

Nie commituj `.env` i nie kopiuj go przez deploy. Plik `.env` powinien istnieć tylko na serwerze produkcyjnym, poza historia Gita. Runner jedynie sprawdza, czy plik istnieje pod sciezka `env.path`.

Jezeli zmieniasz dane produkcyjne, zrob to bezposrednio na serwerze albo przez bezpieczny secret manager hostingu. Nie dodawaj hasel do `deploy.config.php`, workflow ani dokumentacji.

## `--dry-run`

`--dry-run` pokazuje konfiguracje i liste komend, ktore zostalyby wykonane, ale niczego nie uruchamia przez SSH:

```bash
php deploy.php --dry-run
```

To dobry pierwszy test po zmianie `deploy.config.php`.

## Cofniecie deployu

Najprostszy rollback:

1. Sprawdz poprzedni commit:

```bash
git log --oneline -5
```

2. Na serwerze przejdz do katalogu aplikacji i wroc do wybranego commita:

```bash
cd /sciezka/do/aplikacji
git checkout main
git reset --hard <poprzedni_commit>
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
```

3. Zbuduj CSS **lokalnie** (NIE na serwerze — dart-sass nie ma binarek dla FreeBSD, na mydevil
   `npm run build:css` uruchomione zdalnie sie wywali, patrz krok 13 w "Co robi deploy") i wyslij
   gotowy plik:

```bash
npm ci && npm run build:css
scp public/scss/main.css user@host:/sciezka/do/aplikacji/public/scss/main.css
```

4. Jesli problem dotyczy plikow, mozesz rozpakowac ostatni backup z katalogu `backup.dir`.

Uwaga: rollback bazy danych zalezy od konkretnej migracji. Przed wlaczeniem automatycznych migracji na produkcji upewnij sie, ze masz kopie bazy i znasz procedure cofania zmian.
