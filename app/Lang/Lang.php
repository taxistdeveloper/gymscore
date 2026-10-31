<?php

namespace App\Lang;

class Lang
{
    private static string $locale = 'ru';
    /** @var array<string, string> */
    private static array $lines = [];

    public static function boot(): void
    {
        $allowed = ['ru', 'kk'];
        $chosen = null;

        if (isset($_GET['lang']) && in_array($_GET['lang'], $allowed, true)) {
            $chosen = $_GET['lang'];
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            setcookie('ag_lang', $chosen, [
                'expires'  => time() + 365 * 24 * 60 * 60,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
            $_COOKIE['ag_lang'] = $chosen;
        } else {
            $fromCookie = (string) ($_COOKIE['ag_lang'] ?? 'ru');
            $chosen = in_array($fromCookie, $allowed, true) ? $fromCookie : 'ru';
        }

        self::$locale = $chosen;
        self::$lines = require __DIR__ . '/' . self::$locale . '.php';

        if (isset($_GET['lang']) && in_array($_GET['lang'], $allowed, true)) {
            self::redirectWithoutLang();
        }
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    public static function t(string $key, ?string $fallback = null): string
    {
        return self::$lines[$key] ?? $fallback ?? $key;
    }

    public static function disciplineName(array $d): string
    {
        $code = (string) ($d['code'] ?? '');
        return self::t('disc.' . $code, (string) ($d['name_ru'] ?? $code));
    }

    public static function switchUrl(string $locale): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $parts = parse_url($uri);
        $path = $parts['path'] ?? '/';
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        $query['lang'] = $locale;
        return $path . '?' . http_build_query($query);
    }

    private static function redirectWithoutLang(): void
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $parts = parse_url($uri);
        $path = $parts['path'] ?? '/';
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        unset($query['lang']);
        $target = $path . ($query ? '?' . http_build_query($query) : '');
        header('Location: ' . $target);
        exit;
    }
}
