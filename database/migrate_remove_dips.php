<?php

/**
 * Миграция: полное удаление дисциплины «Отжимания на брусьях» (code: 'dips').
 *
 * php database/migrate_remove_dips.php
 */

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

$st = $pdo->prepare("SELECT id FROM disciplines WHERE code = 'dips'");
$st->execute();
$dipsId = $st->fetchColumn();

if (!$dipsId) {
    echo "Discipline 'dips' not found in database (already removed).\n";
} else {
    // 1. Очистка platform_state
    $delState = $pdo->prepare("DELETE FROM platform_state WHERE discipline_id = ?");
    $delState->execute([$dipsId]);
    echo "platform_state deleted ({$delState->rowCount()} row)\n";

    // 2. Очистка попыток и регистраций (хотя FOREIGN KEY CASCADE также сработает)
    $delAtt = $pdo->prepare("DELETE FROM attempts WHERE discipline_id = ?");
    $delAtt->execute([$dipsId]);
    echo "attempts deleted ({$delAtt->rowCount()} row)\n";

    $delReg = $pdo->prepare("DELETE FROM registrations WHERE discipline_id = ?");
    $delReg->execute([$dipsId]);
    echo "registrations deleted ({$delReg->rowCount()} row)\n";

    // 3. Удаление самой дисциплины
    $delDisc = $pdo->prepare("DELETE FROM disciplines WHERE id = ?");
    $delDisc->execute([$dipsId]);
    echo "disciplines deleted 'dips' (id: {$dipsId})\n";
}

// 4. Актуализация порядкового номера (sort_order)
$pdo->exec("UPDATE disciplines SET sort_order = 5 WHERE code = 'armwrestling'");
echo "disciplines sort_order updated\n";

