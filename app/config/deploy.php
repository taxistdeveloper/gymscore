<?php

/**
 * Деплой с локалки на сервер (кнопка в админке).
 * Работает только с localhost. Без пароля — по SSH-ключу.
 * config.php на сервер не отправляется.
 */
return [
    'ssh_host'     => 'kvki.kz',
    // root по ключу — без пароля и без sudo
    'ssh_user'     => 'root',
    'ssh_identity' => '/Users/macbookpro/.ssh/id_ed25519',
    'remote_path'  => '/var/www/sportdecor_k_usr/data/www/sportdecor.kz/alashgym',
    'site_owner'   => 'sportdecor_k_usr',

    'include' => [
        'app',
        'public',
        'database',
        'index.php',
        'package.json',
        '.htaccess',
    ],

    'exclude' => [
        'node_modules',
        '.git',
        'app/config/config.php',
        'app/config/deploy.php',
        'lib',
        'scripts',
        'composer.phar',
        'package-lock.json',
    ],
];
