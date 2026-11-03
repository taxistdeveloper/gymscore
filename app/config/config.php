<?php

return [
    'app_name'    => 'Alash Gym — Силовые соревнования',
    'event_date'  => '08.11.2026',
    'event_place' => 'Тренажёрный зал Alash Gym, 19 мкр, 40А/1',
    'manager_whatsapp' => '87474844387',
    'base_url'    => '', // например '/sport/public' если не в корне

    'db' => [
        'host'    => '127.0.0.1',
        'port'    => '3306',
        'name'    => 'sport',
        'user'    => 'root',
        'pass'    => 'root',
        'charset' => 'utf8mb4',
    ],

    // Настройки соревнования: возрастные группы и весовые категории
    'competition' => [
        'event_year'     => 2026,
        'min_weight'     => 20.0,
        'max_weight'     => 300.0,
        'min_birth_year' => 1926,
        'max_birth_year' => 2012,

        // Возрастные группы (границы берутся из настроек турнира)
        'age_groups' => [
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

        // Весовые категории по дисциплинам
        'weight_categories' => [
            // Пауэрлифтинг / силовые дисциплины
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
            // Армрестлинг
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
        ],
    ],
];
