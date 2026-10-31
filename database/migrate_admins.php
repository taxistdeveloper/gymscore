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
    'CREATE TABLE IF NOT EXISTS admins (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      username VARCHAR(50) NOT NULL UNIQUE,
      password_hash VARCHAR(255) NOT NULL,
      display_name VARCHAR(100) NOT NULL
    ) ENGINE=InnoDB'
);

$hash = '$2y$10$nrfF40e7kq8NWq.A7AxFhO.iQ8JcSWttu1iHRCa.hhna1mBhgkImu';
$st = $pdo->prepare('SELECT COUNT(*) FROM admins WHERE username = ?');
$st->execute(['admin']);

if (!(int) $st->fetchColumn()) {
    $pdo->prepare(
        'INSERT INTO admins (username, password_hash, display_name) VALUES (?, ?, ?)'
    )->execute(['admin', $hash, 'Администратор']);
    echo "admin created\n";
} else {
    echo "admin exists\n";
}

echo "ok\n";
