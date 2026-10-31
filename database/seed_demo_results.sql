-- Демо: одинаковый жим 100 кг — место у более лёгкого (79.9)
USE alash_powerlifting;

-- Жим лёжа (discipline id = 1)
INSERT INTO attempts (participant_id, discipline_id, attempt_no, value, is_valid) VALUES
  (1, 1, 1, 90.00, 1),
  (1, 1, 2, 100.00, 1),
  (1, 1, 3, 105.00, 0),  -- незачёт
  (2, 1, 1, 95.00, 1),
  (2, 1, 2, 100.00, 1),
  (2, 1, 3, NULL, 0),
  (3, 1, 1, 60.00, 1),
  (3, 1, 2, 65.00, 1)
ON DUPLICATE KEY UPDATE value = VALUES(value), is_valid = VALUES(is_valid);

-- Ожидаемый рейтинг жима:
-- 1) Петров 100 кг @ 79.90
-- 2) Иванов 100 кг @ 80.00   ← тот же вес на штанге, тяжелее сам
-- 3) Сидорова 65 кг @ 62.50
