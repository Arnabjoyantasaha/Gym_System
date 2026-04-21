-- ============================================================
--  GYM MANAGEMENT SYSTEM — Database Schema v2
--  Default password for every user = their email address
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `gym_system`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `gym_system`;

-- ── Drop tables in reverse FK order so re-import is always clean ──
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `bmi_records`;
DROP TABLE IF EXISTS `class_bookings`;
DROP TABLE IF EXISTS `classes`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `members`;
DROP TABLE IF EXISTS `trainers`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;
SET FOREIGN_KEY_CHECKS = 1;

-- ── 1. roles ──────────────────────────────────────────────
CREATE TABLE `roles` (
  `id`        TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_name` VARCHAR(50)      NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `roles` (`id`, `role_name`) VALUES
  (1,'admin'),(2,'manager'),(3,'staff'),(4,'trainer'),(5,'member');

-- ── 2. users ──────────────────────────────────────────────
CREATE TABLE `users` (
  `id`         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100)     NOT NULL,
  `email`      VARCHAR(150)     NOT NULL,
  `password`   VARCHAR(255)     NOT NULL,
  `role_id`    TINYINT UNSIGNED NOT NULL,
  `phone`      VARCHAR(20)          NULL,
  `status`     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`),
  KEY `idx_role` (`role_id`),
  CONSTRAINT `fk_users_role`
    FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Passwords stored as plain text. Default password = email address.
INSERT INTO `users` (`name`,`email`,`password`,`role_id`,`phone`,`status`) VALUES
  ('Admin User',    'admin@gym.com',    'admin@gym.com',    1, '555-0001', 'active'),
  ('Sarah Manager', 'manager@gym.com',  'manager@gym.com',  2, '555-0002', 'active'),
  ('Tom Staff',     'staff@gym.com',    'staff@gym.com',    3, '555-0003', 'active'),
  ('Mike Trainer',  'trainer@gym.com',  'trainer@gym.com',  4, '555-0004', 'active'),
  ('Jane Member',   'member@gym.com',   'member@gym.com',   5, '555-0005', 'active'),
  ('Chris Trainer', 'trainer2@gym.com', 'trainer2@gym.com', 4, '555-0006', 'active'),
  ('Alice Member',  'alice@gym.com',    'alice@gym.com',    5, '555-0007', 'active'),
  ('Bob Member',    'bob@gym.com',      'bob@gym.com',      5, '555-0008', 'active'),
  ('Lisa Staff',    'staff2@gym.com',   'staff2@gym.com',   3, '555-0009', 'active'),
  ('David Member',  'david@gym.com',    'david@gym.com',    5, '555-0010', 'active');

-- ── 3. trainers ───────────────────────────────────────────
CREATE TABLE `trainers` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `specialization` VARCHAR(100)     NULL,
  `bio`            TEXT             NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_trainer_user` (`user_id`),
  CONSTRAINT `fk_trainers_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `trainers` (`user_id`,`specialization`,`bio`) VALUES
  (4,'Strength & Conditioning','Certified personal trainer with 8 years of experience.'),
  (6,'Yoga & Flexibility','Yoga instructor specializing in mindfulness and flexibility.');

-- ── 4. members ────────────────────────────────────────────
CREATE TABLE `members` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`          INT UNSIGNED NOT NULL,
  `membership_plan`  ENUM('monthly','quarterly','annual') NOT NULL DEFAULT 'monthly',
  `join_date`        DATE         NOT NULL,
  `expiry_date`      DATE             NULL,
  `assigned_trainer` INT UNSIGNED     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_member_user` (`user_id`),
  KEY `idx_trainer` (`assigned_trainer`),
  CONSTRAINT `fk_members_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_members_trainer`
    FOREIGN KEY (`assigned_trainer`) REFERENCES `trainers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `members` (`user_id`,`membership_plan`,`join_date`,`expiry_date`,`assigned_trainer`) VALUES
  (5,'monthly','2024-01-15','2025-04-15',1),
  (7,'annual','2024-03-01','2025-03-01',2),
  (8,'quarterly','2024-06-01','2025-06-01',1),
  (10,'monthly','2024-11-10','2025-04-10',2);

-- ── 5. attendance ─────────────────────────────────────────
CREATE TABLE `attendance` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`   INT UNSIGNED NOT NULL,
  `date`      DATE         NOT NULL,
  `check_in`  TIME             NULL,
  `check_out` TIME             NULL,
  `status`    ENUM('present','absent','late') NOT NULL DEFAULT 'present',
  PRIMARY KEY (`id`),
  KEY `idx_att_user` (`user_id`),
  KEY `idx_att_date` (`date`),
  CONSTRAINT `fk_att_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `attendance` (`user_id`,`date`,`check_in`,`check_out`,`status`) VALUES
  (5,CURDATE(),'08:00:00','10:00:00','present'),
  (7,CURDATE(),'09:15:00','11:00:00','late'),
  (8,CURDATE(),'07:45:00','09:30:00','present'),
  (3,CURDATE(),'08:00:00','17:00:00','present'),
  (4,CURDATE(),'07:00:00','15:00:00','present'),
  (5,DATE_SUB(CURDATE(),INTERVAL 1 DAY),'08:05:00','10:10:00','present'),
  (7,DATE_SUB(CURDATE(),INTERVAL 1 DAY),NULL,NULL,'absent'),
  (8,DATE_SUB(CURDATE(),INTERVAL 1 DAY),'08:00:00','09:45:00','present'),
  (10,DATE_SUB(CURDATE(),INTERVAL 1 DAY),'07:50:00','10:00:00','present'),
  (5,DATE_SUB(CURDATE(),INTERVAL 2 DAY),'08:00:00','10:00:00','present');

-- ── 6. payments ───────────────────────────────────────────
CREATE TABLE `payments` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `member_id`      INT UNSIGNED  NOT NULL,
  `amount`         DECIMAL(10,2) NOT NULL,
  `payment_date`   DATE          NOT NULL,
  `payment_method` ENUM('cash','card','online') NOT NULL DEFAULT 'cash',
  `status`         ENUM('paid','pending','failed') NOT NULL DEFAULT 'pending',
  `notes`          VARCHAR(255)      NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pay_member` (`member_id`),
  KEY `idx_pay_date`   (`payment_date`),
  CONSTRAINT `fk_pay_member`
    FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `payments` (`member_id`,`amount`,`payment_date`,`payment_method`,`status`,`notes`) VALUES
  (1,50.00,DATE_SUB(CURDATE(),INTERVAL 30 DAY),'card','paid','Monthly plan - March'),
  (1,50.00,DATE_SUB(CURDATE(),INTERVAL 1 DAY),'card','paid','Monthly plan - April'),
  (2,150.00,DATE_SUB(CURDATE(),INTERVAL 90 DAY),'online','paid','Annual plan'),
  (3,80.00,DATE_SUB(CURDATE(),INTERVAL 10 DAY),'cash','paid','Quarterly plan'),
  (4,50.00,CURDATE(),'card','pending','Monthly plan - April');

-- ── 7. classes ────────────────────────────────────────────
CREATE TABLE `classes` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `class_name`    VARCHAR(100) NOT NULL,
  `trainer_id`    INT UNSIGNED NOT NULL,
  `schedule_time` DATETIME     NOT NULL,
  `duration_min`  SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  `capacity`      SMALLINT UNSIGNED NOT NULL DEFAULT 20,
  `description`   TEXT             NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cls_trainer` (`trainer_id`),
  CONSTRAINT `fk_cls_trainer`
    FOREIGN KEY (`trainer_id`) REFERENCES `trainers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `classes` (`class_name`,`trainer_id`,`schedule_time`,`duration_min`,`capacity`,`description`) VALUES
  ('Morning HIIT',1,DATE_FORMAT(DATE_ADD(NOW(),INTERVAL 1 DAY),'%Y-%m-%d 07:00:00'),45,15,'High-intensity interval training to kickstart your day.'),
  ('Power Lifting 101',1,DATE_FORMAT(DATE_ADD(NOW(),INTERVAL 2 DAY),'%Y-%m-%d 09:00:00'),60,10,'Introduction to barbell movements and strength training.'),
  ('Yoga Flow',2,DATE_FORMAT(DATE_ADD(NOW(),INTERVAL 1 DAY),'%Y-%m-%d 18:00:00'),60,20,'Gentle yoga flow focusing on flexibility and mindfulness.'),
  ('Evening Stretch',2,DATE_FORMAT(DATE_ADD(NOW(),INTERVAL 3 DAY),'%Y-%m-%d 19:00:00'),30,25,'Full-body stretching to recover and improve mobility.'),
  ('Core Blaster',1,DATE_FORMAT(DATE_ADD(NOW(),INTERVAL 4 DAY),'%Y-%m-%d 06:30:00'),30,12,'Focused abdominal and core strengthening session.');

-- ── 8. class_bookings ─────────────────────────────────────
CREATE TABLE `class_bookings` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id` INT UNSIGNED NOT NULL,
  `class_id`  INT UNSIGNED NOT NULL,
  `booked_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_booking` (`member_id`,`class_id`),
  KEY `idx_bk_class` (`class_id`),
  CONSTRAINT `fk_bk_member`
    FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bk_class`
    FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `class_bookings` (`member_id`,`class_id`) VALUES
  (1,1),(1,2),(2,3),(3,1),(4,4),(2,1);

-- ── 9. bmi_records ────────────────────────────────────────
CREATE TABLE `bmi_records` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `member_id`   INT UNSIGNED NOT NULL,
  `height_cm`   DECIMAL(5,2) NOT NULL,
  `weight_kg`   DECIMAL(5,2) NOT NULL,
  `bmi_value`   DECIMAL(5,2) NOT NULL,
  `record_date` DATE         NOT NULL,
  `notes`       VARCHAR(255)     NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bmi_member` (`member_id`),
  CONSTRAINT `fk_bmi_member`
    FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `bmi_records` (`member_id`,`height_cm`,`weight_kg`,`bmi_value`,`record_date`,`notes`) VALUES
  (1,170.00,70.00,24.22,DATE_SUB(CURDATE(),INTERVAL 60 DAY),'Initial assessment'),
  (1,170.00,68.50,23.70,DATE_SUB(CURDATE(),INTERVAL 30 DAY),'Month 1 check-in'),
  (1,170.00,67.00,23.18,CURDATE(),'Month 2 check-in'),
  (2,165.00,58.00,21.30,DATE_SUB(CURDATE(),INTERVAL 30 DAY),'Initial'),
  (3,180.00,90.00,27.78,DATE_SUB(CURDATE(),INTERVAL 10 DAY),'Initial assessment'),
  (4,158.00,55.00,22.04,CURDATE(),'Initial');
