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

$has = static function (PDO $pdo, string $table, string $column): bool {
    $st = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
    $st->execute([$column]);
    return (bool) $st->fetch();
};

if (!$has($pdo, 'participants', 'lot_number')) {
    $pdo->exec(
        "ALTER TABLE participants
         ADD COLUMN lot_number INT UNSIGNED NULL
           COMMENT 'жребий'
           AFTER weight_category"
    );
    echo "lot_number added\n";
}

if (!$has($pdo, 'attempts', 'declared_weight')) {
    $pdo->exec(
        "ALTER TABLE attempts
         ADD COLUMN declared_weight DECIMAL(8,2) NULL
           COMMENT 'заказанный вес на попытку'
           AFTER attempt_no"
    );
    echo "declared_weight added\n";
}

// Раздать жребий принятым без номера
$missing = (int) $pdo->query(
    "SELECT COUNT(*) FROM participants WHERE status = 'approved' AND lot_number IS NULL"
)->fetchColumn();
if ($missing > 0) {
    $max = (int) $pdo->query('SELECT COALESCE(MAX(lot_number), 0) FROM participants')->fetchColumn();
    $st = $pdo->query(
        "SELECT id FROM participants WHERE status = 'approved' AND lot_number IS NULL ORDER BY id"
    );
    $upd = $pdo->prepare('UPDATE participants SET lot_number = ? WHERE id = ?');
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $max++;
        $upd->execute([$max, $id]);
    }
    echo "lots assigned: {$missing}\n";
}

// Синхронизировать opening → attempt 1 declared
$pdo->exec(
    "INSERT INTO attempts (participant_id, discipline_id, attempt_no, declared_weight, value, is_valid)
     SELECT r.participant_id, r.discipline_id, 1, r.opening_weight, NULL, 1
     FROM registrations r
     JOIN participants p ON p.id = r.participant_id AND p.status = 'approved'
     WHERE r.opening_weight IS NOT NULL
     ON DUPLICATE KEY UPDATE
       declared_weight = COALESCE(attempts.declared_weight, VALUES(declared_weight))"
);
echo "openings synced\n";
echo "ok\n";
