<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Discipline
{
    public static function all(): array
    {
        return Database::pdo()
            ->query('SELECT * FROM disciplines ORDER BY sort_order')
            ->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $st = Database::pdo()->prepare('SELECT * FROM disciplines WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function findByCode(string $code): ?array
    {
        $st = Database::pdo()->prepare('SELECT * FROM disciplines WHERE code = ?');
        $st->execute([$code]);
        $row = $st->fetch();
        return $row ?: null;
    }
}
