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

if (!$has($pdo, 'participants', 'weigh_note')) {
    $pdo->exec(
        "ALTER TABLE participants
         ADD COLUMN weigh_note TEXT NULL
           COMMENT 'заметки судьи на весах'
           AFTER weighed_at"
    );
    echo "weigh_note added\n";
}

if (!$has($pdo, 'participants', 'weight_category')) {
    $pdo->exec(
        "ALTER TABLE participants
         ADD COLUMN weight_category VARCHAR(80) NULL
           COMMENT 'категория по собственному весу'
           AFTER weigh_note"
    );
    echo "weight_category added\n";
}

if (!$has($pdo, 'registrations', 'opening_weight')) {
    $pdo->exec(
        "ALTER TABLE registrations
         ADD COLUMN opening_weight DECIMAL(8,2) NULL
           COMMENT 'стартовый вес / заявка на 1 попытку'
           AFTER discipline_id"
    );
    echo "opening_weight added\n";
}

echo "ok\n";
