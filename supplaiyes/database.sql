-- SUPPLAIYES - skema database
-- Import file ini lewat phpMyAdmin (tab Import) atau: mysql -u root < database.sql
-- Setelah itu buka http://localhost/supplaiyes/setup.php untuk membuat akun admin + data dummy.

CREATE DATABASE IF NOT EXISTS supplaiyes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE supplaiyes;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS reservation_logs;
DROP TABLE IF EXISTS food_status_logs;
DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS foods;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(80)  NOT NULL,
  email         VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','user') NOT NULL DEFAULT 'user',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role)
) ENGINE=InnoDB;

CREATE TABLE foods (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name                  VARCHAR(120) NOT NULL,
  category              VARCHAR(40)  NOT NULL,
  description           TEXT NULL,
  size_label            VARCHAR(50)  NOT NULL,
  stock                 INT NOT NULL DEFAULT 0,          -- stok yang masih bisa direservasi
  normal_price          INT UNSIGNED NULL,               -- harga normal (opsional)
  price                 INT UNSIGNED NOT NULL DEFAULT 0, -- harga surplus, 0 = gratis
  unit_weight_kg        DECIMAL(6,2) NOT NULL DEFAULT 0, -- berat per porsi, untuk estimasi laporan
  produced_at           DATETIME NOT NULL,
  distribution_deadline DATETIME NOT NULL,
  safety_status         ENUM('layak','karantina','tidak_layak') NOT NULL DEFAULT 'karantina',
  status                ENUM('available','reserved','distributed','expired','rejected','out_of_stock') NOT NULL DEFAULT 'out_of_stock',
  distributed_qty       INT UNSIGNED NOT NULL DEFAULT 0, -- total porsi yang sudah terdistribusi
  withdrawn_qty         INT UNSIGNED NOT NULL DEFAULT 0, -- total porsi yang ditarik
  is_archived           TINYINT(1) NOT NULL DEFAULT 0,   -- soft delete
  created_by            INT UNSIGNED NULL,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_foods_visible (is_archived, safety_status, status, distribution_deadline),
  KEY idx_foods_category (category),
  KEY idx_foods_deadline (distribution_deadline),
  CONSTRAINT fk_foods_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_foods_stock CHECK (stock >= 0)
) ENGINE=InnoDB;

CREATE TABLE reservations (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code        VARCHAR(20) NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  food_id     INT UNSIGNED NOT NULL,
  qty         INT UNSIGNED NOT NULL,
  status      ENUM('reserved','picked_up','cancelled','expired') NOT NULL DEFAULT 'reserved',
  reserved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at  DATETIME NOT NULL,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reservations_code (code),
  KEY idx_res_user (user_id, status),
  KEY idx_res_food (food_id, status),
  KEY idx_res_expires (status, expires_at),
  CONSTRAINT fk_res_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_res_food FOREIGN KEY (food_id) REFERENCES foods(id)
) ENGINE=InnoDB;

CREATE TABLE food_status_logs (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  food_id    INT UNSIGNED NOT NULL,
  actor_id   INT UNSIGNED NULL,           -- NULL = sistem otomatis
  action     VARCHAR(30) NOT NULL,        -- created, updated, reserved, restock, expired, pickup, distributed, withdrawn, archived, restored, status
  old_status VARCHAR(20) NULL,
  new_status VARCHAR(20) NULL,
  old_safety VARCHAR(20) NULL,
  new_safety VARCHAR(20) NULL,
  qty_change INT NOT NULL DEFAULT 0,
  note       VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_fsl_food (food_id),
  KEY idx_fsl_action (action, created_at),
  CONSTRAINT fk_fsl_food  FOREIGN KEY (food_id)  REFERENCES foods(id),
  CONSTRAINT fk_fsl_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE reservation_logs (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reservation_id INT UNSIGNED NOT NULL,
  actor_id       INT UNSIGNED NULL,
  old_status     VARCHAR(20) NULL,
  new_status     VARCHAR(20) NOT NULL,
  note           VARCHAR(255) NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rl_res (reservation_id),
  CONSTRAINT fk_rl_res   FOREIGN KEY (reservation_id) REFERENCES reservations(id),
  CONSTRAINT fk_rl_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
