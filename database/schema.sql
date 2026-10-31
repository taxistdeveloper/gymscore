-- Alash Gym — Соревнования по силовым видам спорта
-- 7.11.2026 | 19 микрорайон, 40А/1

CREATE DATABASE IF NOT EXISTS alash_powerlifting
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE alash_powerlifting;

-- Дисциплины с афиши
CREATE TABLE disciplines (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name_ru VARCHAR(150) NOT NULL,
  sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
  -- weight = макс. поднятый вес (кг); reps = кол-во; arm = победы/место
  scoring_type ENUM('weight', 'reps', 'arm') NOT NULL DEFAULT 'weight',
  attempts_allowed TINYINT UNSIGNED NOT NULL DEFAULT 3
) ENGINE=InnoDB;

INSERT INTO disciplines (code, name_ru, sort_order, scoring_type, attempts_allowed) VALUES
  ('bench',           'Жим лёжа',                        1, 'weight', 3),
  ('bench_folk',      'Жим лёжа народный 1/2 с/в',        2, 'reps',   1),
  ('curl_strict',     'Строгий подъём на бицепс',         3, 'weight', 3),
  ('curl_extreme',    'Экстремальный подъём на бицепс',   4, 'weight', 3),
  ('armwrestling',    'Армрестлинг',                      5, 'arm',    1);

-- Участники / заявки
CREATE TABLE participants (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  body_weight DECIMAL(6,2) NULL DEFAULT NULL COMMENT 'Собственный вес, кг (взвешивание на месте)',
  gender ENUM('M', 'F') NOT NULL DEFAULT 'M',
  birth_year YEAR DEFAULT NULL,
  age_group VARCHAR(50) DEFAULT NULL COMMENT 'Возрастная группа',
  -- pending = новая заявка; approved = в протоколе; rejected = отклонена
  status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  status_note VARCHAR(255) DEFAULT NULL COMMENT 'комментарий админа',
  reviewed_at TIMESTAMP NULL DEFAULT NULL,
  weighed_at TIMESTAMP NULL DEFAULT NULL COMMENT 'официальное взвешивание',
  weigh_note TEXT NULL COMMENT 'заметки судьи на весах',
  weight_category VARCHAR(80) NULL COMMENT 'категория по собственному весу',
  lot_number INT UNSIGNED NULL COMMENT 'жребий',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Регистрация на дисциплины
CREATE TABLE registrations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  participant_id INT UNSIGNED NOT NULL,
  discipline_id INT UNSIGNED NOT NULL,
  opening_weight DECIMAL(8,2) NULL COMMENT 'стартовый вес / заявка на 1 попытку',
  weight_category VARCHAR(80) NULL COMMENT 'весовая категория дисциплины',
  registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_reg (participant_id, discipline_id),
  FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE,
  FOREIGN KEY (discipline_id) REFERENCES disciplines(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Попытки / результаты
CREATE TABLE attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  participant_id INT UNSIGNED NOT NULL,
  discipline_id INT UNSIGNED NOT NULL,
  attempt_no TINYINT UNSIGNED NOT NULL DEFAULT 1,
  declared_weight DECIMAL(8,2) DEFAULT NULL COMMENT 'заказанный вес на попытку',
  -- Для weight/reps — результат; для arm — число побед; NULL = ещё не судили
  value DECIMAL(8,2) DEFAULT NULL,
  is_valid TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = зачёт, 0 = незачёт',
  judged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_attempt (participant_id, discipline_id, attempt_no),
  FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE CASCADE,
  FOREIGN KEY (discipline_id) REFERENCES disciplines(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Судьи (простой логин)
CREATE TABLE judges (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- Пароль: judge123
INSERT INTO judges (username, password_hash, display_name) VALUES
  ('judge', '$2y$10$8jCJg223GHBD6HimgyL9.uqA1pZeUasKv5LkSIlb85WV4vA8uXFY.', 'Главный судья');

-- Админы (управление участниками)
CREATE TABLE admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- Текущий атлет на помосте (live-табло)
CREATE TABLE platform_state (
  discipline_id INT UNSIGNED NOT NULL PRIMARY KEY,
  current_key VARCHAR(32) DEFAULT NULL,
  deferred_keys JSON NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Пары армрестлинга (кто с кем)
CREATE TABLE arm_matches (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  discipline_id INT UNSIGNED NOT NULL,
  weight_category VARCHAR(80) NOT NULL,
  round_no TINYINT UNSIGNED NOT NULL DEFAULT 1,
  match_no SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  participant1_id INT UNSIGNED NOT NULL,
  participant2_id INT UNSIGNED NULL COMMENT 'NULL = bye',
  winner_id INT UNSIGNED NULL,
  status ENUM('pending', 'done') NOT NULL DEFAULT 'pending',
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  judged_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_arm_disc_cat (discipline_id, weight_category, round_no, match_no),
  KEY idx_arm_current (discipline_id, is_current),
  FOREIGN KEY (discipline_id) REFERENCES disciplines(id) ON DELETE CASCADE,
  FOREIGN KEY (participant1_id) REFERENCES participants(id) ON DELETE CASCADE,
  FOREIGN KEY (participant2_id) REFERENCES participants(id) ON DELETE SET NULL,
  FOREIGN KEY (winner_id) REFERENCES participants(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Пароль: admin123
INSERT INTO admins (username, password_hash, display_name) VALUES
  ('admin', '$2y$10$nrfF40e7kq8NWq.A7AxFhO.iQ8JcSWttu1iHRCa.hhna1mBhgkImu', 'Администратор');

-- Демо-участники для проверки тай-брейка
-- Оба жмут 100 кг: место выше у того, кто легче (79.9 > 80.0 по месту)
INSERT INTO participants (full_name, phone, body_weight, gender, status, reviewed_at) VALUES
  ('Иванов Алексей',  '+77001112233', 80.00, 'M', 'approved', NOW()),
  ('Петров Дмитрий',  '+77004445566', 79.90, 'M', 'approved', NOW()),
  ('Сидорова Анна',   '+77007778899', 62.50, 'F', 'approved', NOW());

INSERT INTO registrations (participant_id, discipline_id)
SELECT p.id, d.id FROM participants p CROSS JOIN disciplines d;
