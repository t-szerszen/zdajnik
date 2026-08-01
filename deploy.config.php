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
        'branch' => 'main',
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
        'keep_releases' => 5,
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
            'command' => 'npm ci && npm run build:css',
        ],

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
