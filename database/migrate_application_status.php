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

$cols = $pdo->query("SHOW COLUMNS FROM participants LIKE 'status'")->fetchAll();
if (!$cols) {
    $pdo->exec(
        "ALTER TABLE participants
         ADD COLUMN status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'
           COMMENT 'заявка: новая / принята / отклонена'
           AFTER birth_year,
         ADD COLUMN status_note VARCHAR(255) DEFAULT NULL
           COMMENT 'комментарий админа'
           AFTER status,
         ADD COLUMN reviewed_at TIMESTAMP NULL DEFAULT NULL
           AFTER status_note"
    );
    // Существующие участники уже в протоколе
    $pdo->exec("UPDATE participants SET status = 'approved', reviewed_at = NOW()");
    echo "status column added\n";
} else {
    echo "status column exists\n";
}

echo "ok\n";
