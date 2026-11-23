<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Zdajnik',
        'path' => getenv('DEPLOY_PATH') ?: '/home/your-user/domains/your-domain/public_html',
        'production_branch' => getenv('DEPLOY_BRANCH') ?: 'main',
        'public_dir' => 'public',
    ],

    'ssh' => [
        'host' => getenv('DEPLOY_HOST') ?: 'example.com',
        'user' => getenv('DEPLOY_USER') ?: 'deploy',
        'port' => (int) (getenv('DEPLOY_PORT') ?: 22),
        'key_path' => getenv('DEPLOY_SSH_KEY_PATH') ?: '',
        'strict_host_key_checking' => getenv('DEPLOY_STRICT_HOST_KEY_CHECKING') ?: 'accept-new',
    ],

    'github_actions' => [
        'enabled' => filter_var(getenv('DEPLOY_GITHUB_ACTIONS_ENABLED') ?: false, FILTER_VALIDATE_BOOL),
    ],

    'maintenance' => [
        'enabled' => filter_var(getenv('DEPLOY_MAINTENANCE_ENABLED') ?: false, FILTER_VALIDATE_BOOL),
        'enable_command' => '',
        'disable_command' => '',
    ],

    'env' => [
        'path' => getenv('DEPLOY_ENV_PATH') ?: '.env',
        'required' => true,
    ],

    'backup' => [
        'enabled' => filter_var(getenv('DEPLOY_BACKUP_ENABLED') ?: true, FILTER_VALIDATE_BOOL),
        'dir' => getenv('DEPLOY_BACKUP_DIR') ?: '/home/your-user/backups/zdajnik',
        // Ile najnowszych archiwów aplikacji zostawiać - starsze są usuwane po każdym
        // backupie (wcześniej nieużywane, przez co archiwa rosły bez końca: 31 szt. / ~700 MB).
        'keep_releases' => max(1, (int) (getenv('DEPLOY_BACKUP_KEEP') ?: 5)),
        'exclude' => [
            './.git',
            './vendor',
            './node_modules',
            './.env',
            './public/images/questions/uploads',
        ],
    ],

    // Kopie zapasowe bazy danych: dump przy każdym deployu (siatka bezpieczeństwa przed
    // migracją) + automatyczna rejestracja crona robiącego niezależny, codzienny dump.
    'db_backup' => [
        'enabled' => filter_var(getenv('DEPLOY_DB_BACKUP_ENABLED') ?: true, FILTER_VALIDATE_BOOL),
        // Pusty = domyślnie podkatalog "db" wewnątrz backup.dir (patrz Deployer::dbBackupDir()).
        'dir' => getenv('DEPLOY_DB_BACKUP_DIR') ?: '',
        'retention_days' => (int) (getenv('DEPLOY_DB_BACKUP_RETENTION_DAYS') ?: 14),
        'register_cron' => filter_var(getenv('DEPLOY_DB_BACKUP_REGISTER_CRON') ?: true, FILTER_VALIDATE_BOOL),
        'cron_schedule' => getenv('DEPLOY_DB_BACKUP_CRON') ?: '0 3 * * *',
    ],

    // Cykliczne zadania aplikacji rejestrowane w crontab serwera przy każdym deployu
    // (idempotentnie - wpis rozpoznawany po znaczniku). Komenda uruchamiana z katalogu aplikacji.
    'scheduled_jobs' => [
        'anonymize_deleted_users' => [
            // RODO: konta usunięte > 30 dni temu muszą zostać zanonimizowane,
            // nawet jeśli nikt inny w tym czasie nie usuwa konta.
            'enabled' => filter_var(getenv('DEPLOY_ANONYMIZE_CRON_ENABLED') ?: true, FILTER_VALIDATE_BOOL),
            'schedule' => getenv('DEPLOY_ANONYMIZE_CRON') ?: '30 3 * * *',
            'command' => 'php scripts/anonymize_deleted_users.php',
        ],
    ],

    'logs' => [
        'dir' => getenv('DEPLOY_LOG_DIR') ?: __DIR__ . '/var/deploy-logs',
    ],

    'commands' => [
        'pre_deploy' => [],

        'composer' => [
            'enabled' => 'auto',
            'command' => 'composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction',
        ],

        'frontend' => [
            'enabled' => 'auto',
            // dart-sass (npm "sass") nie publikuje binarek dla FreeBSD - na hostingach typu
            // mydevil `npm run build:css` uruchomione zdalnie się wywala (odpala jakiś inny,
            // systemowy "sass"). Budujemy więc CSS lokalnie (na maszynie odpalającej deploy.php
            // - w CI to Linux/macOS) i wysyłamy gotowy plik przez scp zamiast budować zdalnie.
            'build_locally' => true,
            'local_build_command' => 'npm ci && npm run build:css',
            'compiled_files' => ['public/scss/main.css', 'public/scss/critical.css'],
            // Zostawione dla build_locally=false / innych hostingów, gdzie zdalny sass działa.
            'command' => 'npm ci && npm run build:css',
        ],

        // UWAGA: dbmate nie ma oficjalnego builda dla FreeBSD (produkcja: mydevil.net) - ta
        // komenda dziś kończy się "command not found" na serwerze i zatrzymuje deploy (zanim
        // cokolwiek ruszy w bazie, ale i tak blokuje release). Nie zostawiać 'enabled' => true /
        // DEPLOY_RUN_MIGRATIONS=true na stałe, dopóki to nie zostanie rozwiązane - patrz sekcja
        // "Migracje" w DEPLOY.md.
        'migrations' => [
            'enabled' => filter_var(getenv('DEPLOY_RUN_MIGRATIONS') ?: false, FILTER_VALIDATE_BOOL),
            'command' => 'dbmate --migrations-dir db/migrations up',
        ],

        'cache' => [
            'enabled' => false,
            'command' => '',
        ],

        'permissions' => [
            'enabled' => false,
            'commands' => [],
        ],

        'post_deploy' => [],
    ],

    'safety' => [
        'require_confirmation' => true,
        'allow_dangerous_commands' => false,
        'protected_paths' => [
            '.env',
            'public/images/questions/uploads',
        ],
    ],
];
