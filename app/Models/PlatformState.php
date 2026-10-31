<?php

namespace App\Models;

use App\Core\Database;

/** Состояние помоста для live-табло (текущий + отложенные). */
class PlatformState
{
    public static function setCurrent(int $disciplineId, ?string $currentKey): void
    {
        self::upsert($disciplineId, $currentKey, null);
    }

    /** @return list<string> */
    public static function deferredKeys(int $disciplineId): array
    {
        $st = Database::pdo()->prepare(
            'SELECT deferred_keys FROM platform_state WHERE discipline_id = ?'
        );
        $st->execute([$disciplineId]);
        $raw = $st->fetchColumn();
        if ($raw === false || $raw === null || $raw === '') {
            return [];
        }
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($decoded)) {
            return [];
        }
        return array_values(array_map('strval', $decoded));
    }

    public static function setDeferred(int $disciplineId, array $keys): void
    {
        $keys = array_values(array_unique(array_map('strval', $keys)));
        self::upsert($disciplineId, null, $keys, false);
    }

    public static function defer(int $disciplineId, string $queueKey): void
    {
        $list = self::deferredKeys($disciplineId);
        $list = array_values(array_filter($list, static fn ($k) => $k !== $queueKey));
        $list[] = $queueKey;
        self::setDeferred($disciplineId, $list);
    }

    public static function undefer(int $disciplineId, string $queueKey): void
    {
        $list = array_values(array_filter(
            self::deferredKeys($disciplineId),
            static fn ($k) => $k !== $queueKey
        ));
        self::setDeferred($disciplineId, $list);
    }

    public static function getCurrent(int $disciplineId): ?string
    {
        $st = Database::pdo()->prepare(
            'SELECT current_key FROM platform_state WHERE discipline_id = ?'
        );
        $st->execute([$disciplineId]);
        $v = $st->fetchColumn();
        return $v !== false && $v !== null && $v !== '' ? (string) $v : null;
    }

    /**
     * @param list<string>|null $deferred
     */
    private static function upsert(
        int $disciplineId,
        ?string $currentKey,
        ?array $deferred,
        bool $touchCurrent = true
    ): void {
        $pdo = Database::pdo();
        $exists = $pdo->prepare('SELECT discipline_id FROM platform_state WHERE discipline_id = ?');
        $exists->execute([$disciplineId]);

        if (!$exists->fetchColumn()) {
            $st = $pdo->prepare(
                'INSERT INTO platform_state (discipline_id, current_key, deferred_keys)
                 VALUES (?, ?, ?)'
            );
            $st->execute([
                $disciplineId,
                $touchCurrent ? $currentKey : null,
                json_encode($deferred ?? [], JSON_UNESCAPED_UNICODE),
            ]);
            return;
        }

        if ($touchCurrent && $deferred === null) {
            $st = $pdo->prepare(
                'UPDATE platform_state SET current_key = ? WHERE discipline_id = ?'
            );
            $st->execute([$currentKey, $disciplineId]);
            return;
        }

        if (!$touchCurrent && $deferred !== null) {
            $st = $pdo->prepare(
                'UPDATE platform_state SET deferred_keys = ? WHERE discipline_id = ?'
            );
            $st->execute([json_encode($deferred, JSON_UNESCAPED_UNICODE), $disciplineId]);
            return;
        }

        $st = $pdo->prepare(
            'UPDATE platform_state SET current_key = ?, deferred_keys = ? WHERE discipline_id = ?'
        );
        $st->execute([
            $currentKey,
            json_encode($deferred ?? [], JSON_UNESCAPED_UNICODE),
            $disciplineId,
        ]);
    }
}
