<?php

namespace App\Core;

class Controller
{
    protected array $config;

    public function __construct()
    {
        $this->config = $GLOBALS['app_config'] ?? [];
    }

    protected function view(string $name, array $data = []): void
    {
        extract($data);
        $config = $this->config;
        $base   = $config['base_url'] ?? '';
        $lang   = \App\Lang\Lang::locale();
        $t      = static fn (string $key, ?string $fallback = null): string => \App\Lang\Lang::t($key, $fallback);
        $csrfToken = $this->csrfToken();

        ob_start();
        require dirname(__DIR__) . '/Views/' . $name . '.php';
        $content = ob_get_clean();

        require dirname(__DIR__) . '/Views/layouts/main.php';
    }

    protected function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    protected function validateCsrf(): bool
    {
        $token = $_POST['csrf_token'] ?? '';
        return !empty($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
    }

    protected function redirect(string $path): void
    {
        $base = $this->config['base_url'] ?? '';
        header('Location: ' . $base . $path);
        exit;
    }

    protected function flash(string $type, string $message, array $extra = []): void
    {
        $_SESSION['flash'] = array_merge(['type' => $type, 'message' => $message], $extra);
    }

    protected function takeFlash(): ?array
    {
        $f = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $f;
    }

    protected function requireJudge(): void
    {
        if (empty($_SESSION['judge_id'])) {
            $this->flash('warning', 'Войдите как судья');
            $this->redirect('/judge/login');
        }
    }

    protected function requireAdmin(): void
    {
        if (empty($_SESSION['admin_id'])) {
            $this->flash('warning', 'Войдите как администратор');
            $this->redirect('/admin/login');
        }
    }
}
