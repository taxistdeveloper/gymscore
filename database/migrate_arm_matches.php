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

$exists = static function (PDO $pdo, string $table): bool {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $st->execute([$table]);
    return (int) $st->fetchColumn() > 0;
};

if ($exists($pdo, 'arm_matches')) {
    echo "arm_matches already exists\n";
    exit(0);
}

$pdo->exec(
    "CREATE TABLE arm_matches (
       id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
       discipline_id INT UNSIGNED NOT NULL,
       weight_category VARCHAR(80) NOT NULL,
       round_no TINYINT UNSIGNED NOT NULL DEFAULT 1,
       match_no SMALLINT UNSIGNED NOT NULL DEFAULT 1,
       participant1_id INT UNSIGNED NOT NULL,
       participant2_id INT UNSIGNED NULL COMMENT 'NULL = bye',
       winner_id INT UNSIGNED NULL,
       status ENUM('pending', 'done') NOT NULL DEFAULT 'pending',
       is_current TINYINT(1) NOT NULL DEFAULT 0,
       judged_at TIMESTAMP NULL DEFAULT NULL,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       KEY idx_arm_disc_cat (discipline_id, weight_category, round_no, match_no),
       KEY idx_arm_current (discipline_id, is_current),
       FOREIGN KEY (discipline_id) REFERENCES disciplines(id) ON DELETE CASCADE,
       FOREIGN KEY (participant1_id) REFERENCES participants(id) ON DELETE CASCADE,
       FOREIGN KEY (participant2_id) REFERENCES participants(id) ON DELETE SET NULL,
       FOREIGN KEY (winner_id) REFERENCES participants(id) ON DELETE SET NULL
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

echo "arm_matches created\n";
