<?php
/**
 * Жим лёжа народный 1/2 с/в: результат = повторы, вес штанги = ½ с/в.
 *
 * php database/migrate_bench_folk_reps.php
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

$st = $pdo->prepare(
    "UPDATE disciplines
     SET scoring_type = 'reps', attempts_allowed = 1
     WHERE code = 'bench_folk'"
);
$st->execute();
echo "bench_folk → reps, 1 attempt ({$st->rowCount()} row)\n";

// ROUND(bw/5)*2.5: половина с/в до шага 2.5 кг
$updReg = $pdo->exec(
    "UPDATE registrations r
     JOIN disciplines d ON d.id = r.discipline_id AND d.code = 'bench_folk'
     JOIN participants p ON p.id = r.participant_id
     SET r.opening_weight = ROUND(p.body_weight / 5) * 2.5
     WHERE p.weighed_at IS NOT NULL AND p.body_weight > 0"
);
echo "registrations opening_weight synced ({$updReg} row)\n";

$updAtt = $pdo->exec(
    "UPDATE attempts a
     JOIN disciplines d ON d.id = a.discipline_id AND d.code = 'bench_folk'
     JOIN participants p ON p.id = a.participant_id
     SET a.declared_weight = ROUND(p.body_weight / 5) * 2.5
     WHERE a.attempt_no = 1 AND a.value IS NULL AND p.body_weight > 0"
);
echo "undeclared attempt #1 declared_weight synced ({$updAtt} row)\n";

echo "OK\n";
