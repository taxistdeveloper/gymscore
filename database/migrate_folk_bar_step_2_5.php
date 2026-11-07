<?php
/**
 * Народный жим: штанга = ½ с/в с округлением до шага 2.5 кг (было 0.5).
 *
 * php database/migrate_folk_bar_step_2_5.php
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

// ROUND(bw/5)*2.5 ≡ nearest 2.5 of (bw/2)
$updReg = $pdo->exec(
    "UPDATE registrations r
     JOIN disciplines d ON d.id = r.discipline_id AND d.code = 'bench_folk'
     JOIN participants p ON p.id = r.participant_id
     SET r.opening_weight = ROUND(p.body_weight / 5) * 2.5
     WHERE p.weighed_at IS NOT NULL AND p.body_weight > 0"
);
echo "registrations opening_weight → step 2.5 ({$updReg} row)\n";

$updAtt = $pdo->exec(
    "UPDATE attempts a
     JOIN disciplines d ON d.id = a.discipline_id AND d.code = 'bench_folk'
     JOIN participants p ON p.id = a.participant_id
     SET a.declared_weight = ROUND(p.body_weight / 5) * 2.5
     WHERE a.attempt_no = 1
       AND a.value IS NULL
       AND p.body_weight > 0"
);
echo "undeclared attempt #1 declared_weight → step 2.5 ({$updAtt} row)\n";

echo "OK\n";
