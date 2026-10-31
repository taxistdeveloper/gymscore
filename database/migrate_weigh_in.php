<?php

$c = require dirname(__DIR__) . '/app/config/config.php';
$db = $c['db'];

$pdo = new PDO(
    sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $db['host'],
        $db['port'],
        $db['name'],
        $db['charset']
    ),
    $db['user'],
    $db['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$cols = $pdo->query("SHOW COLUMNS FROM participants LIKE 'weighed_at'")->fetchAll();
if (!$cols) {
    $pdo->exec(
        "ALTER TABLE participants
         ADD COLUMN weighed_at TIMESTAMP NULL DEFAULT NULL
           COMMENT 'официальное взвешивание'
           AFTER reviewed_at"
    );
    echo "weighed_at column added\n";
} else {
    echo "weighed_at column exists\n";
}

echo "ok\n";
