<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Deploy can only be run from CLI.\n");
    exit(1);
}

final class DeployFailed extends RuntimeException
{
}

final class Deployer
{
    private array $config;
    private array $options;
    private string $logFile;
    private bool $maintenanceEnabled = false;

    public function __construct(array $config, array $options)
    {
        $this->config = $config;
        $this->options = $options;
        $this->logFile = $this->prepareLogFile();
    }

    public function run(): int
    {
        $this->log('Deploy started');
        $this->validateConfig();
        $steps = $this->buildSteps();
        $this->validateCommands($steps);
        $this->printSummary($steps);
        $this->confirmIfNeeded();

        try {
            $this->runLocalStep('Check SSH connection', $this->sshCommand('printf "connected:%s\n" "$(hostname)"'));
            $this->runRemoteStep('Enter application directory', 'pwd');

            if ($this->config['maintenance']['enabled']) {
                $this->maintenanceEnabled = true;
                $this->runRemoteStep('Enable maintenance mode', $this->config['maintenance']['enable_command']);
            }

            foreach ($steps as $step) {
                $this->runStep($step);
            }

            if ($this->maintenanceEnabled) {
                $this->runRemoteStep('Disable maintenance mode', $this->config['maintenance']['disable_command']);
                $this->maintenanceEnabled = false;
            }

            $this->log('Deploy finished successfully');
            $this->line("\nDeploy zakonczony poprawnie.");
            $this->line("Log: {$this->logFile}");

            return 0;
        } catch (Throwable $exception) {
            $this->log('Deploy failed: ' . $exception->getMessage());

            if ($this->maintenanceEnabled && !$this->options['dry_run']) {
                $this->line("\nBlad deployu. Probuje wylaczyc maintenance mode...");
                try {
                    $this->runRemoteStep('Disable maintenance mode after failure', $this->config['maintenance']['disable_command']);
                } catch (Throwable $cleanupException) {
                    $this->log('Maintenance cleanup failed: ' . $cleanupException->getMessage());
                    $this->line('Nie udalo sie wylaczyc maintenance mode automatycznie.');
                }
            }

            $this->line("\nDeploy przerwany: " . $exception->getMessage());
            $this->line("Log: {$this->logFile}");

            return 1;
        }
    }

    private function validateConfig(): void
    {
        $required = [
            'app.path' => $this->config['app']['path'] ?? '',
            'app.production_branch' => $this->config['app']['production_branch'] ?? '',
            'ssh.host' => $this->config['ssh']['host'] ?? '',
            'ssh.user' => $this->config['ssh']['user'] ?? '',
            'ssh.port' => $this->config['ssh']['port'] ?? '',
            'env.path' => $this->config['env']['path'] ?? '',
            'logs.dir' => $this->config['logs']['dir'] ?? '',
        ];

        foreach ($required as $key => $value) {
            if ($value === '' || $value === null) {
                throw new DeployFailed("Missing required config value: {$key}");
            }
        }

        if (!$this->options['dry_run'] && in_array($this->config['ssh']['host'], ['example.com', 'your-server.example.com'], true)) {
            throw new DeployFailed('Set DEPLOY_HOST or update ssh.host in deploy.config.php before running a real deploy.');
        }

        if ((int) $this->config['ssh']['port'] <= 0) {
            throw new DeployFailed('ssh.port must be a positive integer.');
        }

        if ($this->config['maintenance']['enabled']) {
            if ($this->blank($this->config['maintenance']['enable_command']) || $this->blank($this->config['maintenance']['disable_command'])) {
                throw new DeployFailed('Maintenance mode is enabled, but enable_command or disable_command is empty.');
            }
        }

        if ($this->config['backup']['enabled'] && $this->blank($this->config['backup']['dir'])) {
            throw new DeployFailed('Backup is enabled, but backup.dir is empty.');
        }

        if ($this->config['db_backup']['enabled'] && $this->blank($this->dbBackupDir())) {
            throw new DeployFailed('DB backup is enabled, but db_backup.dir is empty and backup.dir has no fallback either.');
        }
    }

    private function buildSteps(): array
    {
        $branch = $this->config['app']['production_branch'];
        $envPath = $this->config['env']['path'];

        $steps = [];
        $this->appendConfiguredCommands($steps, 'Pre deploy command', $this->config['commands']['pre_deploy']);

        if ($this->config['env']['required']) {
            $steps[] = [
                'name' => 'Check production .env',
                'command' => '[ -f ' . $this->remoteQuote($envPath) . ' ]',
            ];
        }

        if ($this->config['backup']['enabled']) {
            $steps[] = [
                'name' => 'Create application backup',
                'command' => $this->backupCommand(),
            ];
        }

        if ($this->config['db_backup']['enabled']) {
            $steps[] = [
                'name' => 'Backup database',
                'command' => $this->dbBackupCommand(),
            ];

            if ($this->config['db_backup']['register_cron']) {
                $steps[] = [
                    'name' => 'Register daily DB backup cron job',
                    'command' => $this->registerDbBackupCronCommand(),
                ];
            }
        }

        $steps[] = ['name' => 'Git fetch', 'command' => 'git fetch --prune origin'];
        $steps[] = ['name' => 'Checkout production branch', 'command' => 'git checkout ' . $this->remoteQuote($branch)];
        $steps[] = ['name' => 'Pull latest code', 'command' => 'git pull --ff-only origin ' . $this->remoteQuote($branch)];

        if ($this->commandEnabled($this->config['commands']['composer']['enabled'], 'composer.json')) {
            $steps[] = [
                'name' => 'Install Composer dependencies',
                'command' => $this->config['commands']['composer']['command'],
            ];
        }

        if ($this->commandEnabled($this->config['commands']['frontend']['enabled'], 'package.json')) {
            if (!empty($this->config['commands']['frontend']['build_locally'])) {
                // dart-sass (npm "sass") nie ma binarek dla FreeBSD, wiec na hostingach typu
                // mydevil budowanie CSS zdalnie po prostu sie wywala. Budujemy lokalnie
                // (na maszynie ktora odpala deploy.php - w CI to runner Linux/macOS) i
                // wysylamy gotowy plik przez scp, zamiast odpalac npm/sass na serwerze.
                $steps[] = [
                    'type' => 'local',
                    'name' => 'Build frontend assets (lokalnie)',
                    'local_command' => ['bash', '-lc', $this->config['commands']['frontend']['local_build_command']],
                ];

                foreach ($this->config['commands']['frontend']['compiled_files'] as $file) {
                    $steps[] = [
                        'type' => 'upload',
                        'name' => 'Wyslanie zbudowanego pliku: ' . $file,
                        'local_path' => $file,
                        'remote_path' => $file,
                    ];
                }
            } else {
                $steps[] = [
                    'name' => 'Build frontend assets',
                    'command' => $this->config['commands']['frontend']['command'],
                ];
            }
        }

        if ($this->config['commands']['migrations']['enabled']) {
            $steps[] = [
                'name' => 'Run database migrations',
                'command' => $this->config['commands']['migrations']['command'],
            ];
        }

        if ($this->config['commands']['cache']['enabled']) {
            $steps[] = [
                'name' => 'Clear cache',
                'command' => $this->config['commands']['cache']['command'],
            ];
        }

        if ($this->config['commands']['permissions']['enabled']) {
            $this->appendConfiguredCommands($steps, 'Set permissions', $this->config['commands']['permissions']['commands']);
        }

        $this->appendConfiguredCommands($steps, 'Post deploy command', $this->config['commands']['post_deploy']);

        return $steps;
    }

    private function commandEnabled(mixed $setting, string $localFile): bool
    {
        if ($setting === 'auto') {
            return is_file(__DIR__ . '/' . $localFile);
        }

        return (bool) $setting;
    }

    private function appendConfiguredCommands(array &$steps, string $label, array $commands): void
    {
        foreach ($commands as $index => $command) {
            if (!$this->blank($command)) {
                $steps[] = [
                    'name' => $label . ' #' . ($index + 1),
                    'command' => $command,
                ];
            }
        }
    }

    private function backupCommand(): string
    {
        $backupDir = rtrim($this->config['backup']['dir'], '/');
        $appName = $this->appSlug();
        $excludes = '';

        foreach ($this->config['backup']['exclude'] as $exclude) {
            $excludes .= ' --exclude=' . $this->remoteQuote($exclude);
        }

        // Uwaga: $(date ...) musi zostac zbudowany w zmiennej bashowej PRZED uzyciem,
        // a nie wewnatrz remoteQuote() (pojedynczy cudzyslow wylacza podstawienie polecen).
        return 'BACKUP_DIR=' . $this->remoteQuote($backupDir)
            . ' && mkdir -p "$BACKUP_DIR"'
            . ' && ARCHIVE_FILE="$BACKUP_DIR/' . $appName . '-$(date +%Y%m%d%H%M%S).tar.gz"'
            . ' && tar -czf "$ARCHIVE_FILE"'
            . $excludes
            . ' .';
    }

    private function appSlug(): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9_-]+/', '-', $this->config['app']['name'])) ?: 'app';
    }

    private function dbBackupDir(): string
    {
        $dir = trim((string) ($this->config['db_backup']['dir'] ?? ''));

        if ($dir !== '') {
            return rtrim($dir, '/');
        }

        $appBackupDir = trim((string) ($this->config['backup']['dir'] ?? ''));

        return $appBackupDir === '' ? '' : rtrim($appBackupDir, '/') . '/db';
    }

    /**
     * Dump bazy danych: czyta dane polaczenia z produkcyjnego .env (DB_HOST/DB_NAME/DB_USER/DB_PASS),
     * zrzuca przez mysqldump, gzipuje z timestampem w nazwie, i usuwa zrzuty starsze niz retention_days.
     * Haslo przekazywane przez MYSQL_PWD (nie -p), zeby nie bylo widoczne w `ps aux`.
     *
     * Uwaga: CELOWO nie robimy `source .env` - haslo bazy moze zawierac znaki specjalne dla
     * basha (cudzyslowy, $, srednik), ktore zepsulyby/wypaczylyby zrodlowanie calego pliku
     * jako kodu powloki. Zamiast tego czytamy tylko potrzebne klucze jako czysty tekst przez
     * sed (linia zaczynajaca sie od "KLUCZ=", reszta linii to wartosc - bez interpretacji).
     * Wymaga, zeby DB_HOST/DB_NAME/DB_USER/DB_PASS w .env NIE byly owiniete w cudzyslowy,
     * tak jak juz sa w .env.example.
     */
    private function dbBackupCommand(): string
    {
        $dir = $this->dbBackupDir();
        $envPath = $this->config['env']['path'];
        $appName = $this->appSlug();
        $retentionDays = max(1, (int) $this->config['db_backup']['retention_days']);
        $namePattern = $appName . '-db-*.sql.gz';
        $readVar = static fn (string $key): string => 'sed -n \'s/^' . $key . '=//p\' "$ENV_FILE" | tail -n1';

        return 'ENV_FILE=' . $this->remoteQuote($envPath)
            . ' && DB_BACKUP_DIR=' . $this->remoteQuote($dir)
            . ' && mkdir -p "$DB_BACKUP_DIR"'
            . ' && DB_HOST="$(' . $readVar('DB_HOST') . ')"'
            . ' && DB_NAME="$(' . $readVar('DB_NAME') . ')"'
            . ' && DB_USER="$(' . $readVar('DB_USER') . ')"'
            . ' && DB_PASS="$(' . $readVar('DB_PASS') . ')"'
            . ' && DUMP_FILE="$DB_BACKUP_DIR/' . $appName . '-db-$(date +%Y%m%d%H%M%S).sql.gz"'
            . ' && MYSQL_PWD="$DB_PASS" mysqldump --single-transaction --quick --no-tablespaces -h"$DB_HOST" -u"$DB_USER" "$DB_NAME" | gzip > "$DUMP_FILE"'
            . ' && find "$DB_BACKUP_DIR" -maxdepth 1 -name ' . $this->remoteQuote($namePattern)
            . ' -mtime +' . $retentionDays . ' -print0 | xargs -0 -r rm --';
    }

    /**
     * Idempotentnie dopisuje (lub podmienia) wpis crontab uruchamiajacy dbBackupCommand()
     * niezaleznie od deployow. Wpis rozpoznawany po znaczniku w komentarzu na koncu linii,
     * wiec ponowne deploye nie duplikuja crona.
     */
    private function registerDbBackupCronCommand(): string
    {
        $schedule = trim((string) $this->config['db_backup']['cron_schedule']) ?: '0 3 * * *';
        $marker = 'zdajnik-db-backup';
        $logFile = $this->dbBackupDir() . '/backup.log';

        $innerCommand = 'cd ' . $this->remoteQuote($this->config['app']['path']) . ' && ' . $this->dbBackupCommand();
        $cronCommand = 'bash -lc ' . $this->remoteQuote($innerCommand) . ' >> ' . $this->remoteQuote($logFile) . ' 2>&1';

        // Crontab traktuje niezescapowany "%" jako znak nowej linii / separator stdin,
        // a nasza komenda zawiera "date +%Y%m%d..." - trzeba zescapowac przed zapisem.
        $cronCommand = str_replace('%', '\\%', $cronCommand);
        $cronLine = $schedule . ' ' . $cronCommand . ' # ' . $marker;

        // "|| true" po grep: przy pierwszym uruchomieniu crontab -l jest pusty, a grep -Fv
        // na pustym wejsciu zwraca kod 1 ("brak dopasowan"), co pod set -e (odziedziczonym
        // w tym podshellu z runRemoteStep) przerywaloby cala sekwencje PRZED dojsciem do
        // echo - w efekcie nic nie trafialoby do "crontab -" i krok padalby bez zadnego
        // widocznego komunikatu bledu (odkryte na pierwszym prawdziwym deployu w CI).
        return '(crontab -l 2>/dev/null | grep -Fv ' . $this->remoteQuote($marker) . ' || true; echo ' . $this->remoteQuote($cronLine) . ') | crontab -';
    }

    private function validateCommands(array $steps): void
    {
        $commands = array_column($steps, 'command');

        foreach ($steps as $step) {
            if (($step['type'] ?? 'remote') === 'local') {
                $commands[] = $this->commandForLog($step['local_command']);
            }
        }

        if ($this->config['maintenance']['enabled']) {
            $commands[] = $this->config['maintenance']['enable_command'];
            $commands[] = $this->config['maintenance']['disable_command'];
        }

        foreach ($commands as $command) {
            $this->assertProtectedPathsAreNotOverwritten($command);

            if ($this->isDangerous($command) && (!$this->config['safety']['allow_dangerous_commands'] || !$this->options['allow_dangerous'])) {
                throw new DeployFailed(
                    "Potentially dangerous command blocked: {$command}\n"
                    . "Set safety.allow_dangerous_commands=true and pass --allow-dangerous only if you fully understand it."
                );
            }
        }
    }

    private function assertProtectedPathsAreNotOverwritten(string $command): void
    {
        foreach ($this->config['safety']['protected_paths'] as $path) {
            $escaped = preg_quote($path, '/');
            $patterns = [
                '/>\s*' . $escaped . '(\s|$)/',
                '/\bcp\b.+\s' . $escaped . '(\s|$)/',
                '/\bmv\b.+\s' . $escaped . '(\s|$)/',
                '/\brsync\b.+\s' . $escaped . '(\s|$)/',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $command)) {
                    throw new DeployFailed("Command may overwrite protected path {$path}: {$command}");
                }
            }
        }
    }

    private function isDangerous(string $command): bool
    {
        $patterns = [
            '/\brm\s+-[^\n;|&]*r[^\n;|&]*f\b/',
            '/\bgit\s+reset\s+--hard\b/',
            '/\brsync\b[^\n;|&]*--delete\b/',
            '/\bfind\b[^\n;|&]*-delete\b/',
            '/\bmkfs\b/',
            '/\bdd\b\s+/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $command)) {
                return true;
            }
        }

        return false;
    }

    private function printSummary(array $steps): void
    {
        $this->line('Konfiguracja deployu:');
        $this->line('- aplikacja: ' . $this->config['app']['name']);
        $this->line('- serwer: ' . $this->config['ssh']['user'] . '@' . $this->config['ssh']['host'] . ':' . $this->config['ssh']['port']);
        $this->line('- katalog aplikacji: ' . $this->config['app']['path']);
        $this->line('- galaz produkcyjna: ' . $this->config['app']['production_branch']);
        $this->line('- maintenance mode: ' . ($this->config['maintenance']['enabled'] ? 'wlaczony' : 'wylaczony'));
        $this->line('- backup: ' . ($this->config['backup']['enabled'] ? $this->config['backup']['dir'] : 'wylaczony'));
        $this->line('- backup bazy danych: ' . ($this->config['db_backup']['enabled'] ? $this->dbBackupDir() . ' (retencja ' . $this->config['db_backup']['retention_days'] . ' dni)' : 'wylaczony'));
        $this->line('- log: ' . $this->logFile);
        $this->line('- dry-run: ' . ($this->options['dry_run'] ? 'tak' : 'nie'));
        $this->line("\nKroki:");

        foreach ($steps as $index => $step) {
            $this->line(($index + 1) . '. ' . $step['name']);
            if ($this->options['dry_run']) {
                $this->line('   ' . $this->describeStepCommand($step));
            }
        }

        $this->line('');
    }

    private function describeStepCommand(array $step): string
    {
        $type = $step['type'] ?? 'remote';

        if ($type === 'local') {
            return '[lokalnie] ' . $this->commandForLog($step['local_command']);
        }

        if ($type === 'upload') {
            return '[upload] ' . $step['local_path'] . ' -> ' . $step['remote_path'];
        }

        return $step['command'];
    }

    private function confirmIfNeeded(): void
    {
        if ($this->options['dry_run'] || $this->options['yes'] || !$this->config['safety']['require_confirmation']) {
            return;
        }

        $this->line("Type 'deploy' to continue:");
        $answer = trim((string) fgets(STDIN));

        if ($answer !== 'deploy') {
            throw new DeployFailed('Confirmation was not provided.');
        }
    }

    /**
     * Rozsyla krok do wlasciwego wykonawcy w zaleznosci od 'type':
     * - 'remote' (domyslny): komenda wykonywana przez SSH na serwerze.
     * - 'local': komenda wykonywana lokalnie (np. na runnerze CI, gdzie dart-sass dziala,
     *   w przeciwienstwie do FreeBSD na mydevil - stad budowanie CSS lokalnie zamiast zdalnie).
     * - 'upload': lokalny plik wyslany przez scp do katalogu aplikacji na serwerze.
     */
    private function runStep(array $step): void
    {
        $type = $step['type'] ?? 'remote';

        if ($type === 'local') {
            $this->runLocalStep($step['name'], $step['local_command']);
            return;
        }

        if ($type === 'upload') {
            $this->runLocalStep($step['name'], $this->scpCommand($step['local_path'], $step['remote_path']));
            return;
        }

        $this->runRemoteStep($step['name'], $step['command']);
    }

    private function scpCommand(string $localPath, string $remotePath): array
    {
        $scp = [
            'scp',
            '-P',
            (string) $this->config['ssh']['port'],
            '-o',
            'BatchMode=yes',
            '-o',
            'StrictHostKeyChecking=' . $this->config['ssh']['strict_host_key_checking'],
        ];

        if (!$this->blank($this->config['ssh']['key_path'])) {
            $scp[] = '-i';
            $scp[] = $this->config['ssh']['key_path'];
        }

        // Uwaga: bez remoteQuote() tutaj - ten argument i tak przejdzie przez escapeshellarg()
        // w commandForShell(), wiec dodatkowe cudzyslowy trafilyby do scp jako literalne znaki
        // w nazwie pliku zamiast zostac zdjete przez powloke.
        $remoteAppPath = rtrim($this->config['app']['path'], '/') . '/' . ltrim($remotePath, '/');

        $scp[] = __DIR__ . '/' . ltrim($localPath, '/');
        $scp[] = $this->config['ssh']['user'] . '@' . $this->config['ssh']['host'] . ':' . $remoteAppPath;

        return $scp;
    }

    private function runRemoteStep(string $name, string $command): void
    {
        $remoteCommand = 'set -euo pipefail; cd ' . $this->remoteQuote($this->config['app']['path']) . '; ' . $command;
        $this->runLocalStep($name, $this->sshCommand($remoteCommand));
    }

    private function runLocalStep(string $name, array $command): void
    {
        $this->line('==> ' . $name);
        $this->log('STEP ' . $name);
        $this->log('$ ' . $this->commandForLog($command));

        if ($this->options['dry_run']) {
            $this->line('[dry-run] ' . $this->commandForLog($command));
            return;
        }

        $exitCode = $this->runProcess($command);

        if ($exitCode !== 0) {
            throw new DeployFailed("Step failed: {$name} (exit code {$exitCode})");
        }
    }

    private function runProcess(array $command): int
    {
        $descriptorSpec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($this->commandForShell($command), $descriptorSpec, $pipes);

        if (!is_resource($process)) {
            throw new DeployFailed('Could not start process.');
        }

        foreach ([1, 2] as $pipe) {
            stream_set_blocking($pipes[$pipe], false);
        }

        while (true) {
            $status = proc_get_status($process);

            foreach ([1, 2] as $pipe) {
                $output = stream_get_contents($pipes[$pipe]);
                if ($output !== false && $output !== '') {
                    echo $output;
                    $this->log(rtrim($output));
                }
            }

            if (!$status['running']) {
                break;
            }

            usleep(100000);
        }

        foreach ([1, 2] as $pipe) {
            $output = stream_get_contents($pipes[$pipe]);
            if ($output !== false && $output !== '') {
                echo $output;
                $this->log(rtrim($output));
            }
            fclose($pipes[$pipe]);
        }

        return proc_close($process);
    }

    private function sshCommand(string $remoteCommand): array
    {
        $ssh = [
            'ssh',
            '-p',
            (string) $this->config['ssh']['port'],
            '-o',
            'BatchMode=yes',
            '-o',
            'StrictHostKeyChecking=' . $this->config['ssh']['strict_host_key_checking'],
        ];

        if (!$this->blank($this->config['ssh']['key_path'])) {
            $ssh[] = '-i';
            $ssh[] = $this->config['ssh']['key_path'];
        }

        $ssh[] = $this->config['ssh']['user'] . '@' . $this->config['ssh']['host'];

        // WAZNE: 'bash', '-lc' i komenda MUSZA byc JEDNYM elementem tablicy, nie trzema
        // osobnymi. OpenSSH skleja koncowe argumenty spacjami BEZ ponownego cytowania,
        // wiec przy trzech osobnych elementach zdalna powloka parsuje sklejony string od
        // nowa i gubi oryginalne cudzyslowy z $remoteCommand (np. printf dostaje pusty
        // format i pada z "usage: printf [-v var] format [arguments]"). Budujemy wiec
        // cala zdalna inwokacje jako jeden, samodzielnie poprawnie zacytowany string -
        // wtedy jedyne cytowanie ktore SSH/lokalna powloka moze zdjac to to na zewnatrz
        // (escapeshellarg w commandForShell), a wnetrze (remoteQuote) dociera nietkniete.
        $ssh[] = 'bash -lc ' . $this->remoteQuote($remoteCommand);

        return $ssh;
    }

    private function prepareLogFile(): string
    {
        $logDir = $this->config['logs']['dir'] ?? (__DIR__ . '/var/deploy-logs');

        if (!is_dir($logDir) && !mkdir($logDir, 0755, true) && !is_dir($logDir)) {
            throw new DeployFailed("Could not create log directory: {$logDir}");
        }

        return rtrim($logDir, '/') . '/deploy-' . date('Ymd-His') . '.log';
    }

    private function log(string $message): void
    {
        file_put_contents($this->logFile, '[' . date('c') . '] ' . $message . PHP_EOL, FILE_APPEND);
    }

    private function line(string $message): void
    {
        echo $message . PHP_EOL;
        $this->log($message);
    }

    private function commandForShell(array $command): string
    {
        return implode(' ', array_map('escapeshellarg', $command));
    }

    private function commandForLog(array $command): string
    {
        return implode(' ', array_map(static fn (string $part): string => str_contains($part, ' ') ? escapeshellarg($part) : $part, $command));
    }

    private function remoteQuote(string $value): string
    {
        return "'" . str_replace("'", "'\\''", $value) . "'";
    }

    private function blank(mixed $value): bool
    {
        return trim((string) $value) === '';
    }
}

function loadConfig(string $path): array
{
    if (!is_file($path)) {
        throw new DeployFailed("Config file not found: {$path}");
    }

    $config = require $path;

    if (!is_array($config)) {
        throw new DeployFailed('Config file must return an array.');
    }

    return $config;
}

function parseOptions(array $argv): array
{
    $options = [
        'config' => __DIR__ . '/deploy.config.php',
        'dry_run' => false,
        'yes' => false,
        'allow_dangerous' => false,
        'check_github_actions_enabled' => false,
    ];

    foreach (array_slice($argv, 1) as $argument) {
        if ($argument === '--dry-run') {
            $options['dry_run'] = true;
        } elseif ($argument === '--yes' || $argument === '-y') {
            $options['yes'] = true;
        } elseif ($argument === '--allow-dangerous') {
            $options['allow_dangerous'] = true;
        } elseif ($argument === '--check-github-actions-enabled') {
            $options['check_github_actions_enabled'] = true;
        } elseif (str_starts_with($argument, '--config=')) {
            $options['config'] = substr($argument, strlen('--config='));
        } elseif ($argument === '--help' || $argument === '-h') {
            echo "Usage: php deploy.php [--dry-run] [--yes] [--allow-dangerous] [--config=/path/to/deploy.config.php]\n";
            exit(0);
        } else {
            throw new DeployFailed("Unknown option: {$argument}");
        }
    }

    return $options;
}

try {
    $options = parseOptions($argv);
    $config = loadConfig($options['config']);

    if ($options['check_github_actions_enabled']) {
        echo !empty($config['github_actions']['enabled']) ? "true\n" : "false\n";
        exit(0);
    }

    $deployer = new Deployer($config, $options);
    exit($deployer->run());
} catch (Throwable $exception) {
    fwrite(STDERR, 'Deploy error: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
