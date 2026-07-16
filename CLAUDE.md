# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Zdajnik — a Polish e-learning web app (`Aplikacja e-learningowa do kwalifikacji INF.03`) helping Polish technical-school students (technikum) prepare for vocational exams. Plain PHP (no framework — explicitly not Laravel), Composer autoloading, MySQL, vanilla SCSS frontend. Code comments and docblocks throughout the codebase are written in Polish; follow that convention when editing existing files.

## Commands

- `npm run build:css` — compiles `public/scss/main.scss` → `public/scss/main.css` (compressed, no source map). Run after any SCSS change.
- `composer install` — install PHP dependencies (PSR-4 autoload root: `App\` → `app/`).
- `docker-compose up` — local dev stack: `app` (PHP 8.2 + Apache, port 8080) and `db` (MySQL 8.0, port 3306). App container mounts the repo live at `/var/www/html`.
- No PHP test suite or linter is configured in this repo.

### Database migrations

SQL migration files live in `db/migrations/` in dbmate-style naming (`<timestamp>_<name>.sql`), but there is no local migration runner wired into Composer/npm. Migrations are applied via `dbmate` directly against the target database if installed; there's no in-repo migrate command.

### Deploy

Deployment is a custom PHP runner (`deploy.php`), not a CI/CD framework — see `DEPLOY.md` for full detail. Key points:
- `php deploy.php --dry-run` — show the plan without touching the server.
- `php deploy.php` — connects over SSH, backs up, `git fetch`/`pull --ff-only`, `composer install`, `npm ci && npm run build:css`, then optionally runs migrations.
- Config lives in `deploy.config.php`, overridable via `DEPLOY_*` env vars.
- GitHub Actions workflow at `.github/workflows/deploy.yml` triggers on push to `main` / manual dispatch, gated by the `DEPLOY_ENABLED` repo variable.
- Never touch production `.env` from tooling — it lives only on the server, outside git history.

## Architecture

Custom MVC, no framework. Request flow:

1. `public/index.php` is the sole front controller. It loads Composer's autoloader, `Dotenv` (`.env`), then `config.php` (defines `BASE_URL`, `DB_HOST`, mail constants, `BASE_PATH`), starts the PHP session, and hands off to `App\Core\Router`.
2. `App\Core\Router` (`app/Core/Router.php`) holds all routes in one place — `registerRoutes()` is the single source of truth for every endpoint (auth, admin panel, quiz pages, JSON API, search). Routes use `{param}` placeholders compiled to named regex groups. Handlers are `'Controller@method'` strings resolved via `App\Controllers\{Controller}` and invoked with `call_user_func_array`. Unmatched routes and controller/method errors both funnel through `ErrorController` (404/500/403).
3. Controllers live in `app/Controllers/`, all extending `BaseController` (`app/Controllers/BaseController.php`), which provides:
   - Session-derived `$isUserLoggedIn` / `$currentUser` state, auto-populated in the constructor.
   - `renderView($viewName, $data)` — extracts `$data` and includes `views/{$viewName}.php` directly (no template engine); always injects `$isUserLoggedIn`/`$currentUser`.
   - Access guards: `requireGuest()`, `requireAuth()`, `requireAdmin()` (the latter re-fetches the user from DB to check current role/ban status, refreshing `$_SESSION['user']`).
   - Built-in, best-effort analytics tracking (`trackAnalyticsEvent`, automatic `page_view` tracking on GET requests, excluding `admin/*` and `errors/*` views) — traffic source is inferred from `Referer` host (`organic-search`, `social`, `internal`, `direct`, or the bare host).
4. `App\Core\Database` (`app/Core/Database.php`) is a singleton PDO wrapper (`getInstance()`) using prepared statements exclusively (`fetchAll`, `fetchColumn`, `fetch`, `execute`, `lastInsertId`, plus transaction helpers). All DB errors are caught internally and logged via `error_log`, with safe fallback returns (`[]`/`false`) rather than propagating exceptions — keep that contract when adding methods.
5. `app/Models/` — data-access classes per entity (`User`, `UserModel`, `Question`, `Answer`, `Attempt`, `SavedQuestion`, `UserProgress`). `User` is the session-stored entity object; `UserModel` performs the DB lookups/mutations.
6. `app/Services/` — business logic layer, kept out of controllers: `AuthService` (registration/login/session orchestration, delegates validation to `Validator` and analytics to `AnalyticsService`), `Validator` (input validation, password strength, Have I Been Pwned integration, email uniqueness), `CsrfService` (session-bound CSRF token issue/verify — use `csrf_token()`/`csrf_field()` helpers in forms), `TokenService` (email verification / password reset / account deletion tokens), `Mailer` (PHPMailer wrapper), `AnalyticsService`/`StatisticsService`/`DynamicDataService`, and `Admin*Service` classes backing the admin panel (`AdminAuditService`, `AdminDashboardService`, `AdminExamService`, `AdminQuestionService`, `AdminUserService`).
7. Views in `views/*.php` are plain PHP templates (no Blade/Twig) rendered by `include`; `views/admin/` holds the admin dashboard UI, `views/partials/` and `views/admin/partials/` hold shared fragments (navbar, footer, flash messages, nav).

### Routing map (functional groups, see `Router::registerRoutes`)

- Public/auth: home, about, contact, privacy policy, login, register, email verification, Google OAuth (`auth/google*`), password reset.
- User area: `statystyki` (dashboard), `ustawienia` (settings incl. account deletion/anonymization/email change confirmations), `historia`.
- Admin panel (`admin/*`, guarded by `requireAdmin()`): users, exams, questions (each with add/edit/activate/deactivate), analytics (+ `analytics/data` and `analytics/export` JSON/export endpoints), audit logs.
- Learning/quiz pages: `jedno-pytanie/{code}`, `personalizowany-test/{code}`, `test/{code}`.
- Review: `test-pytanie/{questionId}`, `wyniki/{attemptId}`.
- JSON API (`api/*`): question fetch, personalized/full test fetch, answer checking, attempt/progress saving, saved-question toggling, avatar upload, client-side analytics event tracking.
- Search: `search`, `search/topics`.

### Conventions

- PHP files use 2-space indentation (see `.editorconfig`); Prettier is configured for PHP formatting (`@prettier/plugin-php`, single quotes, semicolons, 120 print width) for other assets.
- All DB access goes through `App\Core\Database`/models — no raw PDO in controllers.
- New protected admin endpoints should call `$this->requireAdmin()` at the top of the action, following existing `AdminController` methods.
- SCSS source lives under `public/scss/`; only the compiled `main.css` is what ships — always rebuild via `npm run build:css` after edits, don't hand-edit the compiled CSS.
