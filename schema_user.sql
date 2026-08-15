-- Membuat tabel user untuk autentikasi dan keamanan login
CREATE TABLE IF NOT EXISTS user (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_lengkap VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME NULL,
    failed_login_count INT DEFAULT 0,
    locked_until DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed user admin default (username: admin, password: admin123)
-- Menggunakan hash bcrypt yang dihasilkan dari password_hash('admin123', PASSWORD_DEFAULT)
INSERT INTO user (nama_lengkap, username, password_hash)
VALUES ('Administrator', 'admin', '$2y$10$nxvUnhIQu63jlijpR.iNl.acYxlQIwjX.QgSP5cLZZTgAjTkzzrd.')
ON DUPLICATE KEY UPDATE nama_lengkap='Administrator';
