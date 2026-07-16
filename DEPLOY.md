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
5. Sprawdza, czy istnieje produkcyjny `.env`.
6. Tworzy backup aplikacji.
7. Wykonuje `git fetch --prune origin`.
8. Przelacza sie na galaz produkcyjna.
9. Wykonuje `git pull --ff-only origin <branch>`.
10. Uruchamia `composer install`, jesli istnieje `composer.json`.
11. Uruchamia `npm ci && npm run build:css`, jesli istnieje `package.json`.
12. Opcjonalnie uruchamia migracje.
13. Opcjonalnie czysci cache i ustawia uprawnienia.
14. Opcjonalnie wylacza maintenance mode.
15. Zapisuje log deployu.

Deploy zatrzymuje sie przy pierwszym bledzie.

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
npm ci && npm run build:css
```

3. Jesli problem dotyczy plikow, mozesz rozpakowac ostatni backup z katalogu `backup.dir`.

Uwaga: rollback bazy danych zalezy od konkretnej migracji. Przed wlaczeniem automatycznych migracji na produkcji upewnij sie, ze masz kopie bazy i znasz procedure cofania zmian.
