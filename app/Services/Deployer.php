<?php

namespace App\Services;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;

class Deployer
{
    public static function isLocal(): bool
    {
        $addr = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!in_array($addr, ['127.0.0.1', '::1'], true)) {
            return false;
        }

        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        return in_array($host, ['localhost', '127.0.0.1', '[::1]'], true);
    }

    /**
     * @return array{ok: bool, message: string, output: string}
     */
    public static function run(array $config, string $sshPassword = ''): array
    {
        if (!self::isLocal()) {
            return [
                'ok'      => false,
                'message' => 'Деплой доступен только с локальной машины',
                'output'  => '',
            ];
        }

        $host = trim((string) ($config['ssh_host'] ?? ''));
        $user = trim((string) ($config['ssh_user'] ?? ''));
        $remote = rtrim(str_replace('\\', '/', (string) ($config['remote_path'] ?? '')), '/');
        $include = $config['include'] ?? [];
        $exclude = $config['exclude'] ?? [];
        $siteOwner = trim((string) ($config['site_owner'] ?? 'sportdecor_k_usr'));
        $identity = (string) ($config['ssh_identity'] ?? '');

        if ($host === '' || $user === '' || $remote === '') {
            return [
                'ok'      => false,
                'message' => 'Не заполнен app/config/deploy.php (host / user / path)',
                'output'  => '',
            ];
        }

        if ($identity !== '' && str_starts_with($identity, '~/')) {
            $home = getenv('HOME') ?: (string) ($_SERVER['HOME'] ?? '');
            if ($home !== '') {
                $identity = $home . substr($identity, 1);
            }
        }

        if ($identity === '' || !is_file($identity)) {
            $hint = $identity === ''
                ? 'Укажи ssh_identity в app/config/deploy.php'
                : 'Файл не найден: ' . $identity;
            return [
                'ok'      => false,
                'message' => 'Нет SSH-ключа. ' . $hint,
                'output'  => '',
            ];
        }

        $root = defined('ROOT') ? ROOT : dirname(__DIR__, 2);
        self::autoloadPhpseclib($root);

        if (!class_exists(SFTP::class)) {
            return [
                'ok'      => false,
                'message' => 'Не найден lib/phpseclib',
                'output'  => '',
            ];
        }

        $log = [];
        $prevLimit = ini_get('max_execution_time');
        @set_time_limit(300);

        $tarPath = $root . DIRECTORY_SEPARATOR . '.deploy-upload.tar';
        @unlink($tarPath);

        try {
            $log[] = "Pack: {$root}";
            self::buildTar($root, $include, $exclude, $tarPath, $log);

            $log[] = "Connect: {$user}@{$host} (key)";
            $key = PublicKeyLoader::load(file_get_contents($identity));
            $sftp = new SFTP($host);
            $sftp->setTimeout(90);
            if (!$sftp->login($user, $key)) {
                $err = trim((string) $sftp->getLastError());
                $log[] = 'Login failed' . ($err !== '' ? ": {$err}" : '');
                return [
                    'ok'      => false,
                    'message' => 'SSH-ключ не принят для ' . $user . '@' . $host
                        . '. Проверь ssh_identity или выполни scripts/install-deploy-key.sh на сервере'
                        . ($err !== '' ? ' (' . $err . ')' : ''),
                    'output'  => self::joinLog($log, ''),
                ];
            }

            $home = trim((string) $sftp->exec('printf %s "$HOME"'));
            if ($home === '' || !str_starts_with($home, '/')) {
                $home = $user === 'root' ? '/root' : '/home/' . $user;
            }
            $log[] = 'HOME: ' . $home;
            $log[] = 'Target: ' . $remote;

            $exists = trim((string) $sftp->exec(
                'if [ -d ' . self::shellQuote($remote) . ' ]; then echo YES; else echo NO; fi'
            ));
            if ($exists !== 'YES') {
                return [
                    'ok'      => false,
                    'message' => 'Папка сайта не найдена: ' . $remote,
                    'output'  => self::joinLog($log, ''),
                ];
            }

            $remoteTar = ($user === 'root' ? $remote : $home) . '/.deploy-upload.tar';
            $log[] = 'Upload → ' . $remoteTar;
            if (!$sftp->put($remoteTar, $tarPath, SFTP::SOURCE_LOCAL_FILE)) {
                // если в сайт не пускает — в HOME
                $remoteTar = $home . '/.deploy-upload.tar';
                $log[] = 'Retry upload → ' . $remoteTar;
                if (!$sftp->put($remoteTar, $tarPath, SFTP::SOURCE_LOCAL_FILE)) {
                    return [
                        'ok'      => false,
                        'message' => 'Не удалось загрузить архив',
                        'output'  => self::joinLog($log, '', (string) $sftp->getLastSFTPError()),
                    ];
                }
            }
            $log[] = 'Upload OK (' . number_format(filesize($tarPath) / 1024, 1) . ' KB)';

            $isRoot = ($user === 'root')
                || trim((string) $sftp->exec('id -u')) === '0';

            if ($isRoot) {
                $inner = 'set -e; tar -xf ' . $remoteTar . ' -C ' . $remote
                    . '; rm -f ' . $remoteTar
                    . '; chown -R ' . $siteOwner . ':' . $siteOwner . ' ' . $remote
                    . '; chmod -R u=rwX,g=rX,o=rX ' . $remote . '/public'
                    . '; echo DEPLOY_OK';
                $cmd = 'bash -lc ' . self::shellQuote($inner) . ' 2>&1; ec=$?; '
                    . 'rm -f ' . self::shellQuote($remoteTar) . ' 2>/dev/null || true; '
                    . 'if [ $ec -ne 0 ]; then echo DEPLOY_FAIL:$ec; fi';
                $log[] = 'Extract as root → ' . $remote;
            } else {
                $inner = 'set -e; tar -xf ' . $remoteTar . ' -C ' . $remote
                    . '; rm -f ' . $remoteTar
                    . '; chown -R ' . $siteOwner . ':sh ' . $remote
                    . '; chmod -R g+rwX ' . $remote
                    . '; chmod -R u=rwX,g=rX,o=rX ' . $remote . '/public'
                    . '; echo DEPLOY_OK';
                $cmd = 'sudo -n bash -lc ' . self::shellQuote($inner) . ' 2>&1; ec=$?; '
                    . 'rm -f ' . self::shellQuote($remoteTar) . ' 2>/dev/null || true; '
                    . 'if [ $ec -ne 0 ]; then echo DEPLOY_FAIL:$ec; fi';
                $log[] = 'Extract via sudo -n → ' . $remote;
            }

            $out = trim((string) $sftp->exec($cmd));
            $log[] = $out !== '' ? $out : '(no output)';

            if (!str_contains($out, 'DEPLOY_OK')) {
                return [
                    'ok'      => false,
                    'message' => 'Распаковка на сервере не удалась',
                    'output'  => self::joinLog($log, '', self::quickDiag($sftp, $remote)),
                ];
            }

            $log[] = 'Deploy OK';
            return [
                'ok'      => true,
                'message' => 'Код отправлен: ' . $user . '@' . $host . ' → ' . $remote,
                'output'  => self::joinLog($log, ''),
            ];
        } catch (\Throwable $e) {
            $log[] = $e->getMessage();
            return [
                'ok'      => false,
                'message' => 'Деплой не удался: ' . $e->getMessage(),
                'output'  => self::joinLog($log, ''),
            ];
        } finally {
            @unlink($tarPath);
            @set_time_limit((int) $prevLimit);
        }
    }

    private static function autoloadPhpseclib(string $root): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $bootstrap = $root . '/lib/phpseclib/bootstrap.php';
        if (is_file($bootstrap)) {
            require_once $bootstrap;
        }

        spl_autoload_register(static function (string $class) use ($root): void {
            $map = [
                'phpseclib3\\' => $root . '/lib/phpseclib/',
                'ParagonIE\\ConstantTime\\' => $root . '/lib/paragonie/constant_time_encoding/',
            ];
            foreach ($map as $prefix => $baseDir) {
                if (!str_starts_with($class, $prefix)) {
                    continue;
                }
                $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
                $file = $baseDir . $relative . '.php';
                if (is_file($file)) {
                    require $file;
                }
                return;
            }
        });
    }

    /** @param list<string> $include @param list<string> $exclude @param list<string> $log */
    private static function buildTar(string $root, array $include, array $exclude, string $tarPath, array &$log): void
    {
        $include = array_values(array_filter(array_map('strval', $include)));
        $exclude = array_values(array_filter(array_map('strval', $exclude)));

        $include = array_values(array_filter($include, static fn(string $item): bool => file_exists($root . DIRECTORY_SEPARATOR . $item)));

        if ($include === []) {
            throw new \RuntimeException('Список include пуст (нет существующих файлов)');
        }

        $tarBin = self::tarBinary();
        $args = [$tarBin, '-cf', $tarPath];
        foreach ($exclude as $ex) {
            $args[] = '--exclude=' . $ex;
        }
        foreach ($include as $item) {
            $args[] = $item;
        }

        $log[] = 'Include: ' . implode(', ', $include);
        $log[] = 'Exclude: ' . implode(', ', $exclude);

        $cmd = self::buildCommand($args);
        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $env = null;
        if (PHP_OS_FAMILY !== 'Windows') {
            $env = array_merge($_ENV ?: [], getenv() ?: [], [
                'COPYFILE_DISABLE' => '1',
                'COPY_EXTENDED_ATTRIBUTES_DISABLE' => '1',
            ]);
        }
        $proc = proc_open($cmd, $descriptor, $pipes, $root, $env, ['bypass_shell' => true]);
        if (!is_resource($proc)) {
            throw new \RuntimeException('Не удалось запустить ' . $tarBin);
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        if ($code !== 0 || !is_file($tarPath)) {
            throw new \RuntimeException('tar не собрал архив: ' . trim($stdout . ' ' . $stderr));
        }

        $log[] = 'Archive: ' . number_format(filesize($tarPath) / 1024, 1) . ' KB';
    }

    private static function tarBinary(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return 'tar.exe';
        }
        foreach (['/usr/bin/tar', '/bin/tar', 'tar'] as $bin) {
            if ($bin === 'tar' || is_executable($bin)) {
                return $bin;
            }
        }
        return 'tar';
    }

    /** @param list<string> $args */
    private static function buildCommand(array $args): string
    {
        $parts = [];
        foreach ($args as $arg) {
            $parts[] = PHP_OS_FAMILY === 'Windows'
                ? self::quoteWin((string) $arg)
                : escapeshellarg((string) $arg);
        }
        return implode(' ', $parts);
    }

    private static function quoteWin(string $value): string
    {
        if ($value === '') {
            return '""';
        }
        if (!preg_match('/[\s"]/', $value)) {
            return $value;
        }
        return '"' . str_replace('"', '""', $value) . '"';
    }

    private static function shellQuote(string $value): string
    {
        return "'" . str_replace("'", "'\\''", $value) . "'";
    }

    private static function quickDiag(SFTP $sftp, string $remote): string
    {
        return trim((string) $sftp->exec(
            'id; ls -ld ' . self::shellQuote($remote) . ' 2>&1; sudo -n true 2>&1 | head -n 2'
        ));
    }

    /** @param list<string> $log */
    private static function joinLog(array $log, string $password, string $extra = ''): string
    {
        $text = implode("\n", $log);
        if ($extra !== '') {
            $text .= "\n" . $extra;
        }
        if ($password !== '') {
            $text = str_replace($password, '***', $text);
        }
        return $text;
    }
}
