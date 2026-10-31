<?php

namespace App\Models;

/**
 * Весовые категории турнира.
 * При взвешивании атлет попадает в первую категорию, чей лимит >= вес.
 */
class WeightCategory
{
    /** @return list<array{max: ?float, label: string}> */
    public static function classes(string $gender): array
    {
        if ($gender === 'F') {
            return [
                ['max' => 52.0,  'label' => 'Женщины до 52 кг'],
                ['max' => 60.0,  'label' => 'Женщины до 60 кг'],
                ['max' => 70.0,  'label' => 'Женщины до 70 кг'],
                ['max' => 80.0,  'label' => 'Женщины до 80 кг'],
                ['max' => null,  'label' => 'Женщины свыше 80 кг'],
            ];
        }

        return [
            ['max' => 60.0,  'label' => 'Мужчины до 60 кг'],
            ['max' => 70.0,  'label' => 'Мужчины до 70 кг'],
            ['max' => 80.0,  'label' => 'Мужчины до 80 кг'],
            ['max' => 90.0,  'label' => 'Мужчины до 90 кг'],
            ['max' => 100.0, 'label' => 'Мужчины до 100 кг'],
            ['max' => 110.0, 'label' => 'Мужчины до 110 кг'],
            ['max' => null,  'label' => 'Мужчины свыше 110 кг'],
        ];
    }

    /**
     * Определение весовой категории по дисциплине, возрастной группе и собственному весу.
     *
     * @param string|int|array $discipline Код дисциплины, id или массив
     * @param string $ageGroup juniors | open
     * @param float $bodyWeight Собственный вес
     * @param array|null $competitionConfig Настройки соревнования
     */
    public static function forDiscipline(
        string|int|array $discipline,
        string $ageGroup,
        float $bodyWeight,
        ?array $competitionConfig = null
    ): string {
        $code = '';
        if (is_array($discipline)) {
            $code = (string) ($discipline['code'] ?? '');
        } elseif (is_int($discipline) || (is_string($discipline) && ctype_digit($discipline))) {
            $d = Discipline::find((int) $discipline);
            $code = (string) ($d['code'] ?? '');
        } else {
            $code = (string) $discipline;
        }

        $type = ($code === 'armwrestling') ? 'armwrestling' : 'powerlifting';
        $ag = (strtolower($ageGroup) === 'juniors'
            || str_contains(mb_strtolower($ageGroup), 'юниор')
            || str_contains(mb_strtolower($ageGroup), 'юнош'))
            ? 'juniors'
            : 'open';

        $config = $competitionConfig ?? ($GLOBALS['app_config']['competition'] ?? []);
        $weightCategories = $config['weight_categories'] ?? self::defaultRules();
        $rules = $weightCategories[$type][$ag] ?? (self::defaultRules()[$type][$ag] ?? []);

        $bw = round($bodyWeight, 2);

        foreach ($rules as $rule) {
            if (!empty($rule['pending_clarification'])) {
                if (isset($rule['min']) && $bw < (float) $rule['min']) {
                    continue;
                }
                return (string) $rule['label'];
            }
            if ($rule['max'] !== null && $bw <= (float) $rule['max']) {
                return (string) $rule['label'];
            }
            if ($rule['max'] === null) {
                return (string) $rule['label'];
            }
        }

        return 'категория определяется согласно уточнённому регламенту';
    }

    /**
     * Список весовых категорий для типа дисциплины и возрастной группы.
     *
     * @param string $type powerlifting | armwrestling
     * @param string $ageGroup juniors | open
     * @return list<array{max: ?float, label: string}>
     */
    public static function categoriesFor(string $type, string $ageGroup, ?array $competitionConfig = null): array
    {
        $config = $competitionConfig ?? ($GLOBALS['app_config']['competition'] ?? []);
        $weightCategories = $config['weight_categories'] ?? self::defaultRules();
        $ag = (strtolower($ageGroup) === 'juniors'
            || str_contains(mb_strtolower($ageGroup), 'юниор')
            || str_contains(mb_strtolower($ageGroup), 'юнош'))
            ? 'juniors'
            : 'open';

        $sportType = ($type === 'armwrestling') ? 'armwrestling' : 'powerlifting';
        return $weightCategories[$sportType][$ag] ?? (self::defaultRules()[$sportType][$ag] ?? []);
    }

    /**
     * Нормализация пользовательского выбора категории до канонического лейбла.
     * Например, "67,5 кг" или "67.5 кг" -> "до 67.5 кг", "82,5+ кг" -> "82.5+ кг".
     */
    public static function normalizeLabel(
        string $input,
        string|int|array $disciplineOrType,
        string $ageGroup,
        ?array $competitionConfig = null
    ): ?string {
        $cleanInput = trim($input);
        if ($cleanInput === '') {
            return null;
        }

        $type = 'powerlifting';
        if (is_string($disciplineOrType) && in_array($disciplineOrType, ['powerlifting', 'armwrestling'], true)) {
            $type = $disciplineOrType;
        } else {
            $code = '';
            if (is_array($disciplineOrType)) {
                $code = (string) ($disciplineOrType['code'] ?? '');
            } elseif (is_int($disciplineOrType) || (is_string($disciplineOrType) && ctype_digit($disciplineOrType))) {
                $d = Discipline::find((int) $disciplineOrType);
                $code = (string) ($d['code'] ?? '');
            } else {
                $code = (string) $disciplineOrType;
            }
            $type = ($code === 'armwrestling') ? 'armwrestling' : 'powerlifting';
        }

        $rules = self::categoriesFor($type, $ageGroup, $competitionConfig);

        $canon = static function (string $s): string {
            $s = mb_strtolower(trim($s));
            $s = str_replace(['до', 'кг', 'свыше', ' ', ','], ['', '', '', '', '.'], $s);
            return $s;
        };

        $target = $canon($cleanInput);

        foreach ($rules as $rule) {
            $ruleLabel = (string) ($rule['label'] ?? '');
            if ($cleanInput === $ruleLabel) {
                return $ruleLabel;
            }
            if ($target === $canon($ruleLabel)) {
                return $ruleLabel;
            }
        }

        return $cleanInput;
    }

    /**
     * Проверка существования категории для дисциплины и возрастной группы.
     */
    public static function isValidCategory(
        string $label,
        string|int|array $disciplineOrType,
        string $ageGroup,
        ?array $competitionConfig = null
    ): bool {
        $canonical = self::normalizeLabel($label, $disciplineOrType, $ageGroup, $competitionConfig);
        if (!$canonical) {
            return false;
        }

        $type = 'powerlifting';
        if (is_string($disciplineOrType) && in_array($disciplineOrType, ['powerlifting', 'armwrestling'], true)) {
            $type = $disciplineOrType;
        } else {
            $code = '';
            if (is_array($disciplineOrType)) {
                $code = (string) ($disciplineOrType['code'] ?? '');
            } elseif (is_int($disciplineOrType) || (is_string($disciplineOrType) && ctype_digit($disciplineOrType))) {
                $d = Discipline::find((int) $disciplineOrType);
                $code = (string) ($d['code'] ?? '');
            } else {
                $code = (string) $disciplineOrType;
            }
            $type = ($code === 'armwrestling') ? 'armwrestling' : 'powerlifting';
        }

        $rules = self::categoriesFor($type, $ageGroup, $competitionConfig);
        foreach ($rules as $rule) {
            if ($rule['label'] === $canonical) {
                return true;
            }
        }
        return false;
    }

    /**
     * Проверка допустимости возрастной группы для указанного года рождения
     * на основе настроек соревнования (без жестко заданных в коде чисел).
     */
    public static function isAgeGroupAllowed(string $ageGroup, int $birthYear, ?array $competitionConfig = null): bool
    {
        $config = $competitionConfig ?? ($GLOBALS['app_config']['competition'] ?? []);
        $groups = $config['age_groups'] ?? [];
        $group = $groups[$ageGroup] ?? null;
        if (!$group) {
            return false;
        }

        $eventYear = (int) ($config['event_year'] ?? 2026);
        $minYear = isset($group['min_birth_year'])
            ? (int) $group['min_birth_year']
            : ($eventYear - (int) ($group['max_age'] ?? 100));
        $maxYear = isset($group['max_birth_year'])
            ? (int) $group['max_birth_year']
            : ($eventYear - (int) ($group['min_age'] ?? 0));

        return $birthYear >= $minYear && $birthYear <= $maxYear;
    }

    /**
     * Возвращает границы года рождения для возрастной группы.
     * @return array{min: int, max: int, min_age: int, max_age: int}
     */
    public static function ageGroupBounds(string $ageGroup, ?array $competitionConfig = null): array
    {
        $config = $competitionConfig ?? ($GLOBALS['app_config']['competition'] ?? []);
        $group = ($config['age_groups'] ?? [])[$ageGroup] ?? [];
        $eventYear = (int) ($config['event_year'] ?? 2026);

        $minAge = (int) ($group['min_age'] ?? 14);
        $maxAge = (int) ($group['max_age'] ?? 100);
        $minYear = isset($group['min_birth_year']) ? (int) $group['min_birth_year'] : ($eventYear - $maxAge);
        $maxYear = isset($group['max_birth_year']) ? (int) $group['max_birth_year'] : ($eventYear - $minAge);

        return [
            'min'     => $minYear,
            'max'     => $maxYear,
            'min_age' => $minAge,
            'max_age' => $maxAge,
        ];
    }

    /**
     * Год рождения из даты (ДД.ММ.ГГГГ / ДД.ММ.ГГ) или из года (1990, 90).
     */
    public static function parseBirthYear(string $raw): ?int
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/(19\d{2}|20\d{2})(?!\d)/', $raw, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/(?:^|\D)(\d{1,2})[.\-\/](\d{1,2})[.\-\/](\d{2})(?!\d)/', $raw, $m)) {
            $y2 = (int) $m[3];
            return $y2 <= 30 ? 2000 + $y2 : 1900 + $y2;
        }
        if (preg_match('/^\d{2}$/', $raw)) {
            $y2 = (int) $raw;
            return $y2 <= 30 ? 2000 + $y2 : 1900 + $y2;
        }
        return null;
    }

    /**
     * Группа, в которую можно записать этот год.
     * Явный выбор сохраняется, только если год в неё входит (юниор может идти в Open).
     * Иначе берётся первая подходящая: юниоры, затем открытая.
     */
    public static function resolveAgeGroup(string $requested, ?int $birthYear, ?array $competitionConfig = null): string
    {
        $config = $competitionConfig ?? ($GLOBALS['app_config']['competition'] ?? []);
        $groups = $config['age_groups'] ?? [];
        $requested = trim($requested);

        $allowed = static function (string $key) use ($groups, $birthYear, $config): bool {
            return $birthYear !== null
                && isset($groups[$key])
                && self::isAgeGroupAllowed($key, $birthYear, $config);
        };

        if ($allowed($requested)) {
            return $requested;
        }

        if ($birthYear !== null) {
            foreach (['juniors', 'open'] as $candidate) {
                if ($allowed($candidate)) {
                    return $candidate;
                }
            }
        }

        if ($requested !== '' && isset($groups[$requested])) {
            return $requested;
        }

        return isset($groups['open']) ? 'open' : 'juniors';
    }

    /**
     * Полные настройки турнира по умолчанию (если в config.php нет competition).
     * Юниоры: 2003–2012, открытая: 1926–2012.
     */
    public static function defaultCompetitionConfig(): array
    {
        return [
            'event_year'     => 2026,
            'min_weight'     => 20.0,
            'max_weight'     => 300.0,
            'min_birth_year' => 1926,
            'max_birth_year' => 2012,
            'age_groups'     => [
                'juniors' => [
                    'code'           => 'juniors',
                    'name'           => 'Юноши — юниоры',
                    'min_age'        => 14,
                    'max_age'        => 23,
                    'min_birth_year' => 2003,
                    'max_birth_year' => 2012,
                    'description'    => 'до 23 лет (2003–2012 г.р.)',
                ],
                'open' => [
                    'code'           => 'open',
                    'name'           => 'Открытая (Open)',
                    'min_age'        => 14,
                    'max_age'        => 100,
                    'min_birth_year' => 1926,
                    'max_birth_year' => 2012,
                    'description'    => 'от 14 лет (1926–2012 г.р.)',
                ],
            ],
            'weight_categories' => self::defaultRules(),
        ];
    }

    /**
     * Настройки турнира из конфига с подстановкой дефолтов.
     * Важно: на сервере config.php часто без competition (файл не деплоится).
     */
    public static function competitionConfig(?array $fromAppConfig = null): array
    {
        $defaults = self::defaultCompetitionConfig();
        $cfg = $fromAppConfig ?? ($GLOBALS['app_config']['competition'] ?? []);
        if (!is_array($cfg) || $cfg === []) {
            return $defaults;
        }

        $merged = array_replace($defaults, $cfg);
        $merged['age_groups'] = array_replace(
            $defaults['age_groups'],
            is_array($cfg['age_groups'] ?? null) ? $cfg['age_groups'] : []
        );
        foreach (['juniors', 'open'] as $key) {
            if (isset($merged['age_groups'][$key]) && is_array($merged['age_groups'][$key])) {
                $merged['age_groups'][$key] = array_replace(
                    $defaults['age_groups'][$key],
                    $merged['age_groups'][$key]
                );
            }
        }
        $merged['weight_categories'] = is_array($cfg['weight_categories'] ?? null)
            ? $cfg['weight_categories']
            : $defaults['weight_categories'];

        return $merged;
    }

    /** Дефолтные правила весовых категорий турнира. */
    public static function defaultRules(): array
    {
        return [
            'powerlifting' => [
                'juniors' => [
                    ['max' => 50.0,  'label' => 'до 50 кг'],
                    ['max' => 60.0,  'label' => 'до 60 кг'],
                    ['max' => 67.5,  'label' => 'до 67.5 кг'],
                    ['max' => 75.0,  'label' => 'до 75 кг'],
                    ['max' => 82.5,  'label' => 'до 82.5 кг'],
                    ['max' => null,  'label' => '82.5+ кг'],
                ],
                'open' => [
                    ['max' => 60.0,  'label' => 'до 60 кг'],
                    ['max' => 67.5,  'label' => 'до 67.5 кг'],
                    ['max' => 75.0,  'label' => 'до 75 кг'],
                    ['max' => 82.5,  'label' => 'до 82.5 кг'],
                    ['max' => 90.0,  'label' => 'до 90 кг'],
                    ['max' => 100.0, 'label' => 'до 100 кг'],
                    ['max' => null,  'label' => '100+ кг'],
                ],
            ],
            'armwrestling' => [
                'juniors' => [
                    ['max' => 60.0,  'label' => 'до 60 кг'],
                    ['max' => 75.0,  'label' => 'до 75 кг'],
                    ['max' => null,  'label' => '80+ кг'],
                ],
                'open' => [
                    ['max' => 70.0,  'label' => 'до 70 кг'],
                    ['max' => 80.0,  'label' => 'до 80 кг'],
                    ['max' => null,  'label' => '80+ кг'],
                ],
            ],
        ];
    }

    public static function resolve(string $gender, float $bodyWeight): array
    {
        $gender = $gender === 'F' ? 'F' : 'M';
        foreach (self::classes($gender) as $class) {
            if ($class['max'] === null || $bodyWeight <= $class['max']) {
                return [
                    'label'  => $class['label'],
                    'max'    => $class['max'],
                    'gender' => $gender,
                ];
            }
        }

        $last = self::classes($gender);
        $last = $last[array_key_last($last)];
        return [
            'label'  => $last['label'],
            'max'    => $last['max'],
            'gender' => $gender,
        ];
    }

    public static function labelFor(string $gender, float $bodyWeight): string
    {
        return self::resolve($gender, $bodyWeight)['label'];
    }

    /**
     * Верхняя граница категории из лейбла («до 60 кг» → 60, «82.5+ кг» → null).
     */
    public static function maxFromLabel(string $label, ?array $competitionConfig = null): ?float
    {
        $label = trim($label);
        if ($label === '') {
            return null;
        }

        $config = $competitionConfig ?? ($GLOBALS['app_config']['competition'] ?? []);
        $weightCategories = $config['weight_categories'] ?? self::defaultRules();

        foreach ($weightCategories as $byAge) {
            if (!is_array($byAge)) {
                continue;
            }
            foreach ($byAge as $rules) {
                if (!is_array($rules)) {
                    continue;
                }
                foreach ($rules as $rule) {
                    if (!is_array($rule)) {
                        continue;
                    }
                    if (trim((string) ($rule['label'] ?? '')) === $label) {
                        return $rule['max'] !== null ? (float) $rule['max'] : null;
                    }
                }
            }
        }

        foreach (['M', 'F'] as $g) {
            foreach (self::classes($g) as $class) {
                if ($class['label'] === $label) {
                    return $class['max'];
                }
            }
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*\+/u', $label)) {
            return null;
        }
        if (preg_match('/(\d+(?:[.,]\d+)?)/u', $label, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return null;
    }

    /** Все категории турнира (мужчины, затем женщины). */
    public static function allLabels(): array
    {
        $labels = [];
        foreach (['M', 'F'] as $g) {
            foreach (self::classes($g) as $class) {
                $labels[] = $class['label'];
            }
        }
        return $labels;
    }

    /** Есть ли такая категория в справочнике. */
    public static function isValidLabel(string $label): bool
    {
        return in_array($label, self::allLabels(), true);
    }

    /** JS-совместимый список для live-preview в карточке. */
    public static function jsPayload(): array
    {
        $map = [];
        foreach (['M', 'F'] as $g) {
            $map[$g] = array_map(
                static fn (array $c) => [$c['max'], $c['label']],
                self::classes($g)
            );
        }
        return $map;
    }
}
