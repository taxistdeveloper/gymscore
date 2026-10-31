<?php

namespace App\Models;

use App\Core\Database;
use InvalidArgumentException;

class StaffAccount
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_JUDGE = 'judge';

    private const TABLES = [
        self::ROLE_ADMIN => 'admins',
        self::ROLE_JUDGE => 'judges',
    ];

    public static function roles(): array
    {
        return [
            self::ROLE_ADMIN => 'Администратор',
            self::ROLE_JUDGE => 'Судья',
        ];
    }

    public static function table(string $role): string
    {
        if (!isset(self::TABLES[$role])) {
            throw new InvalidArgumentException('Некорректная роль');
        }
        return self::TABLES[$role];
    }

    public static function all(string $role): array
    {
        $table = self::table($role);
        return Database::pdo()
            ->query("SELECT id, username, display_name FROM `{$table}` ORDER BY display_name, username")
            ->fetchAll();
    }

    public static function find(string $role, int $id): ?array
    {
        $table = self::table($role);
        $st = Database::pdo()->prepare(
            "SELECT id, username, display_name FROM `{$table}` WHERE id = ?"
        );
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function findByUsername(string $role, string $username): ?array
    {
        $table = self::table($role);
        $st = Database::pdo()->prepare(
            "SELECT id, username, display_name FROM `{$table}` WHERE username = ?"
        );
        $st->execute([$username]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function count(string $role): int
    {
        $table = self::table($role);
        return (int) Database::pdo()
            ->query("SELECT COUNT(*) FROM `{$table}`")
            ->fetchColumn();
    }

    public static function create(string $role, string $username, string $password, string $displayName): int
    {
        $table = self::table($role);
        $st = Database::pdo()->prepare(
            "INSERT INTO `{$table}` (username, password_hash, display_name) VALUES (?, ?, ?)"
        );
        $st->execute([
            $username,
            password_hash($password, PASSWORD_DEFAULT),
            $displayName,
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    public static function update(
        string $role,
        int $id,
        string $username,
        string $displayName,
        ?string $password = null
    ): void {
        $table = self::table($role);
        if ($password !== null && $password !== '') {
            $st = Database::pdo()->prepare(
                "UPDATE `{$table}`
                 SET username = ?, display_name = ?, password_hash = ?
                 WHERE id = ?"
            );
            $st->execute([
                $username,
                $displayName,
                password_hash($password, PASSWORD_DEFAULT),
                $id,
            ]);
            return;
        }

        $st = Database::pdo()->prepare(
            "UPDATE `{$table}` SET username = ?, display_name = ? WHERE id = ?"
        );
        $st->execute([$username, $displayName, $id]);
    }

    public static function delete(string $role, int $id): void
    {
        $table = self::table($role);
        $st = Database::pdo()->prepare("DELETE FROM `{$table}` WHERE id = ?");
        $st->execute([$id]);
    }
}
