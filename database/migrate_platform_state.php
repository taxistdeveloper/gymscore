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

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS platform_state (
       discipline_id INT UNSIGNED NOT NULL PRIMARY KEY,
       current_key VARCHAR(32) DEFAULT NULL,
       updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
     ) ENGINE=InnoDB'
);

echo "platform_state ok\n";
