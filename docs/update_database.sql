CREATE TABLE IF NOT EXISTS `settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(191) NOT NULL,
  `value` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`key`, `value`, `created_at`, `updated_at`)
VALUES
  ('attendance_strict_mode', '0', NOW(), NOW()),
  ('overnight_cutoff_time', '08:50', NOW(), NOW()),
  ('allow_past_leave_requests', '0', NOW(), NOW()),
  ('attendance_supplement_limit_mode', 'last_3_days', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `value` = `value`,
  `updated_at` = `updated_at`;

-- =========================================================
-- Fix ngay cong cho cham cong ca dem
-- Nguyen tac:
-- - Checkin ngay 11, checkout rang sang ngay 12 van tinh cong ngay 11.
-- - Khong lay checkout_at lam ngay cong.
-- - Uu tien shift_assignments.work_date.
-- - Neu khong map duoc shift_assignment thi fallback DATE(checkin_at).
-- =========================================================

-- A. Kiem tra cot work_date trong attendances.
SHOW COLUMNS FROM attendances LIKE 'work_date';

-- B. Neu cau SHOW tren khong co ket qua, chay cau ALTER nay.
-- Luu y: mot so MySQL/MariaDB tren cPanel khong ho tro ADD COLUMN IF NOT EXISTS,
-- nen hay kiem tra bang SHOW COLUMNS truoc khi chay ALTER.
ALTER TABLE attendances
ADD COLUMN work_date DATE NULL AFTER shift_id;

-- C. Fix du lieu cu bang shift_assignments.work_date.
UPDATE attendances a
JOIN shift_assignments sa
  ON sa.user_id = a.user_id
 AND sa.shift_id = a.shift_id
 AND DATE(a.checkin_at) = sa.work_date
SET a.work_date = sa.work_date
WHERE a.work_date IS NULL
  AND a.checkin_at IS NOT NULL;

-- D. Fallback cho ban ghi khong map duoc shift_assignment.
UPDATE attendances
SET work_date = DATE(checkin_at)
WHERE work_date IS NULL
  AND checkin_at IS NOT NULL;

-- E. Kiem tra cac ca qua ngay.
SELECT
  id,
  user_id,
  shift_id,
  work_date,
  checkin_at,
  checkout_at
FROM attendances
WHERE checkout_at IS NOT NULL
  AND DATE(checkout_at) <> DATE(checkin_at)
ORDER BY checkin_at DESC;

-- F. Kiem tra con ban ghi thieu work_date.
SELECT
  id,
  user_id,
  shift_id,
  work_date,
  checkin_at,
  checkout_at
FROM attendances
WHERE work_date IS NULL
ORDER BY checkin_at DESC;


-- =========================================================
-- Phase 3: Khoa cong / chong sua du lieu da chot cong
-- Neu MySQL/MariaDB tren cPanel khong ho tro ADD COLUMN IF NOT EXISTS,
-- hay chay cac cau SHOW COLUMNS truoc. Neu cot da ton tai thi bo qua ALTER.
-- =========================================================

SHOW COLUMNS FROM attendances LIKE 'is_locked';
SHOW COLUMNS FROM attendances LIKE 'locked_at';
SHOW COLUMNS FROM attendances LIKE 'locked_by';

ALTER TABLE attendances
ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
ADD COLUMN locked_at DATETIME NULL AFTER is_locked,
ADD COLUMN locked_by BIGINT UNSIGNED NULL AFTER locked_at;

-- Neu muon them khoa ngoai locked_by ve users.id va DB chua co foreign key:
-- ALTER TABLE attendances
-- ADD CONSTRAINT attendances_locked_by_foreign
-- FOREIGN KEY (locked_by) REFERENCES users(id) ON DELETE SET NULL;


-- =========================================================
-- Face verification mode cho nhan su uu tien
-- Neu cot da ton tai thi bo qua ALTER tuong ung.
-- =========================================================

SHOW COLUMNS FROM users LIKE 'face_verification_mode';
SHOW COLUMNS FROM attendances LIKE 'face_verification_mode';

ALTER TABLE users
ADD COLUMN face_verification_mode ENUM('normal', 'priority') NOT NULL DEFAULT 'normal' AFTER face_descriptor;

-- Neu cot da ton tai nhung dang la VARCHAR, co the chay cau nay de chuan hoa thanh ENUM:
ALTER TABLE users
MODIFY COLUMN face_verification_mode ENUM('normal', 'priority') NOT NULL DEFAULT 'normal';

ALTER TABLE attendances
ADD COLUMN face_verification_mode VARCHAR(20) NULL AFTER status;

UPDATE users
SET face_verification_mode = 'normal'
WHERE face_verification_mode IS NULL
   OR face_verification_mode NOT IN ('normal', 'priority');


-- =========================================================
-- Log cap nhat che do xac thuc khuon mat
-- =========================================================

CREATE TABLE IF NOT EXISTS face_verification_mode_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_id BIGINT UNSIGNED NULL,
  target_user_id BIGINT UNSIGNED NULL,
  bulk_filter_json JSON NULL,
  old_mode VARCHAR(20) NULL,
  new_mode VARCHAR(20) NOT NULL,
  action VARCHAR(100) NOT NULL DEFAULT 'update_face_verification_mode',
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY face_verification_mode_logs_actor_id_index (actor_id),
  KEY face_verification_mode_logs_target_user_id_index (target_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- Ca duoc phep theo co so / branch
-- Khong xoa ca va khong thay doi du lieu gan ca cu.
-- =========================================================

CREATE TABLE IF NOT EXISTS branch_shift (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  branch_id BIGINT UNSIGNED NOT NULL,
  shift_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY branch_shift_branch_id_shift_id_unique (branch_id, shift_id),
  KEY branch_shift_shift_id_index (shift_id),
  CONSTRAINT branch_shift_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
  CONSTRAINT branch_shift_shift_id_foreign FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default 1 de giu nguyen flow cu: man gan ca hien toan bo ca.
-- Dat 0 sau khi admin da cau hinh ca cho tung co so tren giao dien.
INSERT INTO settings (`key`, `value`, created_at, updated_at)
VALUES ('shift_assignment_show_all_shifts', '1', NOW(), NOW())
ON DUPLICATE KEY UPDATE `value` = `value`, updated_at = updated_at;

-- Kiem tra sau khi chay.
SELECT * FROM branch_shift ORDER BY branch_id, shift_id;
SELECT `key`, `value` FROM settings WHERE `key` = 'shift_assignment_show_all_shifts';


-- =========================================================
-- MVP ca gay: cau truc ca va tung luot cham cong
-- Khong sua, xoa hoac backfill attendances cu.
-- =========================================================

CREATE TABLE IF NOT EXISTS shift_segments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  shift_id BIGINT UNSIGNED NOT NULL,
  segment_order TINYINT UNSIGNED NOT NULL,
  start_at TIME NOT NULL,
  end_at TIME NOT NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY shift_segments_shift_id_segment_order_unique (shift_id, segment_order),
  CONSTRAINT shift_segments_shift_id_foreign FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance_segments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  attendance_id BIGINT UNSIGNED NOT NULL,
  segment_order TINYINT UNSIGNED NOT NULL,
  checkin_at DATETIME NULL,
  checkout_at DATETIME NULL,
  checkin_latitude DECIMAL(10,7) NULL,
  checkin_longitude DECIMAL(10,7) NULL,
  checkout_latitude DECIMAL(10,7) NULL,
  checkout_longitude DECIMAL(10,7) NULL,
  late_minutes INT NOT NULL DEFAULT 0,
  early_leave_minutes INT NOT NULL DEFAULT 0,
  worked_minutes INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY attendance_segments_attendance_id_segment_order_unique (attendance_id, segment_order),
  CONSTRAINT attendance_segments_attendance_id_foreign FOREIGN KEY (attendance_id) REFERENCES attendances(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kiem tra sau khi chay.
SHOW COLUMNS FROM shift_segments;
SHOW COLUMNS FROM attendance_segments;

-- Bo sung cong theo tung doan ca gay.
-- Kiem tra truoc, neu cot da co thi khong chay ALTER.
SHOW COLUMNS FROM attendance_supplement_requests LIKE 'segment_order';

ALTER TABLE attendance_supplement_requests
ADD COLUMN segment_order TINYINT UNSIGNED NULL AFTER shift_id;

SELECT id, user_id, shift_id, segment_order, work_date, status
FROM attendance_supplement_requests
ORDER BY id DESC
LIMIT 20;


-- =========================================================
-- Ràng buộc giờ checkin/checkout theo từng ca
-- Default giu nguyen nghiep vu cu: som 60 phut, muon 240 phut,
-- chong quet lai ngay 3 phut. Cot checkout muon de NULL de fallback.
-- =========================================================

SHOW COLUMNS FROM shifts LIKE 'checkin_open_before_minutes';
SHOW COLUMNS FROM shifts LIKE 'checkin_close_after_minutes';
SHOW COLUMNS FROM shifts LIKE 'checkout_min_after_checkin_minutes';
SHOW COLUMNS FROM shifts LIKE 'checkout_close_after_shift_end_minutes';

ALTER TABLE shifts
  ADD COLUMN checkin_open_before_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60 AFTER late_after_minutes,
  ADD COLUMN checkin_close_after_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 240 AFTER checkin_open_before_minutes,
  ADD COLUMN checkout_min_after_checkin_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 3 AFTER checkin_close_after_minutes,
  ADD COLUMN checkout_close_after_shift_end_minutes SMALLINT UNSIGNED NULL AFTER checkout_min_after_checkin_minutes;

-- Neu mot hay nhieu cot da ton tai, khong chay lai ALTER tren. Kiem tra gia tri sau update:
SELECT id, name, checkin_open_before_minutes, checkin_close_after_minutes,
       checkout_min_after_checkin_minutes, checkout_close_after_shift_end_minutes
FROM shifts
ORDER BY id;
