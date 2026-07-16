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
                $this->runRemoteStep($step['name'], $step['command']);
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
            $steps[] = [
                'name' => 'Build frontend assets',
                'command' => $this->config['commands']['frontend']['command'],
            ];
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
        $appName = strtolower(preg_replace('/[^a-zA-Z0-9_-]+/', '-', $this->config['app']['name'])) ?: 'app';
        $excludes = '';

        foreach ($this->config['backup']['exclude'] as $exclude) {
            $excludes .= ' --exclude=' . $this->remoteQuote($exclude);
        }

        return 'mkdir -p ' . $this->remoteQuote($backupDir)
            . ' && tar -czf ' . $this->remoteQuote($backupDir . '/' . $appName . '-$(date +%Y%m%d%H%M%S).tar.gz')
            . $excludes
            . ' .';
    }

    private function validateCommands(array $steps): void
    {
        $commands = array_column($steps, 'command');

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
        $this->line('- log: ' . $this->logFile);
        $this->line('- dry-run: ' . ($this->options['dry_run'] ? 'tak' : 'nie'));
        $this->line("\nKroki:");

        foreach ($steps as $index => $step) {
            $this->line(($index + 1) . '. ' . $step['name']);
            if ($this->options['dry_run']) {
                $this->line('   ' . $step['command']);
            }
        }

        $this->line('');
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
        $ssh[] = 'bash';
        $ssh[] = '-lc';
        $ssh[] = $remoteCommand;

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
