-- Добавить админов в уже существующую БД
USE alash_powerlifting;

CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- Пароль: admin123
INSERT INTO admins (username, password_hash, display_name)
SELECT 'admin', '$2y$10$nrfF40e7kq8NWq.A7AxFhO.iQ8JcSWttu1iHRCa.hhna1mBhgkImu', 'Администратор'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM admins WHERE username = 'admin');
