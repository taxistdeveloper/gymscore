<?php

/**
 * Миграция: поддержка весовых категорий для каждой дисциплины и возрастных групп
 *
 * - participants.body_weight -> DECIMAL(6,2)
 * - participants.age_group -> VARCHAR(50)
 * - registrations.weight_category -> VARCHAR(80)
 *
 * php database/migrate_weight_categories.php
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

$has = static function (PDO $pdo, string $table, string $column): bool {
    $st = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
    $st->execute([$column]);
    return (bool) $st->fetch();
};

// 1. DECIMAL(6,2) для body_weight
$pdo->exec(
    "ALTER TABLE participants MODIFY COLUMN body_weight DECIMAL(6,2) NULL DEFAULT NULL COMMENT 'Собственный вес, кг (взвешивание на месте)'"
);
echo "participants.body_weight is nullable DECIMAL(6,2)\n";

// 2. age_group в participants
if (!$has($pdo, 'participants', 'age_group')) {
    $pdo->exec(
        "ALTER TABLE participants
         ADD COLUMN age_group VARCHAR(50) NULL
           COMMENT 'Возрастная группа (juniors, open)'
           AFTER birth_year"
    );
    echo "participants.age_group added\n";
} else {
    echo "participants.age_group already exists\n";
}

// 3. weight_category в registrations
if (!$has($pdo, 'registrations', 'weight_category')) {
    $pdo->exec(
        "ALTER TABLE registrations
         ADD COLUMN weight_category VARCHAR(80) NULL
           COMMENT 'Весовая категория в дисциплине'
           AFTER opening_weight"
    );
    echo "registrations.weight_category added\n";
} else {
    echo "registrations.weight_category already exists\n";
}

// 4. Заполняем weight_category старых регистраций из participants, если не задана
$pdo->exec(
    "UPDATE registrations r
     JOIN participants p ON p.id = r.participant_id
     SET r.weight_category = p.weight_category
     WHERE r.weight_category IS NULL AND p.weight_category IS NOT NULL AND p.weight_category != ''"
);
echo "registrations.weight_category backfilled from participants\n";

echo "ok\n";

